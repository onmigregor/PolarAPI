<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_customer_routes', function (Blueprint $table) {
            $table->string('rot_code', 50)->nullable()->after('id');
            $table->string('cus_code', 50)->nullable()->after('rot_code');
            $table->string('fre_code', 20)->nullable()->after('cus_code');

            // Días de la semana (frecuencia de visita)
            $table->string('ctr_monday', 3)->nullable()->after('fre_code');
            $table->string('ctr_tuesday', 3)->nullable()->after('ctr_monday');
            $table->string('ctr_wednesday', 3)->nullable()->after('ctr_tuesday');
            $table->string('ctr_thursday', 3)->nullable()->after('ctr_wednesday');
            $table->string('ctr_friday', 3)->nullable()->after('ctr_thursday');
            $table->string('ctr_saturday', 3)->nullable()->after('ctr_friday');
            $table->string('ctr_sunday', 3)->nullable()->after('ctr_saturday');

            // Campos de contacto, balance y precio de ruta
            $table->string('ctr_contact_person', 100)->nullable()->after('ctr_sunday');
            $table->string('ctr_balance', 50)->nullable()->after('ctr_contact_person');
            $table->string('prc_code_for_sale', 20)->nullable()->after('ctr_balance');
            $table->string('con_code', 50)->nullable()->after('prc_code_for_sale');

            $table->unique(['rot_code', 'cus_code'], 'master_customer_routes_unique');
        });
    }

    public function down(): void
    {
        Schema::table('master_customer_routes', function (Blueprint $table) {
            $table->dropUnique('master_customer_routes_unique');
            $table->dropColumn([
                'rot_code', 'cus_code', 'fre_code',
                'ctr_monday', 'ctr_tuesday', 'ctr_wednesday', 'ctr_thursday',
                'ctr_friday', 'ctr_saturday', 'ctr_sunday',
                'ctr_contact_person', 'ctr_balance', 'prc_code_for_sale', 'con_code',
            ]);
        });
    }
};
