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
        Schema::table('master_discount_detail_products', function (Blueprint $table) {
            // Make columns nullable (supported natively in Laravel 12)
            $table->string('pro_code', 50)->nullable()->change();
            $table->string('unt_code', 50)->nullable()->change();

            // Add hierarchy and missing fields if they don't exist
            if (!Schema::hasColumn('master_discount_detail_products', 'cl1code')) {
                $table->string('cl1code', 50)->nullable()->after('pro_code');
            }
            if (!Schema::hasColumn('master_discount_detail_products', 'cl2code')) {
                $table->string('cl2code', 50)->nullable()->after('cl1code');
            }
            if (!Schema::hasColumn('master_discount_detail_products', 'cl3code')) {
                $table->string('cl3code', 50)->nullable()->after('cl2code');
            }
            if (!Schema::hasColumn('master_discount_detail_products', 'cl4code')) {
                $table->string('cl4code', 50)->nullable()->after('cl3code');
            }
            if (!Schema::hasColumn('master_discount_detail_products', 'pro_code_ingredient')) {
                $table->string('pro_code_ingredient', 50)->nullable()->after('cl4code');
            }
            if (!Schema::hasColumn('master_discount_detail_products', 'quo_code')) {
                $table->string('quo_code', 50)->nullable()->after('pro_code_ingredient');
            }
            if (!Schema::hasColumn('master_discount_detail_products', 'con_code')) {
                $table->string('con_code', 50)->nullable()->after('quo_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_discount_detail_products', function (Blueprint $table) {
            $table->string('pro_code', 50)->nullable(false)->change();
            $table->string('unt_code', 50)->nullable(false)->change();

            $table->dropColumn([
                'cl1code',
                'cl2code',
                'cl3code',
                'cl4code',
                'pro_code_ingredient',
                'quo_code',
                'con_code'
            ]);
        });
    }
};
