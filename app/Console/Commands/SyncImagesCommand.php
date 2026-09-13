<?php

namespace App\Console\Commands;

use App\Domain\ErpSync\Jobs\SyncImagesJob;
use Illuminate\Console\Command;

class SyncImagesCommand extends Command
{
    protected $signature = 'shineray:sync-images {--now : Ejecuta en este proceso en vez de encolar}';

    protected $description = 'Descarga las imágenes de productos del ERP y las sube al bucket público';

    public function handle(): int
    {
        if ($this->option('now')) {
            SyncImagesJob::dispatchSync();
            $this->info('Sincronización de imágenes ejecutada.');

            return self::SUCCESS;
        }

        SyncImagesJob::dispatch();
        $this->info('Sincronización de imágenes encolada.');

        return self::SUCCESS;
    }
}
