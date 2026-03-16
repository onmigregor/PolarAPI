<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_product_families', function (Blueprint $table) {
            $table->id();
            $table->string('cl1_code', 18)->unique();
            $table->string('cl1_name', 40);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_product_families');
    }
};
