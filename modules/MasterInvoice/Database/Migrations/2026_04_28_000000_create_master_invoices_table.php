<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('fq_redi', 50)->nullable();
            $table->string('fecha_creacion', 50)->nullable();
            $table->string('codigo_polar_negocio', 50)->nullable();
            $table->string('no_factura', 100)->nullable();
            $table->string('no_control', 100)->nullable();
            $table->string('zona_venta', 50)->nullable();
            $table->string('material', 100)->nullable();
            $table->decimal('cantidad', 12, 4)->nullable();
            $table->string('um', 20)->nullable();
            $table->decimal('precio', 15, 4)->nullable();
            $table->decimal('iva', 12, 4)->nullable();
            $table->decimal('descuento', 12, 4)->nullable();
            $table->decimal('otro_margen', 12, 4)->nullable();
            $table->decimal('envases', 12, 4)->nullable();
            $table->decimal('lisaea_unidad', 15, 4)->nullable();
            $table->decimal('tasa', 15, 4)->nullable();
            $table->timestamps();
            
            // Unique constraint to prevent duplicates on upsert
            $table->unique(['no_factura', 'material']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_invoices');
    }
};
