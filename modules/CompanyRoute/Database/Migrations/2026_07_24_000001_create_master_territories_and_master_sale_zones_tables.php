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
        // 1. Crear tabla master_territories
        if (!Schema::hasTable('master_territories')) {
            Schema::create('master_territories', function (Blueprint $table) {
                $table->string('code', 50)->primary()->comment('Código FQ del territorio (ej: S02, Cerveceria)');
                $table->string('name', 150)->comment('Nombre descriptivo del territorio');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. Crear tabla master_sale_zones
        if (!Schema::hasTable('master_sale_zones')) {
            Schema::create('master_sale_zones', function (Blueprint $table) {
                $table->string('code', 50)->primary()->comment('Código de la zona de venta (ej: N016, N028)');
                $table->string('name', 150)->comment('Nombre descriptivo de la zona');
                $table->string('territory_code', 50)->nullable()->comment('Relación con master_territories');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('territory_code', 'fk_master_sale_zones_territory')
                    ->references('code')
                    ->on('master_territories')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            });
        }

        // 3. Agregar índices y Llaves Foráneas en company_routes
        if (Schema::hasTable('company_routes')) {
            Schema::table('company_routes', function (Blueprint $table) {
                // Agregar índices solo si no existen
                $table->index('subregion_code', 'idx_company_routes_subregion');
                $table->index('sale_zone', 'idx_company_routes_sale_zone');
                $table->index(['subregion_code', 'sale_zone'], 'idx_territorio_zona_composite');

                $table->foreign('subregion_code', 'fk_routes_subregion')
                    ->references('code')
                    ->on('master_territories')
                    ->onDelete('set null')
                    ->onUpdate('cascade');

                $table->foreign('sale_zone', 'fk_routes_sale_zone')
                    ->references('code')
                    ->on('master_sale_zones')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('company_routes')) {
            Schema::table('company_routes', function (Blueprint $table) {
                $table->dropForeign('fk_routes_subregion');
                $table->dropForeign('fk_routes_sale_zone');
                $table->dropIndex('idx_company_routes_subregion');
                $table->dropIndex('idx_company_routes_sale_zone');
                $table->dropIndex('idx_territorio_zona_composite');
            });
        }

        Schema::dropIfExists('master_sale_zones');
        Schema::dropIfExists('master_territories');
    }
};
