<?php

namespace Modules\MasterClient\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class EnsureCustomerPoolTablesExistAction
{
    /**
     * Asegura que las tablas de pools existan en la conexión de base de datos actual (Tenant).
     */
    public function execute(string $connection = 'tenant'): void
    {
        if (!Schema::connection($connection)->hasTable('pools')) {
            Schema::connection($connection)->create('pools', function (Blueprint $table) {
                $table->id();
                $table->string('pol_code', 50)->unique();
                $table->string('pol_name', 255)->nullable();
                $table->boolean('pol_customer_search')->default(false);
                $table->boolean('deleted')->default(false);
                $table->timestamps();
            });
            Log::info("Tabla 'pools' creada en conexión: {$connection}");
        }

        if (!Schema::connection($connection)->hasTable('customer_pools')) {
            Schema::connection($connection)->create('customer_pools', function (Blueprint $table) {
                $table->id();
                $table->string('cus_code', 20)->index();
                $table->string('pol_code', 50)->index();
                $table->boolean('deleted')->default(false);
                $table->timestamps();
                $table->unique(['cus_code', 'pol_code'], 'idx_cus_pol_unique');
            });
            Log::info("Tabla 'customer_pools' creada en conexión: {$connection}");
        }
    }
}
