<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_adc_polar', function (Blueprint $table) {
            $table->id();
            $table->string('fq_redi')->nullable();
            $table->string('cus_code')->nullable()->index(); // id_customer de Admin
            $table->string('marca')->nullable();
            $table->string('no_serie')->nullable()->unique(); // Unique para upsert masivo
            $table->string('no_serial')->nullable();
            $table->string('no_activo')->nullable();
            $table->string('empresa')->nullable();
            $table->string('estado')->nullable();
            $table->string('tipo_activo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_adc_polar');
    }
};
