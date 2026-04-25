<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('master_products_price_polar')) {
            Schema::create('master_products_price_polar', function (Blueprint $table) {
                $table->id();
                $table->string('lgnstreet1');
                $table->string('material');
                $table->string('descripcion')->nullable();
                $table->decimal('precio_compra_caja_con_iva', 10, 2)->nullable();
                $table->timestamps();

                // Clave única para upsert
                $table->unique(['material', 'lgnstreet1'], 'unique_price_material_lgnstreet');
                
                $table->index('material');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('master_products_price_polar');
    }
};
