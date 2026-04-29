<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('master_generals', function (Blueprint $table) {
            $table->string('reaCode', 100)->primary();
            $table->string('reaName', 100);
            
            $table->boolean('reaNoVisit')->default(false);
            $table->boolean('reaNoSale')->default(false);
            $table->boolean('reaNoCollect')->default(false);
            $table->boolean('reaNoDelivery')->default(false);
            $table->boolean('reaNoReturnPickUp')->default(false);
            $table->boolean('reaDeliveryDifference')->default(false);
            $table->boolean('reaReturn')->default(false);
            $table->boolean('reaDamageReturn')->default(false);
            $table->boolean('reaNoInventory')->default(false);
            $table->decimal('reaPercentageAcknoledgment', 11, 2)->nullable();
            $table->boolean('reaStatus')->default(true);
            $table->boolean('reaAsset')->default(false);
            $table->boolean('reaBouncedCheck')->default(false);
            $table->boolean('reaNoCollectionArdocument')->default(false);
            $table->boolean('reaNoBarCodeReading')->default(false);
            $table->boolean('reaHeader')->nullable();
            $table->boolean('reaCancelInvoice')->default(false);
            $table->boolean('reaHos')->default(false);
            
            $table->boolean('deleted')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('master_generals');
    }
};
