<?php

namespace App\Domain\ErpSync\Jobs;

use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Domain\ErpSync\DTOs\ErpProduct;
use App\Enums\ErpSyncType;
use App\Enums\Integration;
use App\Models\ErpSyncRun;
use App\Models\IntegrationLog;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Descarga la imagen de cada producto desde el ERP y la sube al bucket
 * público (reemplaza `sync-images.ts` + `aws-api.js`). Un fallo por imagen
 * se registra y no detiene la corrida.
 */
class SyncImagesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public int $uniqueFor = 7200;

    public int $tries = 1;

    public function handle(ErpClientContract $erp): void
    {
        $run = ErpSyncRun::start(ErpSyncType::Images);

        try {
            $products = $erp->fetchProducts(withStock: false);
            $disk = Storage::disk((string) config('shineray.catalog.images_disk'));
            $path = trim((string) config('shineray.catalog.images_path'), '/');
            $uploaded = 0;
            $failed = 0;

            foreach ($products as $product) {
                try {
                    $disk->put($path.'/'.$product->sku.'.jpg', $this->download($product));
                    $uploaded++;
                } catch (Throwable $exception) {
                    $failed++;
                    IntegrationLog::record(Integration::ShinerayErp, 'image_sync_fail', [
                        'sku' => $product->sku,
                        'message' => $exception->getMessage(),
                    ], $run);
                }
            }

            $run->finish(['fetched' => $products->count(), 'updated' => $uploaded], metadata: ['failed' => $failed]);
        } catch (Throwable $exception) {
            $run->fail($exception);
            IntegrationLog::record(Integration::ShinerayErp, 'sync_images_fail', ['message' => $exception->getMessage()], $run);

            throw $exception;
        }
    }

    /**
     * @throws ConnectionException|RequestException
     */
    private function download(ErpProduct $product): string
    {
        $response = Http::connectTimeout(5)
            ->timeout(15)
            ->retry(3, 500, throw: false)
            ->withUserAgent('Mozilla/5.0 (compatible; ShinerayAdministrador/1.0)')
            ->get((string) config('services.shineray_erp.base_url').'/imageApi/img', ['code' => $product->sku])
            ->throw();

        $body = $response->body();
        $expected = (int) $response->header('Content-Length');

        if ($expected > 0 && strlen($body) !== $expected) {
            throw new \RuntimeException(sprintf('Tamaño inválido: esperado %d, recibido %d', $expected, strlen($body)));
        }

        if ($body === '') {
            throw new \RuntimeException('Imagen vacía.');
        }

        return $body;
    }
}
