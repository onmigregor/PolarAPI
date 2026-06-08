<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregar campos faltantes a la tabla master_client_polar
     * para unificar toda la información del cliente maestro.
     */
    public function up(): void
    {
        Schema::table('master_client_polar', function (Blueprint $table) {
            if (!Schema::hasColumn('master_client_polar', 'tp1_code')) {
                $table->string('tp1_code', 20)->nullable()->after('company_route_id');
            }
            if (!Schema::hasColumn('master_client_polar', 'tp2_code')) {
                $table->string('tp2_code', 20)->nullable()->after('tp1_code');
            }
            if (!Schema::hasColumn('master_client_polar', 'cit_code')) {
                $table->string('cit_code', 20)->nullable()->after('tp2_code');
            }
            if (!Schema::hasColumn('master_client_polar', 'cus_tax_id1')) {
                $table->string('cus_tax_id1', 50)->nullable()->after('cit_code');
            }
            if (!Schema::hasColumn('master_client_polar', 'cus_phone')) {
                $table->string('cus_phone', 50)->nullable()->after('cus_tax_id1');
            }
            if (!Schema::hasColumn('master_client_polar', 'cus_email')) {
                $table->string('cus_email', 255)->nullable()->after('cus_phone');
            }
            if (!Schema::hasColumn('master_client_polar', 'registered_at_tenant')) {
                $table->timestamp('registered_at_tenant')->nullable()->after('cus_email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_client_polar', function (Blueprint $table) {
            $table->dropColumn([
                'tp1_code',
                'tp2_code',
                'cit_code',
                'cus_tax_id1',
                'cus_phone',
                'cus_email',
                'registered_at_tenant',
            ]);
        });
    }
};
