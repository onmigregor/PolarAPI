<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Índice para master_adc_polar
        $indexAdc = DB::select("SHOW INDEX FROM master_adc_polar WHERE Key_name = 'master_adc_polar_cus_code_index'");
        if (count($indexAdc) === 0) {
            Schema::table('master_adc_polar', function (Blueprint $table) {
                $table->index('cus_code');
            });
        }

        // Índice para master_client_polar
        $indexClient = DB::select("SHOW INDEX FROM master_client_polar WHERE Key_name = 'master_client_polar_cus_code_index'");
        if (count($indexClient) === 0) {
            Schema::table('master_client_polar', function (Blueprint $table) {
                $table->index('cus_code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::table('master_adc_polar', function (Blueprint $table) {
                $table->dropIndex(['cus_code']);
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('master_client_polar', function (Blueprint $table) {
                $table->dropIndex(['cus_code']);
            });
        } catch (\Exception $e) {}
    }
};
