<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_products_price_polar', function (Blueprint $table) {
            $table->decimal('precio_venta_caja_con_iva', 10, 2)->nullable()->after('precio_compra_caja_con_iva');
            $table->decimal('iva', 5, 2)->nullable()->after('precio_venta_caja_con_iva');
        });
    }

    public function down(): void
    {
        Schema::table('master_products_price_polar', function (Blueprint $table) {
            $table->dropColumn(['precio_venta_caja_con_iva', 'iva']);
        });
    }
};
