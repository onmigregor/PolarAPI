<?php
declare(strict_types=1);

namespace Modules\MasterClient\Actions;

use Modules\MasterClient\Models\MasterClientPolar;
use Modules\CompanyRoute\Models\CompanyRoute;
use Modules\MasterClient\Http\Resources\MasterClientExportResource;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExportMasterClientsAction
{
    /**
     * Execute export query with filters and format output string.
     *
     * @param array $filters
     * @param string $format 'csv' or 'txt'
     * @return array [filename, content, contentType]
     */
    public function execute(array $filters, string $format = 'csv'): array
    {
        $format = strtolower($format) === 'txt' ? 'txt' : 'csv';
        $records = $this->fetchData($filters);

        $headers = MasterClientExportResource::headers();
        $lines = [implode(';', $headers)];

        foreach ($records as $item) {
            $formatted = (new MasterClientExportResource($item))->toArray(request());
            // Escapar saltos de línea y punto y comas dentro de los campos
            $rowValues = array_map(function ($val) {
                $clean = str_replace(["\r", "\n", ";"], [" ", " ", ","], (string)$val);
                return trim($clean);
            }, array_values($formatted));

            $lines[] = implode(';', $rowValues);
        }

        $content = implode("\r\n", $lines) . "\r\n";
        $dateStr = Carbon::now()->format('Ymd_His');
        $filename = "CLIENTES_MAESTROS_{$dateStr}.{$format}";
        $contentType = ($format === 'txt') ? 'text/plain; charset=UTF-8' : 'text/csv; charset=UTF-8';

        return [$filename, $content, $contentType];
    }

    /**
     * Fetch unpaginated/filtered client records across Master and Tenants.
     */
    private function fetchData(array $filters): array
    {
        $query = MasterClientPolar::query()->with('companyRoute');

        // Filtro de Búsqueda General
        $query->when($filters['query'] ?? null, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('cus_name', 'like', "%{$search}%")
                    ->orWhere('cus_business_name', 'like', "%{$search}%");
            });
        });

        // Filtros sobre company_routes
        if (!empty($filters['codigo_fq'])) {
            $rawCep = ltrim($filters['codigo_fq'], '0');
            $query->whereHas('companyRoute', function ($q) use ($rawCep) {
                $q->where('cep', $rawCep);
            });
        }
        if (!empty($filters['grupo_vendedor'])) {
            $query->whereHas('companyRoute', function ($q) use ($filters) {
                $q->where('address_street2', $filters['grupo_vendedor']);
            });
        }
        if (!empty($filters['oficina'])) {
            $query->whereHas('companyRoute', function ($q) use ($filters) {
                $q->where('address_street1', $filters['oficina']);
            });
        }
        if (!empty($filters['territorio'])) {
            $query->whereHas('companyRoute', function ($q) use ($filters) {
                $q->where('subregion_code', $filters['territorio']);
            });
        }

        $activeTenantsQuery = CompanyRoute::where('is_active', true)->whereNotNull('db_name');
        if (!empty($filters['codigo_fq'])) {
            $rawCep = ltrim($filters['codigo_fq'], '0');
            $activeTenantsQuery->where('cep', $rawCep);
        }
        if (!empty($filters['grupo_vendedor'])) {
            $activeTenantsQuery->where('address_street2', $filters['grupo_vendedor']);
        }
        if (!empty($filters['oficina'])) {
            $activeTenantsQuery->where('address_street1', $filters['oficina']);
        }
        if (!empty($filters['territorio'])) {
            $activeTenantsQuery->where('subregion_code', $filters['territorio']);
        }
        $activeTenants = $activeTenantsQuery->get();

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

                        $selectCols = ['IdCliente', 'cep', 'Cliente', 'Ruta', 'RIF', 'tp1_code', 'TipoCliente', 'segmento', 'TelefonoContacto', 'email', 'Direccion'];
                        if ($hasBusinessName) {
                            $selectCols[] = 'cus_business_name';
                        }

                        $records = DB::connection('tenant')->table('clientes')
                            ->select($selectCols)
                            ->where(function ($q) {
                                $q->whereNull('cep')->orWhere('cep', '');
                            })
                            ->get();

                        foreach ($records as $r) {
                            $client = new \stdClass();
                            $client->id = $r->IdCliente;
                            $client->cus_code = null;
                            $client->cus_name = $r->Cliente;
                            $client->cus_business_name = $hasBusinessName ? ($r->cus_business_name ?? $r->Cliente) : $r->Cliente;
                            $client->companyRoute = $tenant;

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

                            $unlinkedClients[] = $client;
                        }
                    } catch (\Exception $e) {
                        // Ignorar errores de conexión a tenant
                    }
                }

                if (!empty($filters['query'])) {
                    $search = strtolower($filters['query']);
                    $unlinkedClients = array_filter($unlinkedClients, function ($c) use ($search) {
                        return str_contains(strtolower($c->cliente), $search) ||
                               str_contains(strtolower($c->cus_business_name), $search) ||
                               str_contains(strtolower((string)$c->id), $search);
                    });
                }

                return array_values($unlinkedClients);
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
                } catch (\Exception $e) {}
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
                } catch (\Exception $e) {}
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
                } catch (\Exception $e) {}
            }
            $query->whereIn('cus_code', array_unique($matchingCeps));
        }

        $items = $query->orderBy('cus_name')->get();

        // Enriquecer datos multitenant para todos los ítems devueltos
        $tenantGroups = [];
        foreach ($items as $item) {
            $dbName = $item->companyRoute?->db_name;
            if ($dbName) {
                $tenantGroups[$dbName][] = $item;
            }
        }

        foreach ($tenantGroups as $dbName => $groupItems) {
            try {
                Config::set('database.connections.tenant.database', $dbName);
                DB::purge('tenant');

                $ceps = array_map(fn ($item) => $item->cus_code, $groupItems);

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
                    } else {
                        $item->cep = $item->cus_code;
                        $item->cliente = $item->cus_business_name ?: $item->cus_name;
                    }
                }
            } catch (\Exception $e) {
                foreach ($groupItems as $item) {
                    $item->cep = $item->cus_code;
                    $item->cliente = $item->cus_business_name ?: $item->cus_name;
                }
            }
        }

        return $items->all();
    }
}
