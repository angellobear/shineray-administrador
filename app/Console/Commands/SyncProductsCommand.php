<?php

namespace App\Console\Commands;

use App\Domain\ErpSync\Jobs\SyncProductsJob;
use Illuminate\Console\Command;

class SyncProductsCommand extends Command
{
    protected $signature = 'shineray:sync-products {--now : Ejecuta en este proceso en vez de encolar}';

    protected $description = 'Sincroniza el catálogo de productos desde el ERP Shineray/Massline';

    public function handle(): int
    {
        if ($this->option('now')) {
            SyncProductsJob::dispatchSync();
            $this->info('Sincronización de productos ejecutada.');

            return self::SUCCESS;
        }

        SyncProductsJob::dispatch();
        $this->info('Sincronización de productos encolada.');

        return self::SUCCESS;
    }
}
