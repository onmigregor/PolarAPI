<?php

namespace Modules\MasterCompany\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\MasterCompany\Actions\SyncMasterCompaniesAction;

class MasterCompanyController extends Controller
{
    public function syncFromAdmin(SyncMasterCompaniesAction $action)
    {
        try {
            $results = $action->execute();
            return response()->json([
                'success' => true,
                'message' => 'Sincronización de Companies completada con éxito',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar Companies: ' . $e->getMessage()
            ], 500);
        }
    }
}
