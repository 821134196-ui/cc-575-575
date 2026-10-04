<?php

namespace App\Console\Commands;

use App\Exceptions\VersionLockedException;
use App\Models\Entrance;
use App\Models\Performance;
use App\Services\Location;
use App\Services\PrinterGateway;
use App\Services\StockService;
use Illuminate\Console\Command;

class TheaterDemo extends Command
{
    protected $signature = 'theater:demo {--json : 以 JSON 输出核对结果}';
    protected $description = '剧院节目单系统全流程演示：锁定后修改、错版报废、入口调拨与库存核对';

    private int $passed = 0;
    private int $failed = 0;

    public function handle(StockService $stock, PrinterGateway $printer): int
    {
        $this->line('');
        $this->line('🎭 剧院节目单印刷与散场余量统计系统 — 全流程演示');
        $this->line(str_repeat('=', 64));

        // 1. 演出与初版节目单
        $p = Performance::create([
            'title' => '话剧《雷雨》（演示场）',
            'performed_at' => now()->addDay()->setTime(19, 30),
            'venue' => '主剧场',
            'status' => 'draft',
        ]);
        $v1 = $p->versions()->create([
            'version_no' => 1,
            'file_name' => 'leiyu-program-v1.pdf',
            'cast_list' => "周朴园：赵雷\n繁漪：钱雨\n周萍：孙风",
            'status' => 'draft',
        ]);
        $this->line("1) 创建演出《{$p->title}》与节目单 v1（草稿）");

        // 2. 锁定待印文件
        $stock->lockVersion($v1, '编辑-周编');
        $this->line('2) 演出信息确认，编辑锁定 v1 → 待印文件固化（file_hash='.$v1->fresh()->file_hash.'）');

        // 3. 锁定后修改 —— 必须被拒绝
        $this->line('');
        $this->line('3) 【规则验证】锁定后尝试修改文件名（旧节目单改名顶替）…');
        try {
            $v1->fresh()->guardEditable();
            $v1->fresh()->update(['file_name' => 'leiyu-program-RENAMED.pdf']);
            $this->check('锁定后修改被拒绝', false, '修改竟然成功了！');
        } catch (VersionLockedException $e) {
            $this->check('锁定后修改被拒绝', true, $e->getMessage());
        }

        // 4. 印刷下单 + 印厂交货（模拟接口回执）
        $this->line('');
        $this->line('4) 下单印刷 500 份，等待印厂交货…');
        $b1 = $stock->placePrintOrder($v1->fresh(), 500);
        $receipt = $printer->fetchReceipt($b1);
        $b1 = $stock->deliver($b1, $receipt);
        $this->line("   回执号 {$b1->printer_receipt_no}，实际交货 {$b1->quantity_delivered} 份（来源：{$receipt['source']}）");

        // 5. 前台分配到入口
        $a = Entrance::where('code', 'A')->first();
        $b = Entrance::where('code', 'B')->first();
        $c = Entrance::where('code', 'C')->first();
        $stock->allocate($b1, $a, 200);
        $stock->allocate($b1, $b, 150);
        $this->line('5) 前台分配：A 入口 200 份、B 入口 150 份，其余留总仓');

        // 6. 入口发放
        $stock->issue($a, $v1->fresh(), 180);
        $stock->issue($b, $v1->fresh(), 95);
        $this->line('6) 开场发放：A 发 180、B 发 95');

        // 7. 演员临时变化 → 新建版本，旧单不得改名
        $this->line('');
        $this->line('7) 演员名单临时变化（繁漪换人）→ 新建 v2，不改动旧文件…');
        $v2 = $stock->createRevision(
            $v1->fresh(),
            '演员名单临时变化：繁漪改由 李梅 饰演',
            ['cast_list' => "周朴园：赵雷\n繁漪：李梅\n周萍：孙风"],
        );
        $v1Now = $v1->fresh();
        $this->check(
            'v1 变为 superseded 且文件名保持 leiyu-program-v1.pdf 不变',
            $v1Now->status === 'superseded' && $v1Now->file_name === 'leiyu-program-v1.pdf',
            "status={$v1Now->status}, file={$v1Now->file_name}",
        );
        $stock->lockVersion($v2, '编辑-周编');
        $b2 = $stock->placePrintOrder($v2->fresh(), 60);
        $b2 = $stock->deliver($b2, $printer->fetchReceipt($b2));
        $stock->allocate($b2, $c, $b2->quantity_delivered);
        $stock->issue($c, $v2->fresh(), 42);
        $this->line("   v2 补印 {$b2->quantity_delivered} 份，全部分配 C 入口，C 发放 42 份");

        // 8. 错版报废：先申请，未审批库存不动；审批后扣减
        $this->line('');
        $this->line('8) 【错版报废】发现 B 入口 v1 有 10 份错版，提交报废申请…');
        $before = $v1->fresh()->locationStock(Location::entrance($b->id));
        $scrap = $stock->requestScrap($v1->fresh(), Location::entrance($b->id), 10, '错版：内页与锁定文件不符');
        $pending = $v1->fresh()->locationStock(Location::entrance($b->id));
        $this->check('报废待审批期间库存不被扣减', $pending === $before, "审批前 B 余量={$pending}");
        $stock->approveScrap($scrap->fresh(), '运营主管-何静', '核实确为错版，同意报废');
        $after = $v1->fresh()->locationStock(Location::entrance($b->id));
        $this->check('审批通过后库存扣减 10 份', $after === $before - 10, "审批后 B 余量={$after}");

        // 已处理的报废单不能重复审批
        $dupOk = true;
        try {
            $stock->approveScrap($scrap->fresh());
            $dupOk = false;
        } catch (\Throwable) {
        }
        $this->check('报废单不能重复审批', $dupOk);

        // 9. 入口调拨：调出/调入成对，总数不变
        $this->line('');
        $this->line('9) 【入口调拨】A 入口向 B 入口调拨 15 份 v1…');
        $sumBefore = $this->onHandSum($stock, $v1->fresh());
        $stock->transfer($v1->fresh(), $a, $b, 15, 'B 口人流超预期');
        $sumAfter = $this->onHandSum($stock, $v1->fresh());
        $this->check('调拨不改变总库存（总仓+入口合计）', $sumBefore === $sumAfter, "调拨前={$sumBefore} 调拨后={$sumAfter}");
        $aNow = $v1->fresh()->locationStock(Location::entrance($a->id));
        $bNow = $v1->fresh()->locationStock(Location::entrance($b->id));
        $this->check('调出/调入数量成对（A-15，B+15）', $aNow === 200 - 180 - 15 && $bNow === 150 - 95 - 10 + 15, "A={$aNow}, B={$bNow}");

        // 超量调出必须失败
        $overOk = true;
        try {
            $stock->transfer($v1->fresh(), $a, $b, 9999);
            $overOk = false;
        } catch (\Throwable) {
        }
        $this->check('超余量调出被拒绝', $overOk);

        // 10. 散场盘点
        $this->line('');
        $this->line('10) 散场盘点：运营清点各库位实际余量…');
        $counts = $stock->snapshotCount($p->fresh());
        foreach ($counts as $cnt) {
            // 模拟：A 口少 1 份（观众多拿），其余账实相符
            $actual = $cnt->location === Location::entrance($a->id) ? max(0, $cnt->system_qty - 1) : $cnt->system_qty;
            $stock->submitActual($cnt->fresh(), $actual, $actual < $cnt->system_qty ? '散场发现少 1 份' : null);
        }
        $this->line('    盘点完成（A 口盘亏 1 份，已以 count_adjust 流水留痕）');

        // 11. 核对
        $this->line('');
        $this->line('11) 库存总数核对：交货总数 = 总仓 + 各入口 + 已发放 + 报废');
        $this->line(str_repeat('-', 64));
        $rows = collect([$stock->reconcile($v1->fresh()), $stock->reconcile($v2->fresh())]);
        $headers = ['版本', '文件', '交货', '总仓', '入口合计', '已发放', '报废', '合计', '差异'];
        $table = $rows->map(fn ($r) => [
            'v'.$r['version_no'],
            $r['file_name'],
            $r['delivered'],
            $r['warehouse'],
            $r['entrance_total'],
            $r['issued'],
            $r['scrapped'],
            $r['accounted'],
            $r['diff'] === 0 ? '✓ 0' : '✗ '.$r['diff'],
        ])->all();
        $this->table($headers, $table);

        $allBalanced = $rows->every(fn ($r) => $r['balanced']);
        $this->check('所有版本库存守恒（差异全部为 0）', $allBalanced);

        // 补印建议：按本场实际结余
        $totalOnHand = $rows->sum('on_hand');
        $this->line('');
        $this->line("12) 补印依据：本场散场实际结余（总仓+入口）= {$totalOnHand} 份");
        $this->line('    下个场次预估 600 份 → '.($totalOnHand < 600 ? "建议补印 ".(600 - $totalOnHand).' 份' : '结余充足，无需补印'));

        $this->line('');
        $this->line(str_repeat('=', 64));
        $this->info("结果：{$this->passed} 项通过，{$this->failed} 项失败");

        if ($this->option('json')) {
            $this->newLine();
            $this->line(json_encode([
                'checks_passed' => $this->passed,
                'checks_failed' => $this->failed,
                'reconcile' => $rows,
                'all_balanced' => $allBalanced,
                'on_hand_total' => $totalOnHand,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        return $this->failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function check(string $name, bool $ok, string $detail = ''): void
    {
        if ($ok) {
            $this->passed++;
            $this->line("  ✅ {$name}".($detail ? "（{$detail}）" : ''));
        } else {
            $this->failed++;
            $this->error("  ❌ {$name}".($detail ? "（{$detail}）" : ''));
        }
    }

    private function onHandSum(StockService $stock, $version): int
    {
        $r = $stock->reconcile($version);

        return $r['on_hand'];
    }
}
