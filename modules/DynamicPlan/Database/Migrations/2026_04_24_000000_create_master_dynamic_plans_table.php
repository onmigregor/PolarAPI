<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_dynamic_plans', function (Blueprint $table) {
            $table->id();
            $table->string('cod_fq')->unique();
            $table->double('meta_cerveceria')->default(0);
            $table->double('meta_maltin')->default(0);
            $table->double('meta_sangria')->default(0);
            $table->double('meta_pcv')->default(0);
            $table->double('meta_apc')->default(0);
            $table->double('meta_pomar')->default(0);
            $table->double('metas_pg')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_dynamic_plans');
    }
};
