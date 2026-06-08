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
            $table->string('cus_code', 20)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_client_polar', function (Blueprint $table) {
            $table->string('cus_code', 20)->nullable(false)->change();
        });
    }
};
