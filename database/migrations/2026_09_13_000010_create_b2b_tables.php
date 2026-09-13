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
        Schema::create('b2b_clients', function (Blueprint $table) {
            $table->id();
            // FK real (hoy la relación se resuelve en runtime por email).
            $table->foreignId('customer_id')->nullable()->unique()->constrained()->nullOnDelete();
            // Código externo Shineray / RUC.
            $table->string('id_client', 30)->unique();
            $table->string('type_client', 30)->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->json('address')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('erp_synced_at')->nullable();
            $table->timestamps();

            $table->index('email');
        });

        Schema::create('b2b_policies', function (Blueprint $table) {
            $table->id();
            // FK real (reemplaza el `cod_cliente varchar` suelto).
            $table->foreignId('b2b_client_id')->unique()->constrained('b2b_clients')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->decimal('credit_factor', 10, 4)->default(0);
            $table->unsignedInteger('installments')->default(1);
            $table->timestamp('erp_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('b2b_transportistas', function (Blueprint $table) {
            $table->id();
            $table->string('ruc', 20)->unique();
            $table->string('business_name');
            $table->timestamp('erp_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('b2b_transportistas');
        Schema::dropIfExists('b2b_policies');
        Schema::dropIfExists('b2b_clients');
    }
};
