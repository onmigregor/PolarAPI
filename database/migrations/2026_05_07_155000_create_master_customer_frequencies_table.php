<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_customer_frequencies', function (Blueprint $table) {
            $table->id();
            $table->string('fre_code', 20)->unique();
            $table->string('fre_name', 100)->nullable();
            $table->string('fre_week1', 10)->nullable();
            $table->string('fre_week2', 10)->nullable();
            $table->string('fre_week3', 10)->nullable();
            $table->string('fre_week4', 10)->nullable();
            $table->string('fre_customer', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_customer_frequencies');
    }
};
