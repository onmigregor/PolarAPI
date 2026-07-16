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
        Schema::create('company_routes_qa', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('cep')->nullable();
            $table->string('name');
            $table->string('address_street1')->nullable();
            $table->string('address_street2')->nullable();
            $table->string('address_street3')->nullable();
            $table->string('subregion_code', 50)->nullable();
            $table->string('sale_zone', 50)->nullable();
            $table->string('route_name')->nullable();
            $table->string('zone')->nullable();
            $table->string('rif')->nullable();
            $table->text('description')->nullable();
            $table->text('fiscal_address')->nullable();
            $table->foreignId('region_id')->nullable()->constrained('regions')->onDelete('set null');
            $table->string('db_name');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_available_to_sync')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique('code', 'company_routes_qa_code_unique');
            $table->unique('name', 'company_routes_qa_name_unique');
            $table->unique('db_name', 'company_routes_qa_db_name_unique');
            $table->index('rif', 'company_routes_qa_rif_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_routes_qa');
    }
};
