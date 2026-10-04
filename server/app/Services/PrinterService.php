<?php

namespace App\Services;

/**
 * 印厂回执：本地模拟接口（不依赖外部网络）。
 * 交货时由本服务生成回执单，真实环境替换为 HTTP 客户端调用印厂系统即可。
 */
class PrinterService
{
    public function receive(string $printerName, string $batchNo, int $orderedQty, int $deliveredQty): array
    {
        // 模拟印厂偶尔短交的回执（演示数据由调用方决定实际交货数量，
        // 这里只负责颁发回执凭证）。
        return [
            'printer_name' => $printerName,
            'batch_no' => $batchNo,
            'ordered_qty' => $orderedQty,
            'delivered_qty' => $deliveredQty,
            'short_qty' => max(0, $orderedQty - $deliveredQty),
            'receipt_no' => sprintf('RCP-%s-%s', strtoupper(substr(md5($printerName . $batchNo . $orderedQty), 0, 8)), date('md')),
            'received_at' => now()->toDateTimeString(),
            'acknowledged' => true,
        ];
    }
}
