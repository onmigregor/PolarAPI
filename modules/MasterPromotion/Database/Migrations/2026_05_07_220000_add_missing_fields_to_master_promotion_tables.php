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
        // 1. master_promotion_details
        Schema::table('master_promotion_details', function (Blueprint $table) {
            $table->integer('pdl_order')->nullable()->after('unt_code_required');
            $table->boolean('pdl_scalable')->default(false)->after('pdl_order');
            $table->boolean('pdl_accumulable')->default(false)->after('pdl_scalable');
        });

        // 2. master_promotion_detail_products
        Schema::table('master_promotion_detail_products', function (Blueprint $table) {
            $table->boolean('prp_valid_for_base_percentage')->default(false)->after('prp_free');
            $table->boolean('prp_quantity')->default(false)->after('prp_valid_for_base_percentage');
            $table->decimal('prp_quantity2', 15, 3)->nullable()->after('prp_quantity1');
            $table->decimal('prp_quantity3', 15, 3)->nullable()->after('prp_quantity2');
            $table->decimal('prp_quantity4', 15, 3)->nullable()->after('prp_quantity3');
            $table->decimal('prp_quantity5', 15, 3)->nullable()->after('prp_quantity4');
            
            $table->decimal('prp_min_percentage3', 15, 3)->nullable()->after('prp_min_percentage2');
            $table->decimal('prp_min_percentage4', 15, 3)->nullable()->after('prp_min_percentage3');
            $table->decimal('prp_min_percentage5', 15, 3)->nullable()->after('prp_min_percentage4');
            
            $table->decimal('prp_max_percentage2', 15, 3)->nullable()->after('prp_min_percentage5');
            $table->decimal('prp_max_percentage3', 15, 3)->nullable()->after('prp_max_percentage2');
            $table->decimal('prp_max_percentage4', 15, 3)->nullable()->after('prp_max_percentage3');
            $table->decimal('prp_max_percentage5', 15, 3)->nullable()->after('prp_max_percentage4');
            
            $table->decimal('prp_min_free2', 15, 3)->nullable()->after('prp_min_free1');
            $table->decimal('prp_min_free3', 15, 3)->nullable()->after('prp_min_free2');
            $table->decimal('prp_min_free4', 15, 3)->nullable()->after('prp_min_free3');
            $table->decimal('prp_min_free5', 15, 3)->nullable()->after('prp_min_free4');
            
            $table->decimal('prp_max_free1', 15, 3)->nullable()->after('prp_min_free5');
            $table->decimal('prp_max_free2', 15, 3)->nullable()->after('prp_max_free1');
            $table->decimal('prp_max_free3', 15, 3)->nullable()->after('prp_max_free2');
            $table->decimal('prp_max_free4', 15, 3)->nullable()->after('prp_max_free3');
            
            $table->string('unt_code_free', 50)->nullable()->after('prp_max_free4');
        });

        // 3. master_promotion_routes
        Schema::table('master_promotion_routes', function (Blueprint $table) {
            $table->string('extended_fields', 500)->nullable()->after('prm_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_promotion_details', function (Blueprint $table) {
            $table->dropColumn(['pdl_order', 'pdl_scalable', 'pdl_accumulable']);
        });

        Schema::table('master_promotion_detail_products', function (Blueprint $table) {
            $table->dropColumn([
                'prp_valid_for_base_percentage', 'prp_quantity', 'prp_quantity2', 'prp_quantity3', 
                'prp_quantity4', 'prp_quantity5', 'prp_min_percentage3', 'prp_min_percentage4', 
                'prp_min_percentage5', 'prp_max_percentage2', 'prp_max_percentage3', 
                'prp_max_percentage4', 'prp_max_percentage5', 'prp_min_free2', 'prp_min_free3', 
                'prp_min_free4', 'prp_min_free5', 'prp_max_free1', 'prp_max_free2', 
                'prp_max_free3', 'prp_max_free4', 'unt_code_free'
            ]);
        });

        Schema::table('master_promotion_routes', function (Blueprint $table) {
            $table->dropColumn('extended_fields');
        });
    }
};
