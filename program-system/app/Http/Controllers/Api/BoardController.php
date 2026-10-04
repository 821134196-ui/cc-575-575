<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entrance;
use App\Services\Location;
use App\Services\StockService;

class BoardController extends Controller
{
    /**
     * 入口看板：本场允许发放的版本及剩余数量。
     * GET /api/performances/{p}/entrances/{e}/board
     */
    public function show(int $performanceId, Entrance $entrance, StockService $stock)
    {
        $performance = \App\Models\Performance::with('versions')->findOrFail($performanceId);
        $loc = Location::entrance($entrance->id);

        $allowed = $performance->versions
            ->whereIn('status', ['locked', 'superseded'])
            ->map(function ($v) use ($loc, $stock, $entrance) {
                $remaining = $v->locationStock($loc);
                $batches = $v->printBatches()
                    ->whereHas('allocations', fn ($q) => $q->where('entrance_id', $entrance->id))
                    ->get(['id', 'batch_no', 'quantity_delivered']);

                return [
                    'version_id' => $v->id,
                    'version_no' => $v->version_no,
                    'file_name' => $v->file_name,
                    'status' => $v->status,
                    'status_label' => $v->status === 'locked' ? '当前有效版本' : '已被新版替代（旧单不可改名顶替）',
                    'allowed_to_issue' => $remaining > 0,
                    'remaining' => $remaining,
                    'print_batches' => $batches,
                ];
            })
            ->values();

        return [
            'performance' => [
                'id' => $performance->id,
                'title' => $performance->title,
                'performed_at' => $performance->performed_at->toDateTimeString(),
                'venue' => $performance->venue,
                'status' => $performance->status,
            ],
            'entrance' => ['id' => $entrance->id, 'code' => $entrance->code, 'name' => $entrance->name],
            'allowed_versions' => $allowed,
            'total_remaining' => $allowed->sum('remaining'),
        ];
    }
}
