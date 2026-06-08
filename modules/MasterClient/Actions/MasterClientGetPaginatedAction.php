<?php
declare(strict_types=1);

namespace Modules\MasterClient\Actions;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\MasterClient\Models\MasterClientPolar;
use Modules\CompanyRoute\Models\CompanyRoute;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class MasterClientGetPaginatedAction
{
    public function execute(array $filters, ?int $perPage = null): LengthAwarePaginator
    {
        $limit = $perPage ?? (int)config('apiconfig.pagination.per_page', 10);
        $query = MasterClientPolar::query()->with('companyRoute');

        // Filtro de Búsqueda General (Código, Nombre, Razón Social)
        $query->when($filters['query'] ?? null, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('cus_code', 'like', "%{$search}%")
                    ->orWhere('cus_name', 'like', "%{$search}%")
                    ->orWhere('cus_business_name', 'like', "%{$search}%");
            });
        });

        // Filtro Sin Código CEP
        if (isset($filters['has_cep'])) {
            $hasCep = filter_var($filters['has_cep'], FILTER_VALIDATE_BOOLEAN);
            if ($hasCep) {
                $query->where(function ($q) {
                    $q->whereNull('cus_code')->orWhere('cus_code', '');
                });
            }
        }

        // Obtener Tenants Activos para filtros dependientes de BD
        $activeTenants = CompanyRoute::where('is_active', true)->whereNotNull('db_name')->get();

        // Filtro por Clase 2 (TipoCliente)
        if (!empty($filters['tp2_code'])) {
            $matchingCeps = [];
            foreach ($activeTenants as $tenant) {
                try {
                    Config::set('database.connections.tenant.database', $tenant->db_name);
                    DB::purge('tenant');
                    $ceps = DB::connection('tenant')->table('clientes')
                        ->where('TipoCliente', $filters['tp2_code'])
                        ->pluck('cep')
                        ->toArray();
                    $matchingCeps = array_merge($matchingCeps, $ceps);
                } catch (\Exception $e) {
                    // Ignore DB errors
                }
            }
            $query->whereIn('cus_code', array_unique($matchingCeps));
        }

        // Filtro por Clase 3 (segmento)
        if (!empty($filters['cit_code'])) {
            $matchingCeps = [];
            foreach ($activeTenants as $tenant) {
                try {
                    Config::set('database.connections.tenant.database', $tenant->db_name);
                    DB::purge('tenant');
                    $ceps = DB::connection('tenant')->table('clientes')
                        ->where('segmento', $filters['cit_code'])
                        ->pluck('cep')
                        ->toArray();
                    $matchingCeps = array_merge($matchingCeps, $ceps);
                } catch (\Exception $e) {
                    // Ignore DB errors
                }
            }
            $query->whereIn('cus_code', array_unique($matchingCeps));
        }

        $paginated = $query->orderBy('cus_name')->paginate($limit)->withQueryString();
        $items = $paginated->items();

        // Agrupar elementos de la página actual por Base de Datos de Tenant
        $tenantGroups = [];
        foreach ($items as $item) {
            $dbName = $item->companyRoute?->db_name;
            if ($dbName) {
                $tenantGroups[$dbName][] = $item;
            }
        }

        // Cargar detalles de clientes de forma masiva por cada Tenant
        foreach ($tenantGroups as $dbName => $groupItems) {
            try {
                Config::set('database.connections.tenant.database', $dbName);
                DB::purge('tenant');

                $ceps = array_map(fn($item) => $item->cus_code, $groupItems);

                $tenantClients = DB::connection('tenant')->table('clientes')
                    ->whereIn('cep', $ceps)
                    ->get()
                    ->keyBy('cep');

                foreach ($groupItems as $item) {
                    $tc = $tenantClients->get($item->cus_code);
                    if ($tc) {
                        $item->cep = $item->cus_code;
                        $item->cliente = $tc->Cliente;
                        $item->ruta = $tc->Ruta;
                        $item->cus_tax_id1 = $tc->RIF;
                        $item->tp1_code = $tc->tp1_code;
                        $item->tp2_code = $tc->TipoCliente;
                        $item->cit_code = $tc->segmento;
                        $item->cus_phone = $tc->TelefonoContacto;
                        $item->cus_email = $tc->email;
                    } else {
                        $this->setFallbackAttributes($item);
                    }
                }
            } catch (\Exception $e) {
                foreach ($groupItems as $item) {
                    $this->setFallbackAttributes($item);
                }
            }
        }

        // Establecer atributos por defecto para los que no tienen ruta/tenant
        foreach ($items as $item) {
            if (!$item->companyRoute?->db_name) {
                $this->setFallbackAttributes($item);
            }
        }

        return $paginated;
    }

    private function setFallbackAttributes($item): void
    {
        $item->cep = $item->cus_code;
        $item->cliente = $item->cus_business_name ?: $item->cus_name;
        $item->ruta = null;
        $item->cus_tax_id1 = null;
        $item->tp1_code = null;
        $item->tp2_code = null;
        $item->cit_code = null;
        $item->cus_phone = null;
        $item->cus_email = null;
    }
}
