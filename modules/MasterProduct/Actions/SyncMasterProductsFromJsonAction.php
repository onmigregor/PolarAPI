<?php

namespace Modules\MasterProduct\Actions;

use Modules\MasterProduct\Models\MasterProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncMasterProductsFromJsonAction
{
    private array $suffixes = ['BA', 'AG', 'PR', 'S0', 'IN', 'S1'];

    public function execute(string $jsonPath): array
    {
        $results = [
            'total_in_json' => 0,
            'updated' => 0,
            'created' => 0,
            'errors' => [],
        ];

        if (!file_exists($jsonPath)) {
            $results['errors'][] = "File not found: $jsonPath";
            return $results;
        }

        $jsonContent = file_get_content_utf8($jsonPath);
        $data = json_decode($jsonContent, true);

        if (!$data || !isset($data[0]['value']['product'])) {
            $results['errors'][] = "Invalid JSON structure.";
            return $results;
        }

        $products = $data[0]['value']['product'];
        $class4Data = $data[0]['value']['class4'] ?? [];
        $results['total_in_json'] = count($products);

        // 1. Sync Class 4 Lookup Table
        foreach ($class4Data as $c4) {
            $breakdown = $this->breakdownCl4Code($c4['cl4code']);
            \Modules\MasterProduct\Models\MasterProductClass4::updateOrCreate(
                ['cl4_code' => $c4['cl4code']],
                [
                    'cl4_name'     => $c4['cl4name'],
                    'brand_code'   => $breakdown['brand_code'],
                    'segment_code' => $breakdown['segment_code'],
                ]
            );
        }

        // 2. Sync Products
        foreach (array_chunk($products, 200) as $chunk) {
            DB::transaction(function () use ($chunk, &$results) {
                foreach ($chunk as $p) {
                    try {
                        $this->processProduct($p, $results);
                    } catch (\Exception $e) {
                        $results['errors'][] = "SKU {$p['proCode']}: " . $e->getMessage();
                    }
                }
            });
        }

        // 3. Sync Product Units
        $unitsData = $data[0]['value']['productunit'] ?? [];
        foreach (array_chunk($unitsData, 200) as $unitChunk) {
            DB::transaction(function () use ($unitChunk) {
                foreach ($unitChunk as $u) {
                    \Modules\MasterProduct\Models\MasterProductUnit::updateOrCreate(
                        ['pro_code' => (string)$u['proCode'], 'unt_code' => (string)$u['untCode']],
                        [
                            'pru_multiply_by' => !empty($u['pruMultiplyBy']) ? (float)$u['pruMultiplyBy'] : null,
                            'pru_divide_by'   => !empty($u['pruDivideBy']) ? (float)$u['pruDivideBy'] : null,
                            'pru_bar_code'    => (string)($u['pruBarCode'] ?? ''),
                        ]
                    );
                }
            });
        }

        return $results;
    }

    private function processProduct(array $p, array &$results): void
    {
        $sku = (string) $p['proCode'];
        if (empty($sku)) return;

        // 1. Clean Boolean values ("X" -> true, "" -> false)
        $isActive = (strtoupper($p['proAvailableForSale'] ?? '') === 'X');
        
        // 2. Breakdown cl4code
        $dirtyCode = $p['cl4code'] ?? '';
        $breakdown = $this->breakdownCl4Code($dirtyCode);

        // 3. Clean Barcode (Convert numeric to string, handle empty)
        $barcode = !empty($p['proBarCode']) ? (string) $p['proBarCode'] : null;

        // 4. Multiplicity
        $multiplicity = !empty($p['proSalesMultiplicity']) ? (int) $p['proSalesMultiplicity'] : 1;

        // 5. Upsert into master_products
        $product = MasterProduct::updateOrCreate(
            ['sku' => $sku],
            [
                'name'           => $p['proName'] ?? 'Unnamed Product',
                'pro_short_name' => $p['proShortName'] ?? null,
                'barcode'        => $barcode,
                'cl2_code'       => $p['cl2code'] ?? null,
                'cl3_code'       => $p['cl3code'] ?? null,
                'cl4_code'       => $dirtyCode,
                'brand_code'     => $breakdown['brand_code'],
                'segment_code'   => $breakdown['segment_code'],
                'pro_bom_code'   => $p['bomCode'] ?? null,
                'pro_return_allowed' => (strtoupper($p['proReturnAllowed'] ?? '') === 'X'),
                'pro_damage_returns_allowed' => (strtoupper($p['proDamageReturnsAllowed'] ?? '') === 'X'),
                'pro_available_for_sale' => $isActive,
                'pro_customer_inventory_allowed' => (strtoupper($p['proCustomerInventoryAllowed'] ?? '') === 'X'),
                'multiplicity'   => $multiplicity,
                'is_active'      => $isActive,
                'brand'          => $p['proOrganization'] ?? null,
                'unt_code'       => $p['untCode'] ?? null,
            ]
        );

        if ($product->wasRecentlyCreated) {
            $results['created']++;
        } else {
            $results['updated']++;
        }
    }

    private function breakdownCl4Code(string $code): array
    {
        $code = trim($code);
        $brand_code = null;
        $segment_code = null;

        if (empty($code)) {
            return ['brand_code' => null, 'segment_code' => null];
        }

        // Logic: Try to find suffix from our list
        foreach ($this->suffixes as $suffix) {
            if (str_ends_with($code, $suffix)) {
                $segment_code = $suffix;
                $remaining = substr($code, 0, -strlen($suffix));
                
                // Extract brand code (usually the last part of what's left after a space or a fixed pattern)
                // Example: "NAACFHC50 M200BA" -> "NAACFHC50 M200"
                if (str_contains($remaining, ' ')) {
                    $parts = explode(' ', $remaining);
                    $brand_code = end($parts);
                } else {
                    // Example: "VERBNCR005B009S0" -> "VERBNCR005B009"
                    // Often Brand Code is 4 characters (B009, M200)
                    $brand_code = substr($remaining, -4);
                }
                break;
            }
        }

        // Fallback if no known suffix found
        if (!$segment_code) {
             if (str_contains($code, ' ')) {
                $parts = explode(' ', $code);
                $brand_code = end($parts);
            } else {
                // If it ends in numbers, maybe it's just the brand
                $brand_code = substr($code, -4);
            }
        }

        return [
            'brand_code'   => $brand_code,
            'segment_code' => $segment_code
        ];
    }
}

/**
 * Helper to ensure UTF-8 reading
 */
function file_get_content_utf8($fn) {
    $content = file_get_contents($fn);
    return mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
}
