<?php

namespace App\Services\Uom;

use App\Models\Tenant;
use App\Models\Uom;
use App\Models\UomCategory;
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
     * @return array{categories: array<int, array<string, string>>, uoms: array<int, array<string, string>>}
     */
    private function loadConfig(): array
    {
        $config = config('system_uoms');

        if (! is_array($config)) {
            throw new RuntimeException('system_uoms config must be an array.');
        }

        $categories = Arr::get($config, 'categories');
        $uoms = Arr::get($config, 'uoms');

        if (! is_array($categories) || ! is_array($uoms)) {
            throw new RuntimeException('system_uoms config must include categories and uoms arrays.');
        }

        return [
            'categories' => $categories,
            'uoms' => $uoms,
        ];
    }

    /**
     * @param array{categories: array<int, array<string, string>>, uoms: array<int, array<string, string>>} $data
     */
    private function isEmptyConfig(array $data): bool
    {
        return count($data['categories']) === 0 && count($data['uoms']) === 0;
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
}
