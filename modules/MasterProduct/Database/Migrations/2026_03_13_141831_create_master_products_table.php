<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * master_products already exists — only adding the two new category linkage columns.
     */
    public function up(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->string('cl2_code', 18)->nullable()->after('brand');
            $table->string('cl3_code', 25)->nullable()->after('cl2_code');

            $table->index('cl2_code');
            $table->index('cl3_code');
        });
    }

    public function down(): void
    {
        Schema::table('master_products', function (Blueprint $table) {
            $table->dropIndex(['cl2_code']);
            $table->dropIndex(['cl3_code']);
            $table->dropColumn(['cl2_code', 'cl3_code']);
        });
    }
};
