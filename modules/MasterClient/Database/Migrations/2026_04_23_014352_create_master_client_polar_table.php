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
        Schema::create('master_client_polar', function (Blueprint $table) {
            $table->id();
            $table->string('cus_code', 20)->unique();
            $table->string('cus_name', 100)->nullable();
            $table->string('cus_business_name', 100)->nullable();
            $table->string('cus_administrator', 50)->nullable();
            $table->foreignId('company_route_id')->nullable()->constrained('company_routes')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_client_polar');
    }
};
