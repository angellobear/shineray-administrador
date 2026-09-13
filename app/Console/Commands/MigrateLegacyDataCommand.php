<?php

namespace App\Console\Commands;

use App\Domain\Legacy\LegacyMigrator;
use App\Support\TaxCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ETL de corte desde Medusa v1 (conexión `legacy`). Reejecutable: solo migra
 * lo que falta, así que sirve tanto para la prueba en staging como para la
 * pasada incremental final con la escritura congelada en Medusa.
 */
class MigrateLegacyDataCommand extends Command
{
    protected $signature = 'migrate:legacy-data
        {--only=* : Pasos a correr: customers, products, discounts, carts, orders, b2b}
        {--chunk=500 : Filas por lote}
        {--carts-days=30 : Antigüedad máxima de carritos abiertos a migrar}
        {--dry-run : Ejecuta dentro de una transacción y la revierte al final}';

    protected $description = 'Migra los datos existentes de Medusa v1 (conexión legacy) al esquema nuevo';

    public function handle(TaxCalculator $tax): int
    {
        $migrator = new LegacyMigrator(
            legacy: DB::connection('legacy'),
            tax: $tax,
            chunkSize: max(1, (int) $this->option('chunk')),
            cartsDays: max(0, (int) $this->option('carts-days')),
            log: fn (string $message) => $this->line($message),
        );

        $only = array_values(array_filter((array) $this->option('only')));

        $run = function () use ($migrator, $only): void {
            $report = $migrator->run($only);

            $this->table(['Paso', 'Migrados', 'Omitidos'], collect($report)->map(fn (array $r, string $step): array => [$step, $r['migrated'], $r['skipped']])->values()->all());
        };

        if (! $this->option('dry-run')) {
            $run();

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($run): void {
                $run();
                $this->warn('Dry run: revirtiendo la transacción.');

                throw new DryRunRollback;
            });
        } catch (DryRunRollback) {
            // Esperado: nada quedó escrito.
        }

        return self::SUCCESS;
    }
}
