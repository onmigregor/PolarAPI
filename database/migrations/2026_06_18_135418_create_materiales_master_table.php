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
        Schema::create('master_materiales', function (Blueprint $table) {
            $table->id();
            $table->string('se_comercializa', 50)->nullable();
            $table->string('nombre', 255)->nullable();
            $table->string('md', 50)->nullable();
            $table->string('material', 100)->nullable()->index();
            $table->string('ce', 50)->nullable();
            $table->string('untcode', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_materiales');
    }
};
