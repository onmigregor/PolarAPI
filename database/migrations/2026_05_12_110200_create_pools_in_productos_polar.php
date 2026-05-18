<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This migration targets the 'productos_polar' connection (external staging DB).
     */
    public function up(): void
    {
        if (!Schema::connection('productos_polar')->hasTable('pools')) {
            Schema::connection('productos_polar')->create('pools', function (Blueprint $table) {
                $table->id();
                $table->string('pol_code')->unique();
                $table->string('pol_name')->nullable();
                $table->boolean('pol_customer_search')->default(false);
                $table->boolean('deleted')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::connection('productos_polar')->hasTable('customer_pools')) {
            Schema::connection('productos_polar')->create('customer_pools', function (Blueprint $table) {
                $table->id();
                $table->string('cus_code')->index();
                $table->string('pol_code')->index();
                $table->boolean('deleted')->default(false);
                $table->timestamps();
                
                $table->unique(['cus_code', 'pol_code'], 'idx_cus_pol_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('productos_polar')->dropIfExists('customer_pools');
        Schema::connection('productos_polar')->dropIfExists('pools');
    }
};
