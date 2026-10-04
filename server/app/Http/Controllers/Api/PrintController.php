<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entrance;
use App\Models\PrintBatch;
use App\Models\ProgramVersion;
use App\Services\ProgramService;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    public function __construct(private readonly ProgramService $programs) {}

    public function order(Request $request, ProgramVersion $version)
    {
        $data = $request->validate([
            'ordered_qty' => 'required|integer|min:1',
            'printer_name' => 'sometimes|string|max:100',
        ]);
        return $this->programs->orderPrint(
            $version, (int) $data['ordered_qty'], $data['printer_name'] ?? '市第一印厂'
        );
    }

    /** 印厂交货（本地模拟印厂回执）。 */
    public function deliver(Request $request, PrintBatch $batch)
    {
        $data = $request->validate([
            'delivered_qty' => 'sometimes|integer|min:0',
        ]);
        $result = $this->programs->deliverBatch($batch, isset($data['delivered_qty']) ? (int) $data['delivered_qty'] : null);
        return response()->json([
            'batch' => $result,
            'receipt' => [
                'receipt_no' => $result->receipt_no,
                'delivered_qty' => $result->delivered_qty,
                'delivered_at' => $result->delivered_at,
                'source' => '本地模拟印厂接口',
            ],
        ]);
    }

    public function allocate(Request $request, PrintBatch $batch)
    {
        $data = $request->validate([
            'entrance_id' => 'required|integer|exists:entrances,id',
            'quantity' => 'required|integer|min:1',
            'allocated_by' => 'sometimes|string|max:50',
            'note' => 'sometimes|nullable|string',
        ]);
        return $this->programs->allocate(
            $batch,
            Entrance::findOrFail($data['entrance_id']),
            (int) $data['quantity'],
            $data['allocated_by'] ?? '前台'
        );
    }
}
