<?php

namespace App\Domain\ErpSync\Jobs;

use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Domain\ErpSync\DTOs\ErpProduct;
use App\Enums\ErpSyncRunStatus;
use App\Enums\ErpSyncType;
use App\Enums\Integration;
use App\Enums\ProductStatus;
use App\Models\ErpSyncRun;
use App\Models\IntegrationLog;
use App\Models\Product;
use App\Models\ProductVariant;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sincroniza el catálogo desde el ERP (reemplaza `sync-products.ts`).
 *
 * Reglas preservadas del código actual:
 * - Dedup por COD_PRODUCTO (lo hace el cliente del ERP, "primera fila gana").
 * - Guarda de seguridad: si el feed trae menos de `min_products_for_unpublish`
 *   productos no se despublica nada (respuesta vacía o corrupta del ERP).
 * - `allow_backorder = true` siempre (el stock real lo controla el ERP).
 * - Producto que ya no viene en el feed → `status = draft`.
 * - `metadata` se fusiona, nunca se reemplaza: `is_b2b`, `IS_PROMO`, `OLD_PRICE`
 *   e `ID_ITEM_NS` se editan a mano en el admin y deben sobrevivir al sync.
 *
 * Correcciones respecto a Node: todo corre dentro de una transacción, un
 * producto despublicado por el sync vuelve a publicarse si reaparece en el feed
 * (un draft manual del admin no se toca), y cada corrida queda en `erp_sync_runs`.
 */
class SyncProductsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Marca en metadata para distinguir "draft por el sync" de "draft manual". */
    public const UNPUBLISHED_BY_SYNC_KEY = '_unpublished_by_sync';

    public int $timeout = 3600;

    public int $uniqueFor = 3600;

    public int $tries = 1;

    public function handle(ErpClientContract $erp): void
    {
        $run = ErpSyncRun::start(ErpSyncType::Products);

        try {
            $products = $erp->fetchProducts();
            $startedAt = $run->started_at;

            [$counts, $touchedIds, $unpublishedIds] = DB::transaction(function () use ($products, $startedAt): array {
                $touchedIds = [];
                $created = 0;
                $updated = 0;

                $products->chunk(500)->each(function (Collection $chunk) use (&$touchedIds, &$created, &$updated, $startedAt): void {
                    /** @var Collection<int, ErpProduct> $chunk */
                    $variants = ProductVariant::query()
                        ->with('product')
                        ->whereIn('sku', $chunk->map(fn (ErpProduct $product): string => $product->sku))
                        ->get()
                        ->keyBy('sku');

                    foreach ($chunk as $erpProduct) {
                        $variant = $variants->get($erpProduct->sku);

                        if ($variant instanceof ProductVariant) {
                            $this->updateProduct($variant, $erpProduct, $startedAt);
                            $updated++;
                            $touchedIds[] = $variant->product_id;
                        } else {
                            $touchedIds[] = $this->createProduct($erpProduct, $startedAt)->id;
                            $created++;
                        }
                    }
                });

                $unpublishedIds = $this->unpublishMissing($products->count(), $touchedIds);

                return [
                    ['fetched' => $products->count(), 'created' => $created, 'updated' => $updated, 'unpublished' => count($unpublishedIds)],
                    $touchedIds,
                    $unpublishedIds,
                ];
            });

            $this->syncSearchIndex($touchedIds, $unpublishedIds);

            $run->finish($counts, $this->statusFor($products->count(), $counts['unpublished']));
        } catch (Throwable $exception) {
            $run->fail($exception);
            IntegrationLog::record(Integration::ShinerayErp, 'sync_products_fail', ['message' => $exception->getMessage()], $run);

            throw $exception;
        }
    }

    private function createProduct(ErpProduct $erpProduct, CarbonInterface $syncedAt): Product
    {
        $product = Product::withoutSyncingToSearch(fn (): Product => Product::create([
            'title' => $erpProduct->title,
            'description' => $erpProduct->description,
            'handle' => $erpProduct->handle,
            'thumbnail' => $erpProduct->thumbnail,
            'status' => ProductStatus::Published,
            'metadata' => $erpProduct->metadata,
            'erp_synced_at' => $syncedAt,
        ]));

        $product->variants()->create([
            'sku' => $erpProduct->sku,
            'title' => 'Default',
            'price' => $erpProduct->priceCents,
            'inventory_quantity' => $erpProduct->stockQuantity,
            'allow_backorder' => true,
            'manage_inventory' => false,
            'weight_kg' => $erpProduct->weightKg,
        ]);

        return $product;
    }

    private function updateProduct(ProductVariant $variant, ErpProduct $erpProduct, CarbonInterface $syncedAt): void
    {
        $product = $variant->product;
        $metadata = array_merge($product->metadata ?? [], $erpProduct->metadata);

        $attributes = [
            'title' => $erpProduct->title,
            'thumbnail' => $erpProduct->thumbnail,
            'erp_synced_at' => $syncedAt,
        ];

        if (($metadata[self::UNPUBLISHED_BY_SYNC_KEY] ?? false) === true) {
            unset($metadata[self::UNPUBLISHED_BY_SYNC_KEY]);
            $attributes['status'] = ProductStatus::Published;
        }

        $attributes['metadata'] = $metadata;

        Product::withoutSyncingToSearch(fn (): bool => $product->update($attributes));

        $variant->update([
            'price' => $erpProduct->priceCents,
            'inventory_quantity' => $erpProduct->stockQuantity,
            'allow_backorder' => true,
            'weight_kg' => $erpProduct->weightKg ?? $variant->weight_kg,
        ]);
    }

    /**
     * Pasa a `draft` los productos publicados que no vinieron en el feed.
     *
     * @param  list<int>  $touchedIds
     * @return list<int>
     */
    private function unpublishMissing(int $fetchedCount, array $touchedIds): array
    {
        if ($fetchedCount < (int) config('shineray.erp_sync.min_products_for_unpublish')) {
            return [];
        }

        $ids = [];

        Product::query()
            ->published()
            ->whereNotIn('id', $touchedIds)
            ->chunkById(500, function (Collection $products) use (&$ids): void {
                foreach ($products as $product) {
                    $metadata = $product->metadata ?? [];
                    $metadata[self::UNPUBLISHED_BY_SYNC_KEY] = true;

                    Product::withoutSyncingToSearch(fn (): bool => $product->update([
                        'status' => ProductStatus::Draft,
                        'metadata' => $metadata,
                    ]));

                    $ids[] = $product->id;
                }
            });

        return $ids;
    }

    /**
     * @param  list<int>  $touchedIds
     * @param  list<int>  $unpublishedIds
     */
    private function syncSearchIndex(array $touchedIds, array $unpublishedIds): void
    {
        $indexer = new Product;

        if ($unpublishedIds !== []) {
            Product::query()->whereKey($unpublishedIds)
                ->chunkById(500, fn (Collection $products) => $indexer->queueRemoveFromSearch($products));
        }

        if ($touchedIds !== []) {
            Product::query()->with('variant')->whereKey($touchedIds)
                ->chunkById(500, fn (Collection $products) => $indexer->queueMakeSearchable($products));
        }
    }

    private function statusFor(int $fetched, int $unpublished): ErpSyncRunStatus
    {
        if ($fetched < (int) config('shineray.erp_sync.min_products_for_unpublish') && $unpublished === 0) {
            return ErpSyncRunStatus::Skipped;
        }

        return ErpSyncRunStatus::Succeeded;
    }
}
