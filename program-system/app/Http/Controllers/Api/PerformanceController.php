<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entrance;
use App\Models\Performance;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    public function index()
    {
        return Performance::query()
            ->withCount('versions')
            ->orderBy('performed_at')
            ->get()
            ->map(fn (Performance $p) => $this->summary($p));
    }

    public function show(Performance $performance)
    {
        $performance->load('versions.printBatches.allocations.entrance');

        $versions = $performance->versions->map(function ($v) {
            $stock = app(\App\Services\StockService::class)->reconcile($v);

            return [
                'id' => $v->id,
                'version_no' => $v->version_no,
                'file_name' => $v->file_name,
                'file_hash' => $v->file_hash,
                'cast_list' => $v->cast_list,
                'change_reason' => $v->change_reason,
                'status' => $v->status,
                'locked_at' => $v->locked_at?->toDateTimeString(),
                'locked_by' => $v->locked_by,
                'source_version_id' => $v->source_version_id,
                'batches' => $v->printBatches->map(fn ($b) => [
                    'id' => $b->id,
                    'batch_no' => $b->batch_no,
                    'quantity_ordered' => $b->quantity_ordered,
                    'quantity_delivered' => $b->quantity_delivered,
                    'status' => $b->status,
                    'printer_receipt_no' => $b->printer_receipt_no,
                    'delivered_at' => $b->delivered_at?->toDateTimeString(),
                    'allocations' => $b->allocations->map(fn ($a) => [
                        'id' => $a->id,
                        'entrance' => ['id' => $a->entrance->id, 'code' => $a->entrance->code, 'name' => $a->entrance->name],
                        'quantity' => $a->quantity,
                        'allocated_by' => $a->allocated_by,
                        'created_at' => $a->created_at->toDateTimeString(),
                    ]),
                ]),
                'stock' => $stock,
            ];
        });

        return [
            'id' => $performance->id,
            'title' => $performance->title,
            'performed_at' => $performance->performed_at->toDateTimeString(),
            'venue' => $performance->venue,
            'status' => $performance->status,
            'notes' => $performance->notes,
            'versions' => $versions,
            'entrances' => Entrance::orderBy('code')->get(['id', 'code', 'name']),
            'counts' => $performance->stockCounts()->with('version:id,version_no')->get(),
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'performed_at' => ['required', 'date'],
            'venue' => ['nullable', 'string', 'max:100'],
            'cast_list' => ['required', 'string'],
            'file_name' => ['required', 'string', 'max:150'],
        ]);

        $performance = Performance::create([
            'title' => $data['title'],
            'performed_at' => $data['performed_at'],
            'venue' => $data['venue'] ?? '主剧场',
            'status' => 'draft',
        ]);

        $version = $performance->versions()->create([
            'version_no' => 1,
            'file_name' => $data['file_name'],
            'cast_list' => $data['cast_list'],
            'status' => 'draft',
        ]);

        return response()->json($this->summary($performance->fresh()), 201);
    }

    private function summary(Performance $p): array
    {
        return [
            'id' => $p->id,
            'title' => $p->title,
            'performed_at' => $p->performed_at->toDateTimeString(),
            'venue' => $p->venue,
            'status' => $p->status,
            'versions_count' => $p->versions_count ?? $p->versions()->count(),
        ];
    }
}
