<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrintBatch;
use App\Models\ProgramVersion;
use App\Services\PrinterGateway;
use App\Services\StockService;
use Illuminate\Http\Request;

class PrintBatchController extends Controller
{
    public function __construct(
        private readonly StockService $stock,
        private readonly PrinterGateway $printer,
    ) {
    }

    public function store(Request $request, ProgramVersion $version)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'by' => ['nullable', 'string'],
        ]);

        $batch = $this->stock->placePrintOrder($version, (int) $data['quantity'], $data['by'] ?? '编辑部');

        return response()->json(['ok' => true, 'batch' => $batch], 201);
    }

    /** 印厂交货：回执来自本地模拟接口 */
    public function deliver(PrintBatch $batch)
    {
        $receipt = $this->printer->fetchReceipt($batch);
        $batch = $this->stock->deliver($batch, $receipt);

        return response()->json(['ok' => true, 'batch' => $batch, 'receipt' => $receipt]);
    }
}
