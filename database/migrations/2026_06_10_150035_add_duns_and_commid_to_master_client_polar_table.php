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
        Schema::table('master_client_polar', function (Blueprint $table) {
            $table->string('cus_duns', 50)->nullable()->after('cus_business_name');
            $table->string('cus_comm_id', 50)->nullable()->after('cus_duns');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_client_polar', function (Blueprint $table) {
            $table->dropColumn(['cus_duns', 'cus_comm_id']);
        });
    }
};
