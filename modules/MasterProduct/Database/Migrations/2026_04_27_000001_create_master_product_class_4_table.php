<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_product_class_4', function (Blueprint $table) {
            $table->id();
            $table->string('cl4_code', 50)->unique();
            $table->string('cl3_code', 25)->nullable();
            $table->string('cl4_name', 150);
            $table->string('brand_code', 20)->nullable();
            $table->string('segment_code', 10)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('cl3_code');
            $table->index('segment_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_product_class_4');
    }
};
