<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Borrar la tabla antigua de forma explícita
        // Desactivamos llaves foráneas por si acaso (aunque no debería haber)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::dropIfExists('master_adc_polar');
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        // 2. Crear la nueva tabla maestra con la estructura final
        if (!Schema::hasTable('master_adc_datos_polar')) {
            Schema::create('master_adc_datos_polar', function (Blueprint $table) {
                $table->id('id_adc');
                $table->string('cus_code');
                $table->string('serial')->unique();
                $table->string('modelo')->nullable();
                $table->string('condicion')->default('FUNCIONAL');
                $table->text('descripcion')->nullable();
                $table->tinyInteger('es_propio')->default(0);
                $table->string('pertenece_a', 60)->default('POLAR');
                $table->string('imagen', 30)->default('');
                $table->string('ubicacion_imagen', 100)->default('');
                $table->timestamps();
                
                $table->index('cus_code');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_adc_datos_polar');
    }
};
