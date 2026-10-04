<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entrance;
use App\Models\ProgramVersion;
use App\Services\ProgramService;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(private readonly ProgramService $programs) {}

    public function store(Request $request, ProgramVersion $version)
    {
        $data = $request->validate([
            'from_entrance_id' => 'required|integer|different:to_entrance_id|exists:entrances,id',
            'to_entrance_id' => 'required|integer|exists:entrances,id',
            'quantity' => 'required|integer|min:1',
            'transferred_by' => 'sometimes|string|max:50',
            'note' => 'sometimes|nullable|string',
        ]);

        return $this->programs->transfer(
            $version,
            Entrance::findOrFail($data['from_entrance_id']),
            Entrance::findOrFail($data['to_entrance_id']),
            (int) $data['quantity'],
            $data['transferred_by'] ?? '前台'
        );
    }
}
