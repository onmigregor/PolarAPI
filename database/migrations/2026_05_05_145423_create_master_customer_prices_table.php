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
        Schema::create('master_customer_prices', function (Blueprint $table) {
            $table->id();
            $table->string('rot_code', 50)->nullable();
            $table->string('cus_code', 50)->nullable();
            $table->string('prc_code', 50)->nullable();
            $table->boolean('csp_for_sale')->default(0);
            $table->boolean('csp_for_return')->default(0);
            $table->timestamps();
            
            $table->unique(['rot_code', 'cus_code', 'prc_code'], 'master_customer_prices_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_customer_prices');
    }
};
