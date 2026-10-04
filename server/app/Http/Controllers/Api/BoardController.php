<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entrance;
use App\Models\Performance;
use App\Services\InventoryService;

class BoardController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * 入口看板：每个入口显示已确认场次允许发放的版本及剩余数量。
     */
    public function index()
    {
        $entrances = Entrance::orderBy('id')->get();
        $performances = Performance::with(['production', 'programVersion'])
            ->whereIn('status', ['confirmed', 'closed'])
            ->orderBy('performed_at')
            ->get();

        $cards = $performances->map(function ($p) use ($entrances) {
            $version = $p->programVersion;
            $breakdown = $this->inventory->stockBreakdown($version->id);
            return [
                'performance_id' => $p->id,
                'performance_name' => $p->name,
                'performed_at' => $p->performed_at,
                'production' => $p->production->name,
                'status' => $p->status,
                'allowed_version' => [
                    'id' => $version->id,
                    'label' => "v{$version->version_no} {$version->label}",
                    'file_name' => $version->file_name,
                    'file_hash' => $version->file_hash ? substr($version->file_hash, 0, 12) : null,
                    'locked_at' => $version->locked_at,
                ],
                'entrances' => $entrances->map(fn ($e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'remaining' => $breakdown[$e->id] ?? 0,
                ]),
                'front_remaining' => $breakdown['front'],
            ];
        });

        return response()->json(['cards' => $cards]);
    }
}
