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
        Schema::table('master_promotions', function (Blueprint $table) {
            $table->text('prm_extended_file')->nullable()->after('prm_valid_to_sale');
        });

        Schema::table('master_promotion_details', function (Blueprint $table) {
            $table->text('pdl_extended_file')->nullable()->after('pdl_accumulable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_promotions', function (Blueprint $table) {
            $table->dropColumn('prm_extended_file');
        });

        Schema::table('master_promotion_details', function (Blueprint $table) {
            $table->dropColumn('pdl_extended_file');
        });
    }
};
