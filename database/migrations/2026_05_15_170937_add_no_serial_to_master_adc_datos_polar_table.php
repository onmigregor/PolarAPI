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
        Schema::table('master_adc_datos_polar', function (Blueprint $table) {
            if (!Schema::hasColumn('master_adc_datos_polar', 'no_serial')) {
                $table->string('no_serial')->nullable()->after('no_activo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_adc_datos_polar', function (Blueprint $table) {
            $table->dropColumn('no_serial');
        });
    }
};
