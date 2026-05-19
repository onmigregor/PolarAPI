<?php

use Modules\MasterClient\Models\MasterClientType2;
use Modules\MasterClient\Actions\MasterClientBulkSyncAction;
use Illuminate\Support\Facades\DB;

// 1. Obtener una tipología (Type2) actual para la prueba
$originalBranch = MasterClientType2::first();
if (!$originalBranch) {
    echo "ERROR: No hay datos en master_clients_type2 para probar.\n";
    exit(1);
}

echo "=== ESTADO INICIAL ===\n";
echo "Código: {$originalBranch->tp2_code}\n";
echo "Nombre Original: {$originalBranch->tp2_name}\n";
echo "Última Actualización (updated_at): {$originalBranch->updated_at}\n\n";

// Guardamos el nombre original para restaurarlo después
$originalName = $originalBranch->tp2_name;
$testName = "TEST BRANCH RE-TYPED " . rand(100, 999);

// 2. Construir payload de simulación para actualizar el nombre de esta tipología
$branchesPayload = [
    [
        'tp2_code' => $originalBranch->tp2_code,
        'tp2_name' => $testName
    ]
];

// Payload de un cliente que pertenece a esa tipología para ver la propagación al tenant
$clientWithThisType = DB::table('master_clients')
    ->where('tp2_code', $originalBranch->tp2_code)
    ->first();

$dataPayload = [];
if ($clientWithThisType) {
    echo "Cliente de Prueba Asociado: {$clientWithThisType->cep} - {$clientWithThisType->cus_name}\n\n";
    $dataPayload[] = [
        'cus_code' => $clientWithThisType->cep,
        'cus_name' => $clientWithThisType->cus_name,
        'cus_business_name' => $clientWithThisType->cus_business_name,
        'cus_administrator' => '',
        'tp2_code' => $originalBranch->tp2_code,
        'tp3_code' => $clientWithThisType->tp3_code ?? '',
        'cus_tax_id1' => $clientWithThisType->cus_tax_id1,
        'address' => $clientWithThisType->cus_street1,
        'latitude' => $clientWithThisType->cus_latitude,
        'longitude' => $clientWithThisType->cus_longitude,
        'contact_person' => $clientWithThisType->cus_contact_person,
        'phone' => $clientWithThisType->cus_phone,
        'route_name' => $clientWithThisType->ruta,
        'days' => [
            'monday' => 1,
            'tuesday' => 0,
            'wednesday' => 0,
            'thursday' => 0,
            'friday' => 0,
            'saturday' => 0,
            'sunday' => 0,
        ]
    ];
} else {
    echo "AVISO: No se encontró un cliente maestro asociado a esta tipología para probar la propagación.\n\n";
}

// 3. Ejecutar Sincronización Masiva simulada en el HUB
echo "=== EJECUTANDO SIMULACIÓN DE SINCRONIZACIÓN ===\n";
$action = app(MasterClientBulkSyncAction::class);
$result = $action->execute(
    $dataPayload,
    $branchesPayload // Enviamos la tipología con el nombre cambiado
);

echo "Resultado del Sync: " . ($result['branches_synced'] > 0 ? "ÉXITO" : "FALLÓ") . "\n\n";

// 4. Recargar el registro de la base de datos para validar
$updatedBranch = MasterClientType2::where('tp2_code', $originalBranch->tp2_code)->first();

echo "=== ESTADO POST-SINCRONIZACIÓN ===\n";
echo "Código: {$updatedBranch->tp2_code}\n";
echo "Nuevo Nombre en DB: {$updatedBranch->tp2_name}\n";
echo "Nuevo updated_at: {$updatedBranch->updated_at}\n";

// Validaciones estrictas
$nameChanged = ($updatedBranch->tp2_name === $testName);
$updatedAtChanged = ($updatedBranch->updated_at->gt($originalBranch->updated_at));

echo "¿Se modificó el Nombre correctamente?: " . ($nameChanged ? "SÍ (PASÓ)" : "NO (FALLÓ)") . "\n";
echo "¿Se actualizó el campo updated_at?: " . ($updatedAtChanged ? "SÍ (PASÓ)" : "NO (FALLÓ)") . "\n";

// 5. Verificar propagación al Tenant (si aplica)
if ($clientWithThisType) {
    // Buscar la base de datos del Tenant de la ruta
    $companyRoute = DB::table('company_routes')
        ->where('code', $clientWithThisType->ruta)
        ->first();
        
    if ($companyRoute) {
        // Consultar directamente al Tenant
        Config::set('database.connections.tenant.database', $companyRoute->db_name);
        DB::purge('tenant');
        
        $tenantClient = DB::connection('tenant')->table('clientes')
            ->where('cep', $clientWithThisType->cep)
            ->first();
            
        if ($tenantClient) {
            echo "TipoCliente en Base de Datos Tenant ({$companyRoute->db_name}): '{$tenantClient->TipoCliente}'\n";
            echo "¿El Tenant recibió el nuevo nombre de Tipología?: " . ($tenantClient->TipoCliente === $testName ? "SÍ (PASÓ)" : "NO (FALLÓ)") . "\n";
        }
    }
}

// 6. Restaurar nombre original
$action->execute(
    [],
    [
        [
            'tp2_code' => $originalBranch->tp2_code,
            'tp2_name' => $originalName
        ]
    ]
);
echo "\n=== RESTAURADO ESTADO ORIGINAL ===\n";
