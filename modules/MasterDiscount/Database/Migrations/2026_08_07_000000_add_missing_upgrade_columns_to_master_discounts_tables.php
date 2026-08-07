<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregar columnas faltantes a master_discounts y sus tablas asociadas en el HUB.
     */
    public function up(): void
    {
        // 1. master_discounts
        Schema::table('master_discounts', function (Blueprint $table) {
            if (!Schema::hasColumn('master_discounts', 'dis_can_be_disabled')) {
                $table->string('dis_can_be_disabled')->nullable()->after('dis_name');
            }
            if (!Schema::hasColumn('master_discounts', 'dis_enabled_value_on')) {
                $table->string('dis_enabled_value_on')->nullable()->after('dis_can_be_disabled');
            }
            if (!Schema::hasColumn('master_discounts', 'dis_disable_for_detail')) {
                $table->string('dis_disable_for_detail')->nullable()->after('dis_enabled_value_on');
            }
            if (!Schema::hasColumn('master_discounts', 'source_file')) {
                $table->string('source_file')->nullable()->after('dis_disable_for_detail');
            }
            if (!Schema::hasColumn('master_discounts', 'saved_at')) {
                $table->timestamp('saved_at')->nullable()->after('source_file');
            }
        });

        // 2. master_discount_details
        Schema::table('master_discount_details', function (Blueprint $table) {
            if (!Schema::hasColumn('master_discount_details', 'did_order')) {
                $table->string('did_order')->nullable()->after('did_name');
            }
            if (!Schema::hasColumn('master_discount_details', 'tp1code')) {
                $table->string('tp1code')->nullable()->after('did_order');
            }
            if (!Schema::hasColumn('master_discount_details', 'tp2code')) {
                $table->string('tp2code')->nullable()->after('tp1code');
            }
            if (!Schema::hasColumn('master_discount_details', 'tp3code')) {
                $table->string('tp3code')->nullable()->after('tp2code');
            }
            if (!Schema::hasColumn('master_discount_details', 'unt_code_required')) {
                $table->string('unt_code_required')->nullable()->after('tp3code');
            }
            if (!Schema::hasColumn('master_discount_details', 'pol_code')) {
                $table->string('pol_code')->nullable()->after('unt_code_required');
            }
            if (!Schema::hasColumn('master_discount_details', 'did_cascade')) {
                $table->boolean('did_cascade')->default(false)->after('pol_code');
            }
            if (!Schema::hasColumn('master_discount_details', 'did_valid_for_return')) {
                $table->boolean('did_valid_for_return')->default(true)->after('did_cascade');
            }
            if (!Schema::hasColumn('master_discount_details', 'did_valid_for_sales')) {
                $table->boolean('did_valid_for_sales')->default(true)->after('did_valid_for_return');
            }
            if (!Schema::hasColumn('master_discount_details', 'source_file')) {
                $table->string('source_file')->nullable()->after('did_valid_for_sales');
            }
            if (!Schema::hasColumn('master_discount_details', 'saved_at')) {
                $table->timestamp('saved_at')->nullable()->after('source_file');
            }
        });

        // 3. master_discount_detail_products
        Schema::table('master_discount_detail_products', function (Blueprint $table) {
            if (!Schema::hasColumn('master_discount_detail_products', 'source_file')) {
                $table->string('source_file')->nullable();
            }
            if (!Schema::hasColumn('master_discount_detail_products', 'saved_at')) {
                $table->timestamp('saved_at')->nullable();
            }
        });

        // 4. master_discount_detail_routes
        Schema::table('master_discount_detail_routes', function (Blueprint $table) {
            if (!Schema::hasColumn('master_discount_detail_routes', 'source_file')) {
                $table->string('source_file')->nullable();
            }
            if (!Schema::hasColumn('master_discount_detail_routes', 'saved_at')) {
                $table->timestamp('saved_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // 
    }
};
