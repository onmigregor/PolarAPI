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

        // Precargar territorios y logins para cruce de FQ
        $territories = DB::table('master_company_territories')->get()->keyBy('try_code');
        $logins = DB::table('master_company_logins')->get()->keyBy('lgn_code');

        // Filtro de Búsqueda General (Código, Nombre, Razón Social)
        $query->when($filters['query'] ?? null, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('cus_code', 'like', "%{$search}%")
                    ->orWhere('cus_name', 'like', "%{$search}%")
                    ->orWhere('cus_business_name', 'like', "%{$search}%");
            });
        });

        // Obtener Tenants Activos para filtros dependientes de BD
        $activeTenants = CompanyRoute::where('is_active', true)->whereNotNull('db_name')->get();

        // Filtro Sin Código CEP
        if (isset($filters['has_cep'])) {
            $hasCep = filter_var($filters['has_cep'], FILTER_VALIDATE_BOOLEAN);
            if ($hasCep) {
                $unlinkedClients = [];
                foreach ($activeTenants as $tenant) {
                    try {
                        Config::set('database.connections.tenant.database', $tenant->db_name);
                        DB::purge('tenant');

                        $cols = DB::connection('tenant')->select("SHOW COLUMNS FROM clientes");
                        $fields = array_column($cols, 'Field');
                        $hasBusinessName = in_array('cus_business_name', $fields);

                        $selectCols = ['IdCliente', 'cep', 'Cliente', 'Ruta', 'RIF', 'tp1_code', 'TipoCliente', 'segmento', 'TelefonoContacto', 'email', 'Direccion', 'latitud', 'longitud'];
                        if ($hasBusinessName) {
                            $selectCols[] = 'cus_business_name';
                        }

                        $records = DB::connection('tenant')->table('clientes')
                            ->select($selectCols)
                            ->where(function($q) {
                                $q->whereNull('cep')->orWhere('cep', '');
                            })
                            ->get();

                        foreach ($records as $r) {
                            $client = new \stdClass();
                            $client->id = $r->IdCliente;
                            $client->cus_code = null;
                            $client->cus_name = $r->Cliente;
                            $client->cus_business_name = $hasBusinessName ? ($r->cus_business_name ?? $r->Cliente) : $r->Cliente;
                            $client->company_route_id = $tenant->id;
                            $client->companyRoute = $tenant;
                            $client->created_at = null;
                            $client->updated_at = null;

                            $client->cep = null;
                            $client->cliente = $r->Cliente;
                            $client->ruta = $r->Ruta;
                            $client->cus_tax_id1 = $r->RIF;
                            $client->tp1_code = $r->tp1_code;
                            $client->tp2_code = $r->TipoCliente;
                            $client->cit_code = $r->segmento;
                            $client->cus_phone = $r->TelefonoContacto;
                            $client->cus_email = $r->email;
                            $client->direccion = $r->Direccion ?? null;
                            $client->latitud = $r->latitud ?? null;
                            $client->longitud = $r->longitud ?? null;

                            $unlinkedClients[] = $client;
                        }
                    } catch (\Exception $e) {
                        // Ignore tenant DB errors
                    }
                }

                // Filtrar en memoria si hay búsqueda por query
                if (!empty($filters['query'])) {
                    $search = strtolower($filters['query']);
                    $unlinkedClients = array_filter($unlinkedClients, function($c) use ($search) {
                        return str_contains(strtolower($c->cliente), $search) ||
                               str_contains(strtolower($c->cus_business_name), $search) ||
                               str_contains(strtolower((string)$c->id), $search);
                    });
                }

                $total = count($unlinkedClients);
                $page = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
                $slice = array_slice($unlinkedClients, ($page - 1) * $limit, $limit);
                
                return new \Illuminate\Pagination\LengthAwarePaginator($slice, $total, $limit, $page, [
                    'path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(),
                    'query' => request()->query()
                ]);
            }
        }

        // Filtro por Clase 1 (tp1_code)
        if (!empty($filters['tp1_code'])) {
            $matchingCeps = [];
            foreach ($activeTenants as $tenant) {
                try {
                    Config::set('database.connections.tenant.database', $tenant->db_name);
                    DB::purge('tenant');
                    $ceps = DB::connection('tenant')->table('clientes')
                        ->where('tp1_code', $filters['tp1_code'])
                        ->pluck('cep')
                        ->toArray();
                    $matchingCeps = array_merge($matchingCeps, $ceps);
                } catch (\Exception $e) {
                    // Ignore DB errors
                }
            }
            $query->whereIn('cus_code', array_unique($matchingCeps));
        }

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
                        $item->direccion = $tc->Direccion ?? null;
                        $item->latitud = $tc->latitud ?? null;
                        $item->longitud = $tc->longitud ?? null;
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

            // Mapeo de campos geográficos y de Franquicia (FQ)
            $routeCode = strtoupper($item->companyRoute?->code ?? ($item->ruta ?? ''));
            $tryCode = substr($routeCode, 0, 6);
            $territory = $territories->get($tryCode) ?? null;
            $lgnCode = $territory->lgn_code ?? null;
            $login = $lgnCode ? ($logins->get($lgnCode) ?? null) : null;

            $item->zona_venta = $tryCode;
            $item->oficina = $login->lgn_street1 ?? '';
            $item->territorio = $login->srg_code ?? '';
            $item->grupo_vendedor = $login->lgn_street2 ?? '';
            $item->codigo_fq = $lgnCode ?? '';
            $item->cedula_coordinador = ''; // Polar no provee este campo en los maestros
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
        $item->direccion = null;
        $item->latitud = null;
        $item->longitud = null;
    }
}
