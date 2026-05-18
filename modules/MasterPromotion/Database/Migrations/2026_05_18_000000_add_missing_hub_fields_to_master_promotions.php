<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. master_promotion_details
        Schema::table('master_promotion_details', function (Blueprint $table) {
            if (!Schema::hasColumn('master_promotion_details', 'tp1_code')) {
                $table->string('tp1_code', 50)->nullable()->after('cus_code');
            }
            if (!Schema::hasColumn('master_promotion_details', 'tp2_code')) {
                $table->string('tp2_code', 50)->nullable()->after('tp1_code');
            }
            if (!Schema::hasColumn('master_promotion_details', 'tp3_code')) {
                $table->string('tp3_code', 50)->nullable()->after('tp2_code');
            }
        });

        // 2. master_promotion_detail_products
        Schema::table('master_promotion_detail_products', function (Blueprint $table) {
            if (!Schema::hasColumn('master_promotion_detail_products', 'prp_max_percentage1')) {
                $table->decimal('prp_max_percentage1', 15, 3)->nullable()->after('prp_min_percentage5');
            }
            if (!Schema::hasColumn('master_promotion_detail_products', 'prp_max_free5')) {
                $table->decimal('prp_max_free5', 15, 3)->nullable()->after('prp_max_free4');
            }
        });
    }

    public function down(): void
    {
        Schema::table('master_promotion_details', function (Blueprint $table) {
            $table->dropColumn(['tp1_code', 'tp2_code', 'tp3_code']);
        });

        Schema::table('master_promotion_detail_products', function (Blueprint $table) {
            $table->dropColumn(['prp_max_percentage1', 'prp_max_free5']);
        });
    }
};
