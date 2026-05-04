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
            $table->string('address_street1', 255)->nullable()->after('name');
            $table->string('address_street2', 255)->nullable()->after('address_street1');
            $table->string('address_street3', 255)->nullable()->after('address_street2');
            $table->string('subregion_code', 50)->nullable()->after('address_street3');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_routes', function (Blueprint $table) {
            $table->dropColumn(['address_street1', 'address_street2', 'address_street3', 'subregion_code']);
        });
    }
};
