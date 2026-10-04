<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entrance;
use App\Models\Performance;
use App\Models\Production;
use App\Services\InventoryService;
use App\Services\ProgramService;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    public function __construct(
        private readonly ProgramService $programs,
        private readonly InventoryService $inventory,
    ) {}

    public function index()
    {
        return Performance::with(['production', 'programVersion'])
            ->orderBy('performed_at')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'production_id' => 'required|integer|exists:productions,id',
            'name' => 'required|string|max:100',
            'performed_at' => 'required|date',
        ]);
        return Performance::create($data);
    }

    /** 场次页面：追到文件、印刷批次和各入口领用情况。 */
    public function show(Performance $performance)
    {
        $performance->load([
            'production',
            'programVersion.printBatches.allocations.entrance',
            'issues.entrance',
            'stockCounts.entrance',
        ]);

        $versionId = $performance->program_version_id;
        $entrances = Entrance::orderBy('id')->get()->map(function ($e) use ($performance, $versionId) {
            $issued = (int) $performance->issues->where('entrance_id', $e->id)->sum('quantity');
            $moves = $versionId
                ? \App\Models\StockMovement::where('program_version_id', $versionId)
                    ->where('entrance_id', $e->id)
                    ->selectRaw('kind, SUM(quantity) as q')
                    ->groupBy('kind')->pluck('q', 'kind')
                : collect();
            $totalIn = (int) ($moves['allocate_in'] ?? 0) + (int) ($moves['transfer_in'] ?? 0);
            return [
                'id' => $e->id,
                'name' => $e->name,
                'total_in' => $totalIn,
                'transfer_out' => abs((int) ($moves['transfer_out'] ?? 0)),
                'scrapped' => abs((int) ($moves['scrap'] ?? 0)),
                'issued_for_performance' => $issued,
                'current_stock' => $versionId ? $this->inventory->stockAt($versionId, $e->id) : null,
                'counted_remaining' => optional(
                    $performance->stockCounts->firstWhere('entrance_id', $e->id)
                )->remaining_qty,
            ];
        });

        return response()->json([
            'performance' => $performance,
            'entrance_ledger' => $entrances,
            'inventory' => $versionId ? [
                'breakdown' => $this->inventory->stockBreakdown($versionId),
                'totals' => $this->inventory->totals($versionId),
            ] : null,
        ]);
    }

    public function confirm(Request $request, Performance $performance)
    {
        $data = $request->validate([
            'program_version_id' => 'required|integer|exists:program_versions,id',
            'by' => 'sometimes|string|max:50',
        ]);
        return $this->programs->confirmPerformance(
            $performance, (int) $data['program_version_id'], $data['by'] ?? '编辑'
        );
    }

    public function issue(Request $request, Performance $performance)
    {
        $data = $request->validate([
            'entrance_id' => 'required|integer|exists:entrances,id',
            'quantity' => 'required|integer|min:1',
            'issued_by' => 'sometimes|string|max:50',
        ]);
        return $this->programs->issue(
            $performance,
            Entrance::findOrFail($data['entrance_id']),
            (int) $data['quantity'],
            $data['issued_by'] ?? '入口工作人员'
        );
    }

    public function count(Request $request, Performance $performance)
    {
        $data = $request->validate([
            'entrance_id' => 'required|integer|exists:entrances,id',
            'remaining_qty' => 'required|integer|min:0',
            'counted_by' => 'sometimes|string|max:50',
            'note' => 'sometimes|nullable|string',
        ]);
        $result = $this->programs->countStock(
            $performance,
            Entrance::findOrFail($data['entrance_id']),
            (int) $data['remaining_qty'],
            $data['counted_by'] ?? '运营',
            $data['note'] ?? null
        );
        return response()->json($result);
    }

    public function close(Performance $performance)
    {
        return response()->json($this->programs->closePerformance($performance));
    }
}
