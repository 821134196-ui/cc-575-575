<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrintBatch;

/**
 * 印厂本地模拟接口：查询已交货批次的印厂回执。
 * 交货登记时 PrinterService（模拟印厂适配器）生成同样的回执凭证。
 */
class MockPrinterController extends Controller
{
    public function receipts()
    {
        return response()->json([
            'service' => '本地模拟印厂接口',
            'printer' => '市第一印厂（模拟）',
            'receipts' => PrintBatch::query()
                ->whereNotNull('receipt_no')
                ->with('programVersion.production')
                ->orderByDesc('delivered_at')
                ->get()
                ->map(fn (PrintBatch $b) => [
                    'receipt_no' => $b->receipt_no,
                    'batch_no' => $b->batch_no,
                    'production' => $b->programVersion->production->name,
                    'version' => "v{$b->programVersion->version_no} {$b->programVersion->label}",
                    'ordered_qty' => $b->ordered_qty,
                    'delivered_qty' => $b->delivered_qty,
                    'short_qty' => max(0, $b->ordered_qty - $b->delivered_qty),
                    'delivered_at' => $b->delivered_at,
                    'acknowledged' => true,
                ]),
        ]);
    }
}
