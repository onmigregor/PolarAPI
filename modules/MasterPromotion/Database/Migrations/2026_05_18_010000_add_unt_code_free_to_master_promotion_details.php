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
        Schema::table('master_promotion_details', function (Blueprint $table) {
            if (!Schema::hasColumn('master_promotion_details', 'unt_code_free')) {
                $table->string('unt_code_free', 50)->nullable()->after('unt_code_required');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_promotion_details', function (Blueprint $table) {
            if (Schema::hasColumn('master_promotion_details', 'unt_code_free')) {
                $table->dropColumn('unt_code_free');
            }
        });
    }
};
