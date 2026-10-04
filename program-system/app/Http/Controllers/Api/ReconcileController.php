<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Performance;
use App\Models\StockMovement;
use App\Services\StockService;

class ReconcileController extends Controller
{
    /** 整场核对：每个版本 交货 = 总仓 + 入口 + 已发放 + 已报废 */
    public function performance(Performance $performance, StockService $stock)
    {
        $rows = $performance->versions()
            ->whereIn('status', ['locked', 'superseded'])
            ->get()
            ->map(fn ($v) => $stock->reconcile($v));

        $totals = [
            'delivered' => $rows->sum('delivered'),
            'on_hand' => $rows->sum('on_hand'),
            'issued' => $rows->sum('issued'),
            'scrapped' => $rows->sum('scrapped'),
            'accounted' => $rows->sum('accounted'),
        ];
        $totals['diff'] = $totals['delivered'] - $totals['accounted'];
        $totals['balanced'] = $rows->every(fn ($r) => $r['balanced']);

        return response()->json([
            'performance_id' => $performance->id,
            'title' => $performance->title,
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    /** 流水明细（追溯用） */
    public function movements(Performance $performance)
    {
        $versionIds = $performance->versions()->pluck('id');

        return StockMovement::whereIn('program_version_id', $versionIds)
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'version_id' => $m->program_version_id,
                'batch_id' => $m->print_batch_id,
                'type' => $m->type,
                'from' => $m->location_from,
                'to' => $m->location_to,
                'quantity' => $m->quantity,
                'ref_type' => class_basename($m->ref_type ?: ''),
                'ref_id' => $m->ref_id,
                'operator' => $m->operator,
                'at' => $m->created_at->toDateTimeString(),
            ]);
    }
}
