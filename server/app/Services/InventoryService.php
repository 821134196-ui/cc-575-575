<?php

namespace App\Services;

use App\Models\Entrance;
use App\Models\ProgramVersion;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * 库存账：所有数量以 stock_movements 流水为唯一事实来源。
 *
 * 总库存口径（entrance_id 为 NULL 表示前台总库）：
 *   交货 deliver       总库 +            （总库存增加）
 *   分配 allocate_out  总库 - / allocate_in 入口 +   （总库存不变）
 *   发放 issue         入口 -            （总库存减少）
 *   调拨 transfer_out  出口 - / transfer_in 入口 +  （总库存不变）
 *   报废 scrap         所在库 -          （总库存减少，仅审批通过后记账）
 *
 * 核对恒等式：交货总数 = 发放总数 + 报废总数 + 当前剩余（总库 + 各入口）
 */
class InventoryService
{
    public function stockAt(int $versionId, ?int $entranceId): int
    {
        return (int) StockMovement::query()
            ->where('program_version_id', $versionId)
            ->where(fn ($q) => $entranceId === null
                ? $q->whereNull('entrance_id')
                : $q->where('entrance_id', $entranceId))
            ->sum('quantity');
    }

    /** 各入口库存 map：[entrance_id => qty]，总库键为 "front"。 */
    public function stockBreakdown(int $versionId): array
    {
        $rows = StockMovement::query()
            ->where('program_version_id', $versionId)
            ->selectRaw('COALESCE(entrance_id, "front") as loc, SUM(quantity) as qty')
            ->groupBy('loc')
            ->pluck('qty', 'loc');

        $out = ['front' => (int) ($rows['front'] ?? 0)];
        foreach (Entrance::orderBy('id')->get() as $e) {
            $out[$e->id] = (int) ($rows[$e->id] ?? 0);
        }
        return $out;
    }

    public function totals(int $versionId): array
    {
        $sums = StockMovement::query()
            ->where('program_version_id', $versionId)
            ->selectRaw('kind, SUM(quantity) as qty')
            ->groupBy('kind')
            ->pluck('qty', 'kind');

        $byKind = [
            'deliver' => (int) ($sums['deliver'] ?? 0),
            'issue' => (int) ($sums['issue'] ?? 0),
            'scrap' => (int) ($sums['scrap'] ?? 0),
        ];

        $delivered = $byKind['deliver'];
        $issued = abs($byKind['issue']);
        $scrapped = abs($byKind['scrap']);

        $breakdown = $this->stockBreakdown($versionId);
        $front = $breakdown['front'];
        $entranceTotal = collect($breakdown)->except('front')->sum();
        $onHand = $front + $entranceTotal;

        return [
            'delivered' => $delivered,
            'issued' => $issued,
            'scrapped' => $scrapped,
            'front_stock' => $front,
            'entrance_stock' => $entranceTotal,
            'on_hand' => $onHand,
            'expected_remaining' => $delivered - $issued - $scrapped,
            'balanced' => $delivered === $issued + $scrapped + $onHand
                && $onHand === $delivered - $issued - $scrapped,
            'has_negative' => collect($breakdown)->contains(fn ($q) => $q < 0),
        ];
    }

    /** 记一条流水（必须在业务事务内调用）。 */
    public function move(int $versionId, ?int $entranceId, int $quantity, string $kind, ?object $ref = null, ?string $note = null): StockMovement
    {
        return StockMovement::create([
            'program_version_id' => $versionId,
            'entrance_id' => $entranceId,
            'quantity' => $quantity,
            'kind' => $kind,
            'ref_type' => $ref ? class_basename($ref) : null,
            'ref_id' => $ref?->getKey(),
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    /**
     * 全量核对：逐版本 + 总账两套口径必须一致。
     */
    public function reconciliation(): array
    {
        $versions = ProgramVersion::with('production')->get();
        $perVersion = $versions->map(fn ($v) => [
            'version_id' => $v->id,
            'version_label' => "v{$v->version_no} {$v->label}",
            'production' => $v->production->name,
            ...$this->totals($v->id),
        ])->values();

        $sum = fn ($k) => $perVersion->sum($k);

        $grand = [
            'delivered' => $sum('delivered'),
            'issued' => $sum('issued'),
            'scrapped' => $sum('scrapped'),
            'on_hand' => $sum('on_hand'),
            'front_stock' => $sum('front_stock'),
            'entrance_stock' => $sum('entrance_stock'),
        ];
        $grand['balanced'] = $grand['delivered'] === $grand['issued'] + $grand['scrapped'] + $grand['on_hand'];

        // 调拨净增必须为 0（调入之和 = 调出之和）。
        $transferNet = (int) StockMovement::whereIn('kind', ['transfer_in', 'transfer_out'])->sum('quantity');
        // 分配净增必须为 0。
        $allocateNet = (int) StockMovement::whereIn('kind', ['allocate_in', 'allocate_out'])->sum('quantity');

        return [
            'per_version' => $perVersion,
            'grand' => $grand,
            'transfer_net' => $transferNet,
            'allocate_net' => $allocateNet,
            'internal_movements_conserved' => $transferNet === 0 && $allocateNet === 0,
            'all_balanced' => $grand['balanced']
                && $transferNet === 0
                && $allocateNet === 0
                && $perVersion->every(fn ($r) => $r['balanced'] && ! $r['has_negative']),
        ];
    }
}
