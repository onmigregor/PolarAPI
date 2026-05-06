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
        Schema::table('master_clients', function (Blueprint $table) {
            if (!Schema::hasColumn('master_clients', 'con_code')) {
                $table->string('con_code')->nullable()->after('cus_email');
            }
            if (!Schema::hasColumn('master_clients', 'cus_credit_limit')) {
                $table->string('cus_credit_limit')->nullable()->after('con_code');
            }
            if (!Schema::hasColumn('master_clients', 'cus_balance')) {
                $table->string('cus_balance')->nullable()->after('cus_credit_limit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_clients', function (Blueprint $table) {
            $table->dropColumn(['con_code', 'cus_credit_limit', 'cus_balance']);
        });
    }
};
