<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add unt_code to master_products to store the product's base unit of measure.
     */
    public function up(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->string('unt_code', 3)->nullable()->after('cl3_code')->comment('Unidad de medida base');
            $table->index('unt_code');
        });
    }

    public function down(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->dropIndex(['unt_code']);
            $table->dropColumn('unt_code');
        });
    }
};
