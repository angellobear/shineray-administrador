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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('handle')->unique();
            $table->string('thumbnail', 2048)->nullable();
            $table->json('images')->nullable();
            // `draft` es la visibilidad controlada por el sync del ERP (producto que ya
            // no viene en el feed). `deleted_at` es solo para borrado manual desde el admin.
            $table->string('status', 20)->default('draft');
            // Campos ricos del ERP (codigo_marca, moto_modelo, nivel_1..4, anio_desde...).
            // Se mantienen en JSON porque el ERP puede agregar campos sin migración.
            $table->json('metadata')->nullable();
            $table->timestamp('erp_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('erp_synced_at');
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // SKU = COD_PRODUCTO del ERP.
            $table->string('sku')->unique();
            $table->string('title');
            // Centavos USD, sin IVA.
            $table->unsignedInteger('price');
            $table->integer('inventory_quantity')->default(0);
            // Regla de negocio: el stock real lo controla el ERP. Con allow_backorder=false
            // el checkout podría rechazar una orden DESPUÉS de cobrar y facturar.
            $table->boolean('allow_backorder')->default(true);
            $table->boolean('manage_inventory')->default(false);
            $table->decimal('weight_kg', 8, 3)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
