<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_pools', function (Blueprint $table) {
            $table->id();
            $table->string('pol_code')->unique();
            $table->string('pol_name')->nullable();
            $table->boolean('pol_customer_search')->default(false);
            $table->boolean('deleted')->default(false);
            $table->timestamps();
        });

        Schema::create('master_customer_pools', function (Blueprint $table) {
            $table->id();
            $table->string('cus_code')->index();
            $table->string('pol_code')->index();
            $table->boolean('deleted')->default(false);
            $table->timestamps();
            
            $table->unique(['cus_code', 'pol_code'], 'idx_cus_pol_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_customer_pools');
        Schema::dropIfExists('master_pools');
    }
};
