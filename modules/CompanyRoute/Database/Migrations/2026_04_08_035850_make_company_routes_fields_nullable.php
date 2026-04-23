<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('company_routes', function (Blueprint $table) {
            $table->string('rif')->nullable()->change();
            $table->text('fiscal_address')->nullable()->change();
            $table->foreignId('region_id')->nullable()->change();
            $table->string('cep')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('company_routes', function (Blueprint $table) {
            $table->string('rif')->nullable(false)->change();
            $table->text('fiscal_address')->nullable(false)->change();
            $table->foreignId('region_id')->nullable(false)->change();
        });
    }
};
