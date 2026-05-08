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
        Schema::create('master_company_branches', function (Blueprint $table) {
            $table->id();
            $table->string('brc_code', 20)->unique();
            $table->string('brc_name', 200);
            $table->string('brc_general_header1', 255)->nullable();
            $table->string('reg_code', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('master_company_login_branches', function (Blueprint $table) {
            $table->id();
            $table->string('lgn_code', 50);
            $table->string('brc_code', 20);
            $table->timestamps();
            
            $table->index(['lgn_code', 'brc_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_company_login_branches');
        Schema::dropIfExists('master_company_branches');
    }
};
