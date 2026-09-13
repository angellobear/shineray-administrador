<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reemplaza el archivo local logs/payments.jsonl. Cubre pagos, envíos y ERP:
        // una sola tabla consultable desde el admin, válida con varias instancias.
        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('integration', 30);
            // servientrega_fail, invoice_fail, quote_fallback, etc. (mismo vocabulario que hoy).
            $table->string('event', 60);
            $table->string('level', 10)->default('error');
            $table->nullableMorphs('loggable');
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['integration', 'event']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
    }
};
