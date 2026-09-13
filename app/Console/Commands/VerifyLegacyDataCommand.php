<?php

namespace App\Console\Commands;

use App\Domain\Legacy\LegacyMigrator;
use App\Support\TaxCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Compara conteos y una muestra de órdenes entre Medusa v1 y el esquema nuevo antes del corte. */
class VerifyLegacyDataCommand extends Command
{
    protected $signature = 'migrate:legacy-verify {--sample=20 : Órdenes a muestrear}';

    protected $description = 'Verifica la migración de datos legacy (conteos y muestra de órdenes)';

    public function handle(TaxCalculator $tax): int
    {
        $migrator = new LegacyMigrator(DB::connection('legacy'), $tax);
        $failed = false;

        $rows = [];

        foreach ($migrator->counts() as $table => $count) {
            $ok = $count['new'] >= $count['legacy'];
            $failed = $failed || ! $ok;
            $rows[] = [$table, $count['legacy'], $count['new'], $ok ? 'OK' : 'FALTAN'];
        }

        $this->table(['Tabla', 'Legacy', 'Nuevo', 'Estado'], $rows);

        foreach ($migrator->sampleOrders(max(1, (int) $this->option('sample'))) as $sample) {
            $failed = $failed || ! $sample['ok'];
            $this->line(sprintf('%s %s — %s', $sample['ok'] ? '✔' : '✘', $sample['order_number'], $sample['detail']));
        }

        if ($failed) {
            $this->error('La verificación encontró diferencias.');

            return self::FAILURE;
        }

        $this->info('Verificación OK.');

        return self::SUCCESS;
    }
}
