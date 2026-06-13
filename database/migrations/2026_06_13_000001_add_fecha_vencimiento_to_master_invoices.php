<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_invoices') && !Schema::hasColumn('master_invoices', 'fecha_vencimiento')) {
            Schema::table('master_invoices', function (Blueprint $table) {
                $table->string('fecha_vencimiento', 50)->nullable()->after('fecha_creacion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('master_invoices') && Schema::hasColumn('master_invoices', 'fecha_vencimiento')) {
            Schema::table('master_invoices', function (Blueprint $table) {
                $table->dropColumn('fecha_vencimiento');
            });
        }
    }
};
