<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('master_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_route_id')->constrained('company_routes')->cascadeOnDelete();
            $table->unsignedBigInteger('external_id')->comment('IdCliente from tenant clientes table');
            $table->string('cliente', 255);
            $table->string('ruta', 50)->nullable();
            $table->timestamps();

            $table->unique(['company_route_id', 'external_id']);
            $table->index('company_route_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_clients');
    }
};
