<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Production;
use App\Models\ProgramVersion;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Services\ProgramService;
use Illuminate\Http\Request;

class VersionController extends Controller
{
    public function __construct(
        private readonly ProgramService $programs,
        private readonly InventoryService $inventory,
    ) {}

    public function store(Request $request, Production $production)
    {
        $data = $request->validate([
            'label' => 'required|string|max:100',
            'change_reason' => 'nullable|string|max:255',
        ]);
        $version = $this->programs->createVersion($production->id, $data);
        return $version;
    }

    public function updateFile(Request $request, ProgramVersion $version)
    {
        // 锁定后修改会抛 VERSION_LOCKED（422），由全局异常渲染返回。
        $data = $request->validate([
            'label' => 'sometimes|string|max:100',
            'change_reason' => 'sometimes|nullable|string|max:255',
            'file_name' => 'sometimes|string|max:255',
            'file' => 'sometimes|file|max:20480',
        ]);
        return $this->programs->updateFile($version, $data, $request->file('file'));
    }

    public function lock(Request $request, ProgramVersion $version)
    {
        $by = $request->input('by', '编辑');
        return $this->programs->lockVersion($version, $by);
    }

    public function inventory(ProgramVersion $version)
    {
        return response()->json([
            'version' => $version,
            'breakdown' => $this->inventory->stockBreakdown($version->id),
            'totals' => $this->inventory->totals($version->id),
        ]);
    }

    public function movements(ProgramVersion $version)
    {
        return StockMovement::where('program_version_id', $version->id)
            ->with('entrance')
            ->orderBy('id')
            ->get();
    }
}
