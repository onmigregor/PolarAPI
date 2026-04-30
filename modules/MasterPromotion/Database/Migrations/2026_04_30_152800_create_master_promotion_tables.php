<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Cabeceras
        Schema::create('master_promotions', function (Blueprint $table) {
            $table->string('prm_code', 200)->primary();
            $table->string('prm_name')->nullable();
            $table->string('prm_can_be_disabled', 10)->nullable();
            $table->string('prm_enabled_value_on', 10)->nullable();
            $table->string('prm_valid_to_sale', 10)->nullable();
            $table->timestamps();
        });

        // 2. Detalles (Enrutamiento)
        Schema::create('master_promotion_details', function (Blueprint $table) {
            $table->string('pdl_code', 200)->primary();
            $table->string('prm_code', 200)->nullable();
            $table->string('pdl_name')->nullable();
            $table->date('pdl_since')->nullable();
            $table->dateTime('pdl_until')->nullable();
            $table->string('cus_code', 50)->nullable();
            $table->string('rot_code', 50)->nullable();
            $table->string('tp3code', 50)->nullable();
            $table->decimal('pdl_minimum', 15, 3)->nullable();
            $table->string('unt_code_required', 50)->nullable();
            $table->timestamps();

            $table->index('prm_code');
        });

        // 3. Reglas de Productos
        Schema::create('master_promotion_detail_products', function (Blueprint $table) {
            $table->string('prp_code', 200)->primary();
            $table->string('pdl_code', 200)->nullable();
            $table->string('prm_code', 200)->nullable();
            $table->string('pro_code', 50)->nullable();
            $table->string('unt_code', 10)->nullable();
            $table->string('cl1code', 50)->nullable();
            $table->string('cl2code', 50)->nullable();
            $table->string('cl3code', 50)->nullable();
            $table->string('cl4code', 50)->nullable();
            $table->string('prp_required', 10)->nullable();
            $table->string('prp_free', 10)->nullable();
            $table->decimal('prp_quantity1', 15, 3)->nullable();
            $table->decimal('prp_min_percentage1', 15, 3)->nullable();
            $table->decimal('prp_min_percentage2', 15, 3)->nullable();
            $table->decimal('prp_min_free1', 15, 3)->nullable();
            $table->timestamps();

            $table->index('pdl_code');
            $table->index('prm_code');
        });

        // 4. Segmentación por Rutas
        Schema::create('master_promotion_routes', function (Blueprint $table) {
            $table->id();
            $table->string('rot_code', 50);
            $table->string('prm_code', 200);
            $table->timestamps();

            $table->index('prm_code');
        });

        // 5. Segmentación por Equipos
        Schema::create('master_promotion_teams', function (Blueprint $table) {
            $table->id();
            $table->string('tea_code', 50);
            $table->string('prm_code', 200);
            $table->timestamps();

            $table->index('prm_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_promotion_teams');
        Schema::dropIfExists('master_promotion_routes');
        Schema::dropIfExists('master_promotion_detail_products');
        Schema::dropIfExists('master_promotion_details');
        Schema::dropIfExists('master_promotions');
    }
};
