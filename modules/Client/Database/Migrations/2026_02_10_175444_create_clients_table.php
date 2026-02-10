<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            // Definir las columnas
            $table->string('code')->unique();
            $table->string('name')->unique();
            $table->string('rif')->index();
            $table->text('description')->nullable();
            $table->text('fiscal_address');
            $table->foreignId('region_id')->constrained('regions');
            $table->string('db_name')->unique();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('clients');
    }
};
