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
        // 1. Crear tabla master_territories
        if (!Schema::hasTable('master_territories')) {
            Schema::create('master_territories', function (Blueprint $table) {
                $table->string('code', 50)->primary()->comment('Código FQ del territorio');
                $table->string('name', 150)->comment('Nombre descriptivo del territorio');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. Crear tabla master_sale_zones
        if (!Schema::hasTable('master_sale_zones')) {
            Schema::create('master_sale_zones', function (Blueprint $table) {
                $table->string('code', 50)->primary()->comment('Código de la zona de venta');
                $table->string('name', 150)->comment('Nombre descriptivo de la zona');
                $table->string('territory_code', 50)->nullable()->comment('Relación con master_territories');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 3. Pre-poblar master_territories y master_sale_zones con los datos existentes en company_routes para evitar error FK 1452
        if (Schema::hasTable('company_routes')) {
            try {
                DB::statement("
                    INSERT IGNORE INTO master_territories (code, name, created_at, updated_at)
                    SELECT DISTINCT subregion_code, subregion_code, NOW(), NOW()
                    FROM company_routes
                    WHERE subregion_code IS NOT NULL AND TRIM(subregion_code) != ''
                ");

                DB::statement("
                    INSERT IGNORE INTO master_sale_zones (code, name, territory_code, created_at, updated_at)
                    SELECT DISTINCT sale_zone, sale_zone, subregion_code, NOW(), NOW()
                    FROM company_routes
                    WHERE sale_zone IS NOT NULL AND TRIM(sale_zone) != ''
                ");
            } catch (\Throwable $e) {}
        }

        // Foreign Key en master_sale_zones -> master_territories
        try {
            DB::statement("ALTER TABLE master_sale_zones ADD CONSTRAINT fk_master_sale_zones_territory FOREIGN KEY (territory_code) REFERENCES master_territories(code) ON DELETE SET NULL ON UPDATE CASCADE;");
        } catch (\Throwable $e) {}

        // 4. Agregar índices y Llaves Foráneas aisladas de forma segura en company_routes
        if (Schema::hasTable('company_routes')) {
            try {
                DB::statement("ALTER TABLE company_routes ADD INDEX idx_company_routes_subregion (subregion_code);");
            } catch (\Throwable $e) {}

            try {
                DB::statement("ALTER TABLE company_routes ADD INDEX idx_company_routes_sale_zone (sale_zone);");
            } catch (\Throwable $e) {}

            try {
                DB::statement("ALTER TABLE company_routes ADD INDEX idx_territorio_zona_composite (subregion_code, sale_zone);");
            } catch (\Throwable $e) {}

            try {
                DB::statement("ALTER TABLE company_routes ADD CONSTRAINT fk_routes_subregion FOREIGN KEY (subregion_code) REFERENCES master_territories(code) ON DELETE SET NULL ON UPDATE CASCADE;");
            } catch (\Throwable $e) {}

            try {
                DB::statement("ALTER TABLE company_routes ADD CONSTRAINT fk_routes_sale_zone FOREIGN KEY (sale_zone) REFERENCES master_sale_zones(code) ON DELETE SET NULL ON UPDATE CASCADE;");
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('company_routes')) {
            try { DB::statement("ALTER TABLE company_routes DROP FOREIGN KEY fk_routes_subregion;"); } catch (\Throwable $e) {}
            try { DB::statement("ALTER TABLE company_routes DROP FOREIGN KEY fk_routes_sale_zone;"); } catch (\Throwable $e) {}
            try { DB::statement("ALTER TABLE company_routes DROP INDEX idx_company_routes_subregion;"); } catch (\Throwable $e) {}
            try { DB::statement("ALTER TABLE company_routes DROP INDEX idx_company_routes_sale_zone;"); } catch (\Throwable $e) {}
            try { DB::statement("ALTER TABLE company_routes DROP INDEX idx_territorio_zona_composite;"); } catch (\Throwable $e) {}
        }

        try { DB::statement("ALTER TABLE master_sale_zones DROP FOREIGN KEY fk_master_sale_zones_territory;"); } catch (\Throwable $e) {}

        Schema::dropIfExists('master_sale_zones');
        Schema::dropIfExists('master_territories');
    }
};
