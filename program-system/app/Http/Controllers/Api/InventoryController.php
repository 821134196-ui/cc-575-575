<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entrance;
use App\Models\Performance;
use App\Models\PrintBatch;
use App\Models\ProgramVersion;
use App\Models\ScrapRequest;
use App\Models\StockCount;
use App\Services\Location;
use App\Services\StockService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(private readonly StockService $stock)
    {
    }

    /** 前台分配：某批次 → 入口 */
    public function allocate(Request $request, PrintBatch $batch)
    {
        $data = $request->validate([
            'entrance_id' => ['required', 'exists:entrances,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'by' => ['nullable', 'string'],
        ]);

        $allocation = $this->stock->allocate(
            $batch,
            Entrance::findOrFail($data['entrance_id']),
            (int) $data['quantity'],
            $data['by'] ?? '前台',
        );

        return response()->json(['ok' => true, 'allocation' => $allocation->load('entrance')], 201);
    }

    /** 入口发放给观众 */
    public function issue(Request $request, Entrance $entrance)
    {
        $data = $request->validate([
            'program_version_id' => ['required', 'exists:program_versions,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'by' => ['nullable', 'string'],
        ]);

        $issue = $this->stock->issue(
            $entrance,
            ProgramVersion::findOrFail($data['program_version_id']),
            (int) $data['quantity'],
            $data['by'] ?? '入口工作人员',
        );

        return response()->json(['ok' => true, 'issue' => $issue], 201);
    }

    /** 入口调拨：调出/调入成对记录，不增加总库存 */
    public function transfer(Request $request)
    {
        $data = $request->validate([
            'program_version_id' => ['required', 'exists:program_versions,id'],
            'from_entrance_id' => ['required', 'different:to_entrance_id', 'exists:entrances,id'],
            'to_entrance_id' => ['required', 'exists:entrances,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string'],
            'by' => ['nullable', 'string'],
        ]);

        $transfer = $this->stock->transfer(
            ProgramVersion::findOrFail($data['program_version_id']),
            Entrance::findOrFail($data['from_entrance_id']),
            Entrance::findOrFail($data['to_entrance_id']),
            (int) $data['quantity'],
            $data['reason'] ?? null,
            $data['by'] ?? '前台',
        );

        return response()->json(['ok' => true, 'transfer' => $transfer->load('fromEntrance', 'toEntrance')], 201);
    }

    /** 提交错版报废申请 */
    public function requestScrap(Request $request)
    {
        $data = $request->validate([
            'program_version_id' => ['required', 'exists:program_versions,id'],
            'print_batch_id' => ['nullable', 'exists:print_batches,id'],
            'location' => ['required', 'string'], // warehouse 或 entrance:{id}
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:200'],
            'by' => ['nullable', 'string'],
        ]);

        $req = $this->stock->requestScrap(
            ProgramVersion::findOrFail($data['program_version_id']),
            $data['location'],
            (int) $data['quantity'],
            $data['reason'],
            isset($data['print_batch_id']) ? PrintBatch::find($data['print_batch_id']) : null,
            $data['by'] ?? '运营',
        );

        return response()->json(['ok' => true, 'scrap' => $req], 201);
    }

    public function approveScrap(ScrapRequest $scrapRequest, Request $request)
    {
        $req = $this->stock->approveScrap(
            $scrapRequest,
            $request->input('by', '运营主管'),
            $request->input('note'),
        );

        return response()->json(['ok' => true, 'scrap' => $req]);
    }

    public function rejectScrap(ScrapRequest $scrapRequest, Request $request)
    {
        $req = $this->stock->rejectScrap(
            $scrapRequest,
            $request->input('by', '运营主管'),
            $request->input('note'),
        );

        return response()->json(['ok' => true, 'scrap' => $req]);
    }

    public function scrapIndex()
    {
        return ScrapRequest::query()
            ->with('version:id,version_no,file_name,performance_id')
            ->latest('id')
            ->get()
            ->map(fn ($s) => $this->scrapJson($s));
    }

    /** 散场盘点：生成/获取账面快照 */
    public function countSnapshot(Performance $performance, Request $request)
    {
        $records = $this->stock->snapshotCount($performance, $request->input('by', '运营'));

        return response()->json(['ok' => true, 'counts' => $records]);
    }

    /** 录入散场实际余量 */
    public function countSubmit(StockCount $stockCount, Request $request)
    {
        $data = $request->validate([
            'actual_qty' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        $adjust = $this->stock->submitActual($stockCount, (int) $data['actual_qty'], $data['note'] ?? null);
        $stockCount->performance()->update(['status' => 'settled']);

        return response()->json([
            'ok' => true,
            'count' => $stockCount->fresh(),
            'adjustment' => $adjust ? ['id' => $adjust->id, 'type' => $adjust->type, 'quantity' => $adjust->quantity] : null,
        ]);
    }

    private function scrapJson(ScrapRequest $s): array
    {
        $location = $s->location;
        if (str_starts_with($location, 'entrance:')) {
            $entrance = Entrance::find((int) substr($location, 9));
            $locationLabel = $entrance ? "{$entrance->code} {$entrance->name}" : Location::label($location);
        } else {
            $locationLabel = Location::label($location);
        }

        return [
            'id' => $s->id,
            'scrap_no' => $s->scrap_no,
            'program_version_id' => $s->program_version_id,
            'version_label' => $s->version ? 'v'.$s->version->version_no.' '.$s->version->file_name : null,
            'print_batch_id' => $s->print_batch_id,
            'location' => $s->location,
            'location_label' => $locationLabel,
            'quantity' => $s->quantity,
            'reason' => $s->reason,
            'status' => $s->status,
            'requested_by' => $s->requested_by,
            'approved_by' => $s->approved_by,
            'approved_at' => $s->approved_at?->toDateTimeString(),
            'approval_note' => $s->approval_note,
            'created_at' => $s->created_at->toDateTimeString(),
        ];
    }
}
