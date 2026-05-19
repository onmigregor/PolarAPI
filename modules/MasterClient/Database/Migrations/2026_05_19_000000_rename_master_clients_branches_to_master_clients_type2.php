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
        if (Schema::hasTable('master_clients_branches') && !Schema::hasTable('master_clients_type2')) {
            Schema::rename('master_clients_branches', 'master_clients_type2');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('master_clients_type2') && !Schema::hasTable('master_clients_branches')) {
            Schema::rename('master_clients_type2', 'master_clients_branches');
        }
    }
};
