<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('master_clients', function (Blueprint $table) {
            // CEP as requested (maps to cus_code)
            $table->string('cep', 20)->nullable()->unique()->after('external_id');
            
            // Polar Official Fields (Vitaminando la tabla)
            $table->string('cus_name', 100)->nullable()->after('cep');
            $table->string('cus_business_name', 100)->nullable()->after('cus_name');
            $table->string('cus_duns', 100)->nullable()->after('cus_business_name');
            $table->string('cus_comm_id', 100)->nullable()->after('cus_duns');
            $table->string('tp1_code', 20)->nullable()->after('cus_comm_id');
            $table->string('tp2_code', 20)->nullable()->after('tp1_code');
            $table->string('cit_code', 20)->nullable()->after('tp2_code');
            $table->string('txn_code', 20)->nullable()->after('cit_code');
            $table->string('cus_phone', 50)->nullable()->after('txn_code');
            $table->string('cus_fax', 50)->nullable()->after('cus_phone');
            $table->string('cus_street1', 100)->nullable()->after('cus_fax');
            $table->string('cus_street2', 100)->nullable()->after('cus_street1');
            $table->string('cus_street3', 100)->nullable()->after('cus_street2');
            $table->string('cus_tax_id1', 50)->nullable()->after('cus_street3');
            $table->string('brc_code', 50)->nullable()->after('cus_tax_id1');
            $table->string('cus_latitude', 50)->nullable()->after('brc_code');
            $table->string('cus_longitude', 50)->nullable()->after('cus_latitude');
            $table->string('prc_code_for_sale', 20)->nullable()->after('cus_longitude');
            $table->string('prc_code_for_return', 20)->nullable()->after('prc_code_for_sale');
            $table->string('cus_contact_person', 100)->nullable()->after('prc_code_for_return');
            $table->string('cus_email', 255)->nullable()->after('cus_contact_person');
            
            // Soft Deletes (if not already present)
            if (!Schema::hasColumn('master_clients', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down()
    {
        Schema::table('master_clients', function (Blueprint $table) {
            $table->dropColumn([
                'cep', 'cus_name', 'cus_business_name', 'cus_duns', 'cus_comm_id',
                'tp1_code', 'tp2_code', 'cit_code', 'txn_code', 'cus_phone',
                'cus_fax', 'cus_street1', 'cus_street2', 'cus_street3',
                'cus_tax_id1', 'brc_code', 'cus_latitude', 'cus_longitude',
                'prc_code_for_sale', 'prc_code_for_return', 'cus_contact_person',
                'cus_email'
            ]);
        });
    }
};
