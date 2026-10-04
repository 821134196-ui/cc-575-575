<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProgramVersion;
use App\Models\Scrap;
use App\Services\ProgramService;
use Illuminate\Http\Request;

class ScrapController extends Controller
{
    public function __construct(private readonly ProgramService $programs) {}

    public function index(Request $request)
    {
        return Scrap::with(['programVersion.production', 'entrance', 'printBatch'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
            ->orderByDesc('id')
            ->get();
    }

    public function store(Request $request, ProgramVersion $version)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
            'entrance_id' => 'sometimes|nullable|integer|exists:entrances,id',
            'requested_by' => 'sometimes|string|max:50',
        ]);

        return $this->programs->requestScrap(
            $version,
            (int) $data['quantity'],
            $data['reason'],
            $data['entrance_id'] ?? null,
            $data['requested_by'] ?? '运营'
        );
    }

    public function review(Request $request, Scrap $scrap)
    {
        $data = $request->validate([
            'approve' => 'required|boolean',
            'reviewed_by' => 'sometimes|string|max:50',
            'review_note' => 'sometimes|nullable|string',
        ]);

        return $this->programs->reviewScrap(
            $scrap,
            (bool) $data['approve'],
            $data['reviewed_by'] ?? '运营主管',
            $data['review_note'] ?? null
        );
    }
}
