<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('master_discounts', function (Blueprint $table) {
            $table->string('dis_code', 50)->primary();
            $table->string('dis_name', 100);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('master_discount_details', function (Blueprint $table) {
            $table->string('did_code', 50)->primary();
            $table->string('dis_code', 50);
            $table->string('did_name', 100);
            $table->string('rot_code_customer', 50)->nullable();
            $table->string('cus_code', 50)->nullable();
            $table->date('did_since')->nullable();
            $table->date('did_until')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('master_discount_detail_products', function (Blueprint $table) {
            $table->string('dlp_code', 50)->primary();
            $table->string('dis_code', 50);
            $table->string('did_code', 50);
            $table->string('pro_code', 50);
            $table->string('unt_code', 50);

            $table->string('dlp_required')->nullable();
            $table->decimal('dlp_discount', 15, 4)->nullable();
            $table->decimal('dlp_discount_percentage', 15, 4)->nullable();
            $table->decimal('dlp_discount_amount', 15, 4)->nullable();

            $table->decimal('dlp_required_quantity', 15, 4)->nullable();
            $table->decimal('dlp_required_quantity_amount', 15, 4)->nullable();
            $table->string('dlp_base_from_taken_for_discou')->nullable();
            $table->string('dlp_pallet_discount')->nullable();
            $table->decimal('dlp_minimum', 15, 4)->nullable();

            for ($i = 1; $i <= 5; $i++) {
                $table->decimal("dlp_quantity{$i}", 15, 4)->nullable();
                $table->decimal("dlp_min_discount{$i}", 15, 4)->nullable();
            }

            for ($i = 1; $i <= 6; $i++) {
                $table->decimal("dlp_max_discount{$i}", 15, 4)->nullable();
            }

            $table->decimal('dlp_global_discount_amount', 15, 4)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('master_discount_detail_routes', function (Blueprint $table) {
            $table->id();
            $table->string('rot_code', 50);
            $table->string('dis_code', 50);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['rot_code', 'dis_code'], 'uniq_master_rot_dis');
        });
    }

    public function down()
    {
        Schema::dropIfExists('master_discount_detail_routes');
        Schema::dropIfExists('master_discount_detail_products');
        Schema::dropIfExists('master_discount_details');
        Schema::dropIfExists('master_discounts');
    }
};
