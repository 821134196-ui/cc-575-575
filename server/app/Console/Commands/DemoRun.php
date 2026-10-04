<?php

namespace App\Console\Commands;

use App\Exceptions\LockedVersionException;
use App\Models\Entrance;
use App\Models\Performance;
use App\Models\PrintBatch;
use App\Models\Production;
use App\Services\InventoryService;
use App\Services\ProgramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DemoRun extends Command
{
    protected $signature = 'demo:run';
    protected $description = '运行剧院节目单系统端到端演示：锁定后修改、错版报废、入口调拨、库存核对';

    private array $checks = [];

    public function handle(ProgramService $svc, InventoryService $inv): int
    {
        DB::connection()->statement('PRAGMA foreign_keys = ON');

        $this->line('');
        $this->line('================ 剧院节目单印刷与散场余量统计 · 演示 ================');

        /* 0. 清空并重建 */
        $this->call('migrate:fresh', ['--force' => true]);

        /* 1. 基础数据：剧目、入口 */
        $prod = Production::create(['name' => '话剧《长夜》', 'code' => 'CY-2026', 'note' => '秋季巡演']);
        $east = Entrance::create(['name' => '东门', 'code' => 'EAST']);
        $west = Entrance::create(['name' => '西门', 'code' => 'WEST']);
        $vip = Entrance::create(['name' => 'VIP通道', 'code' => 'VIP']);
        $this->line("\n【基础数据】剧目《长夜》，入口：东门、西门、VIP通道");

        /* 2. 节目单 v1：上传待印文件并锁定 */
        $v1 = $svc->createVersion($prod->id, ['label' => '首演初版', 'change_reason' => '建组首演']);
        $svc->updateFile($v1, ['file_name' => 'changye-v1.pdf', 'file_path' => 'program-files/changye-v1.pdf',
            'file_hash' => hash('sha256', 'changye v1 contents'), 'file_size' => 102400]);
        $svc->lockVersion($v1, '编辑小李');
        $this->line("【版本锁定】v1「首演初版」待印文件已锁定（hash {$this->short($v1->file_hash)}）");

        /* 3. 锁定后修改 —— 必须被拒绝 */
        try {
            $svc->updateFile($v1->fresh(), ['file_name' => 'changye-v1-改名顶替.pdf']);
            $this->markFail('锁定后修改未被拦截');
        } catch (LockedVersionException $e) {
            $this->markPass("锁定后修改被拒绝：{$e->getMessage()}");
        }

        /* 4. 演员名单临时变化 → 新建 v2，旧版本仍在 */
        $v2 = $svc->createVersion($prod->id, ['label' => '换角修订版', 'change_reason' => '主演临时更换，演员名单变化']);
        $svc->updateFile($v2, ['file_name' => 'changye-v2.pdf', 'file_path' => 'program-files/changye-v2.pdf',
            'file_hash' => hash('sha256', 'changye v2 contents'), 'file_size' => 104800]);
        $this->markPass("新建版本 v2「换角修订版」；旧 v1 保持原名、不能改名顶替");

        /* 5. 场次安排并确认使用 v2（确认时自动锁定 v2 待印文件） */
        $show = Performance::create([
            'production_id' => $prod->id, 'name' => '10月4日晚场',
            'performed_at' => '2026-10-04 19:30', 'status' => 'scheduled',
        ]);
        $svc->confirmPerformance($show, $v2->id, '编辑小李');
        $this->line("【场次确认】10月4日晚场 → 允许发放 v2「换角修订版」，待印文件锁定");

        /* 6. 印厂订货 + 模拟印厂回执交货 500 份 */
        $b1 = $svc->orderPrint($v2->fresh(), 500);
        $b1 = $svc->deliverBatch($b1->fresh(), 500);
        $this->line("【印厂交货】批次 {$b1->batch_no} 订货 500，回执 {$b1->receipt_no}，实交 {$b1->delivered_qty}");

        // 已交货批次重复登记必须失败
        try {
            $svc->deliverBatch($b1->fresh(), 10);
            $this->markFail('重复交货未被拦截');
        } catch (ValidationException $e) {
            $this->markPass('重复交货被拒绝：' . collect($e->errors())->flatten()->first());
        }

        /* 7. 前台分配：东门 200、西门 200、VIP 60；总库剩 40 */
        $svc->allocate($b1->fresh(), $east, 200, '前台小王');
        $svc->allocate($b1->fresh(), $west, 200, '前台小王');
        $svc->allocate($b1->fresh(), $vip, 60, '前台小王');
        $this->line("【前台分配】东门 200 / 西门 200 / VIP 60，前台总库留存 40");
        $this->expect('分配后账面', $inv->totals($v2->id), [
            'delivered' => 500, 'front_stock' => 40, 'entrance_stock' => 460, 'on_hand' => 500,
            'issued' => 0, 'scrapped' => 0, 'balanced' => true,
        ]);

        /* 8. 入口发放：东门 180、西门 175、VIP 50 */
        $svc->issue($show->fresh(), $east, 180, '东门志愿者');
        $svc->issue($show->fresh(), $west, 175, '西门志愿者');
        $svc->issue($show->fresh(), $vip, 50, 'VIP接待');
        $this->line("【入口发放】东门 180 / 西门 175 / VIP 50，合计 405");

        // 超量发放必须失败（东门剩 20）
        try {
            $svc->issue($show->fresh(), $east, 21);
            $this->markFail('超量发放未被拦截');
        } catch (ValidationException $e) {
            $this->markPass('超量发放被拒绝：' . collect($e->errors())->flatten()->first());
        }

        /* 9. 入口调拨：西门调 10 给东门 —— 调出/调入同时记账，总库存不变 */
        $svc->transfer($v2, $west, $east, 10, '前台小王');
        $this->line("【入口调拨】西门 → 东门 10 份");
        $bd = $inv->stockBreakdown($v2->id);
        $this->assert($bd[$east->id] === 30 && $bd[$west->id] === 15 && $bd['front'] === 40,
            "调拨后：东门 30（20+10）、西门 15（25-10）、总库 40",
            "调拨后实际：东门 {$bd[$east->id]}、西门 {$bd[$west->id]}、总库 {$bd['front']}");
        $recon = $inv->reconciliation();
        $this->assert($recon['transfer_net'] === 0, '调拨净额为 0，总库存不增加',
            "调拨净额 = {$recon['transfer_net']}");

        /* 10. 错版报废：西门发现 5 份错版 → 提交审批 → 主管通过后才扣库存 */
        $scrap = $svc->requestScrap($v2, 5, '错版：误印旧版演员名单', $west->id, '运营小张');
        $pendingTotals = $inv->totals($v2->id);
        $this->assert($pendingTotals['scrapped'] === 0, '待审批报废不扣库存',
            "待审批状态报废数 = {$pendingTotals['scrapped']}");
        $svc->reviewScrap($scrap->fresh(), true, '运营主管老周', '情况属实，同意报废');
        $this->line("【错版报废】西门 5 份错版，审批通过后扣减");
        $this->assert($inv->stockAt($v2->id, $west->id) === 10,
            '西门库存 15 - 5 = 10', "西门库存 = {$inv->stockAt($v2->id, $west->id)}");

        // 再提交一笔报废但驳回 —— 库存不动
        $rej = $svc->requestScrap($v2, 3, '污损待核', $east->id);
        $svc->reviewScrap($rej->fresh(), false, '运营主管老周', '可继续使用，驳回');
        $this->assert($inv->stockAt($v2->id, $east->id) === 30,
            '报废被驳回，东门库存仍为 30', "东门库存 = {$inv->stockAt($v2->id, $east->id)}");

        /* 11. 散场盘点：东门实点 30、西门实点 10、VIP实点 10 */
        $r1 = $svc->countStock($show->fresh(), $east, 30, '运营小张');
        $r2 = $svc->countStock($show->fresh(), $west, 10, '运营小张');
        $r3 = $svc->countStock($show->fresh(), $vip, 10, '运营小张');
        $this->line("【散场盘点】东门实点 30（账 {$r1['book_remaining']}，差异 {$r1['variance']}）"
            ." / 西门 10（账 {$r2['book_remaining']}，差异 {$r2['variance']}）"
            ." / VIP 10（账 {$r3['book_remaining']}，差异 {$r3['variance']}）");

        $summary = $svc->closePerformance($show->fresh());
        $this->line("【补印依据】入库 {$summary['delivered_total']}，发放 {$summary['issued_total']}，"
            ."报废 {$summary['scrapped_total']}，入口结余 {$summary['actual_remaining_at_entrances']}，"
            ."总库 {$summary['front_stock']}，总结余 {$summary['total_remaining']} → {$summary['reprint_reason']}");

        /* 12. 全量核对 */
        $recon = $inv->reconciliation();
        $g = $recon['grand'];
        $this->line("\n【库存核对】交货 {$g['delivered']} = 发放 {$g['issued']} + 报废 {$g['scrapped']} + 剩余 {$g['on_hand']}"
            ."（总库 {$g['front_stock']} + 入口 {$g['entrance_stock']}）");
        $this->assert($g['delivered'] === $g['issued'] + $g['scrapped'] + $g['on_hand'],
            '恒等式成立：入库 = 发放 + 报废 + 剩余', '恒等式不成立');
        $this->assert($recon['internal_movements_conserved'],
            '分配与调拨内部流水净额均为 0（不凭空增减库存）',
            "分配净额 {$recon['allocate_net']}，调拨净额 {$recon['transfer_net']}");
        $this->assert($recon['all_balanced'], '全部版本账实平衡且无负库存', '存在不平衡版本或负库存');

        /* 汇总 */
        $this->line("\n================ 核对结果 ================");
        $pass = count(array_filter($this->checks, fn ($c) => $c[0]));
        $fail = count($this->checks) - $pass;
        foreach ($this->checks as [$ok, $msg]) {
            $this->line(($ok ? '  ✓ ' : '  ✗ ') . $msg);
        }
        $this->line("\n通过 {$pass} 项，失败 {$fail} 项");
        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function expect(string $name, array $totals, array $want): void
    {
        foreach ($want as $k => $v) {
            $actual = $totals[$k];
            $this->assert($actual === $v, "{$name}：{$k} = " . var_export($v, true),
                "{$name}：{$k} 期望 " . var_export($v, true) . "，实际 " . var_export($actual, true));
        }
    }

    private function assert(bool $cond, string $okMsg, string $failMsg): void
    {
        $this->checks[] = [$cond, $cond ? $okMsg : $failMsg];
    }

    private function markPass(string $msg): void { $this->checks[] = [true, $msg]; $this->line('  ✓ ' . $msg); }
    private function markFail(string $msg): void { $this->checks[] = [false, $msg]; $this->line('  ✗ ' . $msg); }
    private function short(?string $h): string { return $h ? substr($h, 0, 12) . '…' : '-'; }
}
