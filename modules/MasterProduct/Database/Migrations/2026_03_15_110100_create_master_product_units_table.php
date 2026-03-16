<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_product_units', function (Blueprint $table) {
            $table->id();
            $table->string('pro_code', 18)->comment('SKU del producto - FK to master_products');
            $table->string('unt_code', 3)->comment('Unidad de medida - FK to master_units');
            $table->string('pru_divide_by', 13)->nullable()->comment('Cantidad de componente');
            $table->string('pru_bar_code', 20)->nullable()->comment('Código de barras de unidad');

            $table->unique(['pro_code', 'unt_code'], 'master_prod_unit_unique');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_product_units');
    }
};
