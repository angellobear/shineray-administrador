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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            // Identificador expuesto al storefront (carritos de invitado): no enumerable.
            $table->ulid('public_id')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('shipping_total')->default(0);
            $table->unsignedInteger('tax_total')->default(0);
            $table->unsignedInteger('discount_total')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->foreignId('shipping_address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->json('metadata')->nullable();
            // Abandoned cart (portado 1:1 desde cart.abandoned_* de Medusa).
            $table->timestamp('abandoned_completed_at')->nullable();
            $table->unsignedInteger('abandoned_count')->default(0);
            $table->unsignedBigInteger('abandoned_last_interval')->nullable();
            $table->timestamp('abandoned_lastdate')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_activity_at']);
            $table->index('email');
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            // Snapshot del precio al momento de agregar; no se recalcula del producto.
            $table->unsignedInteger('unit_price');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['cart_id', 'product_variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
