<?php

namespace App\Support\Uom;

use App\Models\ItemUomConversion;
use App\Models\Uom;
use App\Models\UomConversion;
use Illuminate\Support\Collection;

/**
 * Resolve direct, reciprocal, and multi-step UoM conversion paths.
 */
class UomConversionPathResolver
{
    public const PRECEDENCE_GENERAL_FIRST = 'general_first';
    public const PRECEDENCE_ITEM_FIRST = 'item_first';

    private const SCALE = 6;
    private const FACTOR_SCALE = 12;

    /**
     * Determine whether a conversion path exists for the tenant and optional item context.
     */
    public function canResolve(
        int $tenantId,
        ?int $itemId,
        Uom $fromUom,
        Uom $toUom,
        string $precedence = self::PRECEDENCE_GENERAL_FIRST
    ): bool {
        return $this->resolveFactor($tenantId, $itemId, $fromUom, $toUom, $precedence) !== null;
    }

    /**
     * Convert a quantity through a resolved conversion path.
     */
    public function convertQuantity(
        int $tenantId,
        ?int $itemId,
        Uom $fromUom,
        Uom $toUom,
        string $quantity,
        string $precedence = self::PRECEDENCE_GENERAL_FIRST
    ): ?string {
        $factor = $this->resolveFactor($tenantId, $itemId, $fromUom, $toUom, $precedence);

        if ($factor === null) {
            return null;
        }

        return bcmul($quantity, $factor, self::SCALE);
    }

    /**
     * Resolve the final multiplier from one UoM symbol to another.
     */
    public function resolveFactor(
        int $tenantId,
        ?int $itemId,
        Uom $fromUom,
        Uom $toUom,
        string $precedence = self::PRECEDENCE_GENERAL_FIRST
    ): ?string {
        $fromSymbol = $this->normalizeSymbol((string) $fromUom->symbol);
        $toSymbol = $this->normalizeSymbol((string) $toUom->symbol);

        if ($fromSymbol === '' || $toSymbol === '') {
            return null;
        }

        if ($fromSymbol === $toSymbol) {
            return '1.000000000000';
        }

        $edges = $this->orderedEdges($tenantId, $itemId, $precedence);

        return $this->findFactor($fromSymbol, $toSymbol, $edges);
    }

    /**
     * Build conversion graph edges in the requested precedence order.
     *
     * @return array<int, array{from: string, to: string, factor: string}>
     */
    private function orderedEdges(int $tenantId, ?int $itemId, string $precedence): array
    {
        $generalEdges = $this->generalEdges($tenantId);
        $itemEdges = $itemId === null ? [] : $this->itemEdges($tenantId, $itemId);

        if ($precedence === self::PRECEDENCE_ITEM_FIRST) {
            return array_merge($itemEdges, $generalEdges);
        }

        return array_merge($generalEdges, $itemEdges);
    }

    /**
     * Build tenant and global general conversion edges.
     *
     * @return array<int, array{from: string, to: string, factor: string}>
     */
    private function generalEdges(int $tenantId): array
    {
        /** @var Collection<int, UomConversion> $conversions */
        $conversions = UomConversion::query()
            ->join('uoms as from_uoms', 'from_uoms.id', '=', 'uom_conversions.from_uom_id')
            ->join('uoms as to_uoms', 'to_uoms.id', '=', 'uom_conversions.to_uom_id')
            ->where(function ($query) use ($tenantId): void {
                $query->where('uom_conversions.tenant_id', $tenantId)
                    ->orWhereNull('uom_conversions.tenant_id');
            })
            ->orderByRaw('CASE WHEN uom_conversions.tenant_id = ? THEN 0 ELSE 1 END', [$tenantId])
            ->orderBy('uom_conversions.id')
            ->get([
                'uom_conversions.multiplier',
                'from_uoms.symbol as from_symbol',
                'to_uoms.symbol as to_symbol',
            ]);

        return $this->edgesFromRows($conversions, 'multiplier');
    }

    /**
     * Build item-specific conversion edges.
     *
     * @return array<int, array{from: string, to: string, factor: string}>
     */
    private function itemEdges(int $tenantId, int $itemId): array
    {
        /** @var Collection<int, ItemUomConversion> $conversions */
        $conversions = ItemUomConversion::query()
            ->withoutGlobalScopes()
            ->join('uoms as from_uoms', 'from_uoms.id', '=', 'item_uom_conversions.from_uom_id')
            ->join('uoms as to_uoms', 'to_uoms.id', '=', 'item_uom_conversions.to_uom_id')
            ->where('item_uom_conversions.tenant_id', $tenantId)
            ->where('item_uom_conversions.item_id', $itemId)
            ->orderBy('item_uom_conversions.id')
            ->get([
                'item_uom_conversions.conversion_factor',
                'from_uoms.symbol as from_symbol',
                'to_uoms.symbol as to_symbol',
            ]);

        return $this->edgesFromRows($conversions, 'conversion_factor');
    }

    /**
     * Convert conversion rows into direct and reciprocal graph edges.
     *
     * @param Collection<int, mixed> $rows
     * @return array<int, array{from: string, to: string, factor: string}>
     */
    private function edgesFromRows(Collection $rows, string $factorColumn): array
    {
        $edges = [];

        foreach ($rows as $row) {
            $from = $this->normalizeSymbol((string) $row->from_symbol);
            $to = $this->normalizeSymbol((string) $row->to_symbol);
            $factor = bcadd((string) $row->{$factorColumn}, '0', self::FACTOR_SCALE);

            if ($from === '' || $to === '' || bccomp($factor, '0', self::FACTOR_SCALE) !== 1) {
                continue;
            }

            $edges[] = [
                'from' => $from,
                'to' => $to,
                'factor' => $factor,
            ];
            $edges[] = [
                'from' => $to,
                'to' => $from,
                'factor' => bcdiv('1', $factor, self::FACTOR_SCALE),
            ];
        }

        return $edges;
    }

    /**
     * Find a conversion factor through the graph using breadth-first traversal.
     *
     * @param array<int, array{from: string, to: string, factor: string}> $edges
     */
    private function findFactor(string $fromSymbol, string $toSymbol, array $edges): ?string
    {
        $queue = [
            [
                'symbol' => $fromSymbol,
                'factor' => '1.000000000000',
            ],
        ];
        $visited = [$fromSymbol => true];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($edges as $edge) {
                if ($edge['from'] !== $current['symbol']) {
                    continue;
                }

                $nextFactor = bcmul($current['factor'], $edge['factor'], self::FACTOR_SCALE);

                if ($edge['to'] === $toSymbol) {
                    return $nextFactor;
                }

                if (isset($visited[$edge['to']])) {
                    continue;
                }

                $visited[$edge['to']] = true;
                $queue[] = [
                    'symbol' => $edge['to'],
                    'factor' => $nextFactor,
                ];
            }
        }

        return null;
    }

    /**
     * Normalize UoM symbols for semantic matching.
     */
    private function normalizeSymbol(string $symbol): string
    {
        return strtolower(trim($symbol));
    }
}
