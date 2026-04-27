<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->string('pro_short_name')->nullable()->after('name');
            $table->string('barcode')->nullable()->after('pro_short_name');
            $table->string('cl4_code')->nullable()->after('cl3_code');
            $table->string('brand_code', 20)->nullable()->after('cl4_code');
            $table->string('segment_code', 10)->nullable()->after('brand_code');
            $table->integer('multiplicity')->default(1)->after('segment_code');
            
            $table->index('barcode');
            $table->index('cl4_code');
            $table->index('segment_code');
        });
    }

    public function down(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->dropIndex(['barcode']);
            $table->dropIndex(['cl4_code']);
            $table->dropIndex(['segment_code']);
            
            $table->dropColumn([
                'pro_short_name',
                'barcode',
                'cl4_code',
                'brand_code',
                'segment_code',
                'multiplicity'
            ]);
        });
    }
};
