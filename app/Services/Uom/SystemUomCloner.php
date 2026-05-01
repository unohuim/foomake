<?php

namespace App\Services\Uom;

use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
use App\Models\UomConversion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Class SystemUomCloner
 *
 * Seeds system UoMs and clones them into tenants.
 */
class SystemUomCloner
{
    /**
     * Seed system defaults with tenant_id = null.
     */
    public function seedSystemDefaults(): void
    {
        $data = $this->loadConfig();

        if ($this->isEmptyConfig($data)) {
            return;
        }

        DB::transaction(function () use ($data): void {
            $categoryMap = $this->ensureSystemCategories($data['categories']);
            $this->ensureSystemUoms($categoryMap, $data['uoms']);
            $this->ensureSystemConversions($categoryMap, $data['conversions']);
        });
    }

    /**
     * Clone defaults into a tenant.
     */
    public function cloneForTenant(Tenant $tenant): void
    {
        $data = $this->loadConfig();

        if ($this->isEmptyConfig($data)) {
            return;
        }

        DB::transaction(function () use ($tenant, $data): void {
            $categoryMap = $this->ensureTenantCategories($tenant, $data['categories']);
            $this->ensureTenantUoms($tenant, $categoryMap, $data['uoms']);
        });
    }

    /**
     * @return array{
     *     categories: array<int, array<string, string>>,
     *     uoms: array<int, array<string, string>>,
     *     conversions: array<string, array<int, array<string, string>>>
     * }
     */
    private function loadConfig(): array
    {
        $config = config('system_uoms');

        if (! is_array($config)) {
            throw new RuntimeException('system_uoms config must be an array.');
        }

        $categories = Arr::get($config, 'categories');
        $uoms = Arr::get($config, 'uoms');
        $conversions = Arr::get($config, 'conversions', []);

        if (! is_array($categories) || ! is_array($uoms)) {
            throw new RuntimeException('system_uoms config must include categories and uoms arrays.');
        }

        if (! is_array($conversions)) {
            throw new RuntimeException('system_uoms conversions config must be an array.');
        }

        return [
            'categories' => $categories,
            'uoms' => $uoms,
            'conversions' => $conversions,
        ];
    }

    /**
     * @param array{
     *     categories: array<int, array<string, string>>,
     *     uoms: array<int, array<string, string>>,
     *     conversions: array<string, array<int, array<string, string>>>
     * } $data
     */
    private function isEmptyConfig(array $data): bool
    {
        return count($data['categories']) === 0
            && count($data['uoms']) === 0
            && count($data['conversions']) === 0;
    }

    /**
     * @param array<int, array<string, string>> $categories
     * @return array<string, UomCategory>
     */
    private function ensureSystemCategories(array $categories): array
    {
        $mapped = [];

        foreach ($categories as $category) {
            $key = $category['key'] ?? null;
            $name = $category['name'] ?? null;

            if (! is_string($key) || $key === '' || ! is_string($name) || $name === '') {
                throw new RuntimeException('Invalid system_uoms category entry.');
            }

            $model = UomCategory::query()->firstOrCreate([
                'tenant_id' => null,
                'name' => $name,
            ]);

            $mapped[$key] = $model;
        }

        return $mapped;
    }

    /**
     * @param array<string, UomCategory> $categoryMap
     * @param array<int, array<string, string>> $uoms
     */
    private function ensureSystemUoms(array $categoryMap, array $uoms): void
    {
        foreach ($uoms as $uom) {
            $categoryKey = $uom['category_key'] ?? null;
            $name = $uom['name'] ?? null;
            $symbol = $uom['symbol'] ?? null;

            if (! is_string($categoryKey) || $categoryKey === '' || ! is_string($name) || $name === '' || ! is_string($symbol) || $symbol === '') {
                throw new RuntimeException('Invalid system_uoms uom entry.');
            }

            $category = $categoryMap[$categoryKey] ?? null;

            if (! $category) {
                throw new RuntimeException('system_uoms uom references unknown category key.');
            }

            Uom::query()->firstOrCreate([
                'tenant_id' => null,
                'symbol' => $symbol,
            ], [
                'uom_category_id' => $category->id,
                'name' => $name,
            ]);
        }
    }

    /**
     * @param array<int, array<string, string>> $categories
     * @return array<string, UomCategory>
     */
    private function ensureTenantCategories(Tenant $tenant, array $categories): array
    {
        $mapped = [];

        foreach ($categories as $category) {
            $key = $category['key'] ?? null;
            $name = $category['name'] ?? null;

            if (! is_string($key) || $key === '' || ! is_string($name) || $name === '') {
                throw new RuntimeException('Invalid system_uoms category entry.');
            }

            $model = UomCategory::query()->firstOrCreate([
                'tenant_id' => $tenant->id,
                'name' => $name,
            ]);

            $mapped[$key] = $model;
        }

        return $mapped;
    }

    /**
     * @param array<string, UomCategory> $categoryMap
     * @param array<int, array<string, string>> $uoms
     */
    private function ensureTenantUoms(Tenant $tenant, array $categoryMap, array $uoms): void
    {
        foreach ($uoms as $uom) {
            $categoryKey = $uom['category_key'] ?? null;
            $name = $uom['name'] ?? null;
            $symbol = $uom['symbol'] ?? null;

            if (! is_string($categoryKey) || $categoryKey === '' || ! is_string($name) || $name === '' || ! is_string($symbol) || $symbol === '') {
                throw new RuntimeException('Invalid system_uoms uom entry.');
            }

            $category = $categoryMap[$categoryKey] ?? null;

            if (! $category) {
                throw new RuntimeException('system_uoms uom references unknown category key.');
            }

            Uom::query()->firstOrCreate([
                'tenant_id' => $tenant->id,
                'symbol' => $symbol,
            ], [
                'uom_category_id' => $category->id,
                'name' => $name,
            ]);
        }
    }

    /**
     * @param array<string, UomCategory> $categoryMap
     * @param array<string, array<int, array<string, string>>> $conversions
     */
    private function ensureSystemConversions(array $categoryMap, array $conversions): void
    {
        foreach ($conversions as $categoryKey => $entries) {
            if (! is_array($entries)) {
                throw new RuntimeException('Invalid system_uoms conversions entry.');
            }

            $category = $categoryMap[$categoryKey] ?? null;

            if (! $category) {
                throw new RuntimeException('system_uoms conversion references unknown category key.');
            }

            foreach ($entries as $entry) {
                $fromSymbol = $entry['from'] ?? null;
                $toSymbol = $entry['to'] ?? null;
                $multiplier = $entry['multiplier'] ?? null;

                if (
                    ! is_string($fromSymbol) || $fromSymbol === ''
                    || ! is_string($toSymbol) || $toSymbol === ''
                    || ! is_string($multiplier) || $multiplier === ''
                ) {
                    throw new RuntimeException('Invalid system_uoms conversion entry.');
                }

                if (bccomp($multiplier, '0', 8) <= 0) {
                    throw new RuntimeException('system_uoms conversion multiplier must be greater than zero.');
                }

                $fromUom = Uom::query()
                    ->withoutGlobalScopes()
                    ->whereNull('tenant_id')
                    ->where('symbol', $fromSymbol)
                    ->first();

                $toUom = Uom::query()
                    ->withoutGlobalScopes()
                    ->whereNull('tenant_id')
                    ->where('symbol', $toSymbol)
                    ->first();

                if (! $fromUom || ! $toUom) {
                    throw new RuntimeException('system_uoms conversion references missing UoMs.');
                }

                if ((int) $fromUom->uom_category_id !== (int) $category->id || (int) $toUom->uom_category_id !== (int) $category->id) {
                    throw new RuntimeException('system_uoms conversion category mismatch.');
                }

                UomConversion::query()->firstOrCreate([
                    'tenant_id' => null,
                    'from_uom_id' => $fromUom->id,
                    'to_uom_id' => $toUom->id,
                ], [
                    'multiplier' => $multiplier,
                ]);

                UomConversion::query()->firstOrCreate([
                    'tenant_id' => null,
                    'from_uom_id' => $toUom->id,
                    'to_uom_id' => $fromUom->id,
                ], [
                    'multiplier' => bcdiv('1', $multiplier, 8),
                ]);
            }
        }
    }
}
