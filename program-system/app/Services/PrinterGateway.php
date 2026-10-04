<?php

namespace App\Services;

use App\Models\PrintBatch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 印厂回执网关：通过本地模拟接口获取交货回执。
 * 模拟服务由 routes/api.php 中的 /api/mock/printer/receipt 提供；
 * 若本地服务尚未启动（如纯命令行演示），退回到内置模拟，保证演示可重放。
 */
class PrinterGateway
{
    public function __construct(private readonly string $baseUrl = '')
    {
    }

    public function fetchReceipt(PrintBatch $batch): array
    {
        $url = ($this->baseUrl ?: config('app.url')).'/api/mock/printer/receipt';

        try {
            $resp = Http::timeout(2)->post($url, [
                'batch_no' => $batch->batch_no,
                'quantity_ordered' => $batch->quantity_ordered,
            ]);
            if ($resp->ok() && ($data = $resp->json('data'))) {
                return [
                    'receipt_no' => $data['receipt_no'],
                    'quantity_delivered' => (int) $data['quantity_delivered'],
                    'delivered_at' => $data['delivered_at'] ?? now()->toDateTimeString(),
                    'source' => 'mock-api',
                ];
            }
        } catch (\Throwable $e) {
            Log::info('印厂模拟接口不可达，使用内置模拟：'.$e->getMessage());
        }

        return $this->localFallback($batch);
    }

    private function localFallback(PrintBatch $batch): array
    {
        // 模拟回执：98% 场景按量交货；少量批次少交或缺损，触发补印决策
        $shortfall = $batch->quantity_ordered >= 200 ? 4 : 0;

        return [
            'receipt_no' => 'RCPT-MOCK-'.strtoupper(substr(md5($batch->batch_no), 0, 8)),
            'quantity_delivered' => $batch->quantity_ordered - $shortfall,
            'delivered_at' => now()->toDateTimeString(),
            'source' => 'local-fallback',
        ];
    }
}
