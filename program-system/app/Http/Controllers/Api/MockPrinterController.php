<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * 印厂回执本地模拟接口。
 * 前端/网关 POST {batch_no, quantity_ordered} -> 回执 JSON
 */
class MockPrinterController extends Controller
{
    public function receipt(Request $request)
    {
        $data = $request->validate([
            'batch_no' => ['required', 'string'],
            'quantity_ordered' => ['required', 'integer', 'min:1'],
        ]);

        // 用批次号做确定性模拟：同批次多次查询回执一致
        $seed = hexdec(substr(md5($data['batch_no']), 0, 8));
        $defectRate = ($seed % 10) < 1 ? 0.02 : 0.0; // 10% 的批次有约 2% 缺损
        $delivered = (int) floor($data['quantity_ordered'] * (1 - $defectRate));

        return response()->json([
            'data' => [
                'receipt_no' => 'RCPT-'.strtoupper(substr(md5($data['batch_no']), 0, 10)),
                'batch_no' => $data['batch_no'],
                'quantity_ordered' => (int) $data['quantity_ordered'],
                'quantity_delivered' => $delivered,
                'quality' => $defectRate > 0 ? 'minor_defect_reported' : 'ok',
                'delivered_at' => now()->toDateTimeString(),
            ],
        ]);
    }
}
