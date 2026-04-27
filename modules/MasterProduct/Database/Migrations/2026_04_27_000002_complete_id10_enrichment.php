<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update master_products table
        Schema::table('master_products', function (Blueprint $table) {
            $table->string('pro_bom_code', 25)->nullable()->after('unt_code');
            $table->boolean('pro_return_allowed')->default(0)->after('pro_bom_code');
            $table->boolean('pro_damage_returns_allowed')->default(0)->after('pro_return_allowed');
            $table->boolean('pro_available_for_sale')->default(1)->after('pro_damage_returns_allowed');
            $table->boolean('pro_customer_inventory_allowed')->default(0)->after('pro_available_for_sale');
        });

        // 2. Update master_product_units table
        Schema::table('master_product_units', function (Blueprint $table) {
            $table->decimal('pru_multiply_by', 12, 4)->nullable()->after('unt_code');
        });
    }

    public function down(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->dropColumn([
                'pro_bom_code',
                'pro_return_allowed',
                'pro_damage_returns_allowed',
                'pro_available_for_sale',
                'pro_customer_inventory_allowed'
            ]);
        });

        Schema::table('master_product_units', function (Blueprint $table) {
            $table->dropColumn('pru_multiply_by');
        });
    }
};
