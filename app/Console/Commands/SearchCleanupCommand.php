<?php

namespace App\Console\Commands;

use App\Enums\Integration;
use App\Models\IntegrationLog;
use App\Models\Product;
use Illuminate\Console\Command;
use Laravel\Scout\EngineManager;
use Meilisearch\Client as MeilisearchClient;
use Meilisearch\Contracts\DocumentsQuery;

/**
 * Elimina del índice documentos que ya no corresponden a un producto
 * publicado (reemplaza `meilisearch-cleanup.ts`). Tarea de mantenimiento,
 * no un flujo por evento.
 */
class SearchCleanupCommand extends Command
{
    protected $signature = 'search:cleanup {--dry-run : Solo reporta, no borra}';

    protected $description = 'Quita del índice de búsqueda los productos que ya no están publicados';

    public function handle(EngineManager $engines): int
    {
        // Productos existentes pero no publicados: Scout los quita con el modelo.
        $unpublished = Product::query()->withTrashed()->whereNot('status', 'published');
        $unpublishedCount = $unpublished->count();

        if (! $this->option('dry-run') && $unpublishedCount > 0) {
            $unpublished->chunkById(500, fn ($products) => (new Product)->queueRemoveFromSearch($products));
        }

        $this->info("Productos no publicados quitados del índice: {$unpublishedCount}");

        // Documentos huérfanos (producto borrado físicamente): solo con Meilisearch.
        $engine = $engines->engine();

        if (! method_exists($engine, 'getMeilisearchClient') && ! property_exists($engine, 'meilisearch')) {
            $this->line('Motor sin cliente Meilisearch: se omite la limpieza de huérfanos.');

            return self::SUCCESS;
        }

        $client = app(MeilisearchClient::class);
        $index = $client->index((new Product)->searchableAs());
        $orphans = [];
        $offset = 0;

        do {
            $documents = $index->getDocuments((new DocumentsQuery)->setFields(['id'])->setLimit(1000)->setOffset($offset));
            $ids = array_map(fn (array $document): int => (int) $document['id'], $documents->getResults());
            $existing = Product::query()->published()->whereKey($ids)->pluck('id')->all();
            $orphans = array_merge($orphans, array_values(array_diff($ids, $existing)));
            $offset += 1000;
        } while (count($ids) === 1000);

        if ($orphans !== [] && ! $this->option('dry-run')) {
            $index->deleteDocuments($orphans);
            IntegrationLog::record(Integration::Meilisearch, 'cleanup_orphans', ['ids' => $orphans], level: 'info');
        }

        $this->info('Documentos huérfanos en el índice: '.count($orphans));

        return self::SUCCESS;
    }
}
