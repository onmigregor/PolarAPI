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
        Schema::table('company_routes', function (Blueprint $table) {
            $table->string('sale_zone', 50)->nullable()->after('subregion_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_routes', function (Blueprint $table) {
            $table->dropColumn('sale_zone');
        });
    }
};
