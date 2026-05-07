<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_clients', function (Blueprint $table) {
            $table->string('fre_week1', 10)->nullable();
            $table->string('fre_week2', 10)->nullable();
            $table->string('fre_week3', 10)->nullable();
            $table->string('fre_week4', 10)->nullable();
            $table->string('fre_customer', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('master_clients', function (Blueprint $table) {
            $table->dropColumn(['fre_week1', 'fre_week2', 'fre_week3', 'fre_week4', 'fre_customer']);
        });
    }
};
