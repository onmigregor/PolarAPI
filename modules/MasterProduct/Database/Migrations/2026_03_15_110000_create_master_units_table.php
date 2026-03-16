<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_units', function (Blueprint $table) {
            $table->id();
            $table->string('unt_code', 3)->unique()->comment('Unidad de medida base');
            $table->string('unt_name', 10)->comment('Texto para la unidad de medida');
            $table->string('unt_nick', 3)->nullable()->comment('Unidad medida alternativa');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_units');
    }
};
