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
        Schema::create('master_company_logins', function (Blueprint $table) {
            $table->string('lgn_code', 20)->primary();
            $table->string('lgn_name', 100)->nullable();
            $table->string('brc_code', 10)->nullable();
            $table->string('lgn_phone', 50)->nullable();
            $table->string('lgn_street1', 150)->nullable();
            $table->string('lgn_street2', 150)->nullable();
            $table->string('lgn_street3', 150)->nullable();
            $table->string('srg_code', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('master_company_territories', function (Blueprint $table) {
            $table->string('try_code', 50)->primary();
            $table->string('brc_code', 10)->nullable();
            $table->string('lgn_code', 20)->nullable();
            $table->string('try_name', 150)->nullable();
            $table->string('try_email', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_company_territories');
        Schema::dropIfExists('master_company_logins');
    }
};
