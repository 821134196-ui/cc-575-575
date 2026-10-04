<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Exceptions\InsufficientStockException;
use App\Models\Allocation;
use App\Models\Entrance;
use App\Models\Issue;
use App\Models\PrintBatch;
use App\Models\ProgramVersion;
use App\Models\ScrapRequest;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\Transfer;
use Illuminate\Support\Facades\DB;

class StockService
{
    // ---------- 版本：锁定与修订 ----------

    public function lockVersion(ProgramVersion $version, string $by = '编辑'): ProgramVersion
    {
        if ($version->status !== 'draft') {
            throw new DomainRuleException("版本 v{$version->version_no} 当前状态为 {$version->status}，只有草稿可以锁定。");
        }
        $version->update([
            'status' => 'locked',
            'locked_at' => now(),
            'locked_by' => $by,
            'file_hash' => $version->file_hash ?: $this->fakeHash($version),
        ]);
        $version->performance()->update(['status' => 'confirmed']);

        return $version;
    }

    /**
     * 演员名单临时变化 / 增加场次：从已锁定版本新建草稿版本。
     * 旧版本置为 superseded，旧节目单保持原文件名不变，不能改名顶替。
     */
    public function createRevision(ProgramVersion $current, string $reason, array $changes = []): ProgramVersion
    {
        return DB::transaction(function () use ($current, $reason, $changes) {
            $locked = ProgramVersion::lockForUpdate()->find($current->id);
            if ($locked->status !== 'locked') {
                throw new DomainRuleException("只有当前锁定版本才能派生出新版本（当前：{$locked->status}）。");
            }
            $locked->update(['status' => 'superseded']);

            return ProgramVersion::create([
                'performance_id' => $locked->performance_id,
                'source_version_id' => $locked->id,
                'version_no' => $locked->version_no + 1,
                'file_name' => $changes['file_name']
                    ?? $this->bumpFileName($locked->file_name, $locked->version_no + 1),
                'file_hash' => null,
                'cast_list' => $changes['cast_list'] ?? $locked->cast_list,
                'change_reason' => $reason,
                'status' => 'draft',
            ]);
        });
    }

    private function bumpFileName(string $name, int $v): string
    {
        $base = preg_replace('/[-_]?v?\d+$/', '', pathinfo($name, PATHINFO_FILENAME));
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        return $base."-v{$v}".($ext ? ".{$ext}" : '');
    }

    private function fakeHash(ProgramVersion $v): string
    {
        return substr(hash('sha256', $v->file_name.'|'.$v->cast_list.'|'.$v->id), 0, 16);
    }

    // ---------- 印刷：下单 / 交货（印厂回执） ----------

    public function placePrintOrder(ProgramVersion $version, int $qty, string $by = '编辑部'): PrintBatch
    {
        if ($version->status !== 'locked') {
            throw new DomainRuleException('只有已锁定的版本才能下单印刷，草稿请先锁定。');
        }
        if ($qty <= 0) {
            throw new DomainRuleException('印量必须大于 0。');
        }

        return PrintBatch::create([
            'program_version_id' => $version->id,
            'batch_no' => $this->nextNo('PB', PrintBatch::class, 'batch_no'),
            'quantity_ordered' => $qty,
            'status' => 'ordered',
            'ordered_by' => $by,
        ]);
    }

    public function deliver(PrintBatch $batch, array $receipt): PrintBatch
    {
        return DB::transaction(function () use ($batch, $receipt) {
            $batch = PrintBatch::lockForUpdate()->find($batch->id);
            if ($batch->status === 'delivered') {
                throw new DomainRuleException("批次 {$batch->batch_no} 已交货，不能重复登记。");
            }
            $qty = (int) ($receipt['quantity_delivered'] ?? $batch->quantity_ordered);
            if ($qty < 0 || $qty > $batch->quantity_ordered + 100) {
                throw new DomainRuleException("回执交货量异常：{$qty}");
            }

            $batch->update([
                'quantity_delivered' => $qty,
                'status' => 'delivered',
                'printer_receipt_no' => $receipt['receipt_no'] ?? null,
                'delivered_at' => now(),
            ]);

            $this->move(
                versionId: $batch->program_version_id,
                batchId: $batch->id,
                type: 'print_in',
                from: null,
                to: Location::WAREHOUSE,
                qty: $qty,
                refType: PrintBatch::class,
                refId: $batch->id,
                operator: '印厂回执',
            );

            return $batch;
        });
    }

    // ---------- 前台分配到入口（入口领用） ----------

    public function allocate(PrintBatch $batch, Entrance $entrance, int $qty, string $by = '前台'): Allocation
    {
        return DB::transaction(function () use ($batch, $entrance, $qty, $by) {
            if ($qty <= 0) {
                throw new DomainRuleException('分配数量必须大于 0。');
            }
            $available = $this->batchWarehouseStock($batch->id);
            if ($available < $qty) {
                throw new InsufficientStockException(
                    "批次 {$batch->batch_no} 总仓可分配数量不足：需要 {$qty}，剩余 {$available}。"
                );
            }

            $allocation = Allocation::create([
                'print_batch_id' => $batch->id,
                'program_version_id' => $batch->program_version_id,
                'entrance_id' => $entrance->id,
                'quantity' => $qty,
                'allocated_by' => $by,
            ]);

            $this->move(
                versionId: $batch->program_version_id,
                batchId: $batch->id,
                type: 'allocate',
                from: Location::WAREHOUSE,
                to: Location::entrance($entrance->id),
                qty: $qty,
                refType: Allocation::class,
                refId: $allocation->id,
                operator: $by,
            );

            return $allocation;
        });
    }

    // ---------- 入口发放 ----------

    public function issue(Entrance $entrance, ProgramVersion $version, int $qty, string $by = '入口工作人员'): Issue
    {
        return DB::transaction(function () use ($entrance, $version, $qty, $by) {
            if ($version->status === 'draft') {
                throw new DomainRuleException('草稿版本不允许发放。');
            }
            if ($qty <= 0) {
                throw new DomainRuleException('发放数量必须大于 0。');
            }
            $loc = Location::entrance($entrance->id);
            $available = $this->locationStock($version->id, $loc);
            if ($available < $qty) {
                throw new InsufficientStockException(
                    "入口 {$entrance->code} 的 v{$version->version_no} 剩余 {$available} 份，不足以发放 {$qty} 份。"
                );
            }

            $issue = Issue::create([
                'entrance_id' => $entrance->id,
                'program_version_id' => $version->id,
                'quantity' => $qty,
                'issued_by' => $by,
                'issued_at' => now(),
            ]);

            $this->move(
                versionId: $version->id,
                batchId: null,
                type: 'issue',
                from: $loc,
                to: Location::AUDIENCE,
                qty: $qty,
                refType: Issue::class,
                refId: $issue->id,
                operator: $by,
            );

            return $issue;
        });
    }

    // ---------- 入口间调拨：调出/调入成对，总数不变 ----------

    public function transfer(
        ProgramVersion $version,
        Entrance $from,
        Entrance $to,
        int $qty,
        string $reason = null,
        string $by = '前台',
    ): Transfer {
        if ($from->id === $to->id) {
            throw new DomainRuleException('调入入口与调出入口不能相同。');
        }

        return DB::transaction(function () use ($version, $from, $to, $qty, $reason, $by) {
            if ($qty <= 0) {
                throw new DomainRuleException('调拨数量必须大于 0。');
            }
            $fromLoc = Location::entrance($from->id);
            $available = $this->locationStock($version->id, $fromLoc);
            if ($available < $qty) {
                throw new InsufficientStockException(
                    "入口 {$from->code} 余量 {$available}，不足以调出 {$qty}。"
                );
            }

            $transfer = Transfer::create([
                'transfer_no' => $this->nextNo('TR', Transfer::class, 'transfer_no'),
                'program_version_id' => $version->id,
                'from_entrance_id' => $from->id,
                'to_entrance_id' => $to->id,
                'quantity' => $qty,
                'operator' => $by,
                'reason' => $reason,
            ]);

            // 调出
            $this->move(
                versionId: $version->id,
                batchId: null,
                type: 'transfer_out',
                from: $fromLoc,
                to: null,
                qty: $qty,
                refType: Transfer::class,
                refId: $transfer->id,
                operator: $by,
            );
            // 调入
            $this->move(
                versionId: $version->id,
                batchId: null,
                type: 'transfer_in',
                from: null,
                to: Location::entrance($to->id),
                qty: $qty,
                refType: Transfer::class,
                refId: $transfer->id,
                operator: $by,
            );

            return $transfer;
        });
    }

    // ---------- 错版报废：审批 ----------

    public function requestScrap(
        ProgramVersion $version,
        string $location,
        int $qty,
        string $reason,
        ?PrintBatch $batch = null,
        string $by = '运营',
    ): ScrapRequest {
        if ($qty <= 0) {
            throw new DomainRuleException('报废数量必须大于 0。');
        }
        if ($this->locationStock($version->id, $location) < $qty) {
            throw new InsufficientStockException('该库位余量不足以报废 '.$qty.' 份。');
        }

        return ScrapRequest::create([
            'scrap_no' => $this->nextNo('SC', ScrapRequest::class, 'scrap_no'),
            'program_version_id' => $version->id,
            'print_batch_id' => $batch?->id,
            'location' => $location,
            'quantity' => $qty,
            'reason' => $reason,
            'status' => 'pending',
            'requested_by' => $by,
        ]);
    }

    public function approveScrap(ScrapRequest $req, string $by = '运营主管', string $note = null): ScrapRequest
    {
        return DB::transaction(function () use ($req, $by, $note) {
            $req = ScrapRequest::lockForUpdate()->find($req->id);
            if ($req->status !== 'pending') {
                throw new DomainRuleException("报废单 {$req->scrap_no} 已处理（{$req->status}），不能重复审批。");
            }
            if ($this->locationStock($req->program_version_id, $req->location) < $req->quantity) {
                throw new InsufficientStockException('审批时库位余量已不足，无法报废。');
            }

            $req->update([
                'status' => 'approved',
                'approved_by' => $by,
                'approved_at' => now(),
                'approval_note' => $note,
            ]);

            $this->move(
                versionId: $req->program_version_id,
                batchId: $req->print_batch_id,
                type: 'scrap',
                from: $req->location,
                to: Location::SCRAPPED,
                qty: $req->quantity,
                refType: ScrapRequest::class,
                refId: $req->id,
                operator: $by,
            );

            return $req;
        });
    }

    public function rejectScrap(ScrapRequest $req, string $by = '运营主管', string $note = null): ScrapRequest
    {
        if ($req->status !== 'pending') {
            throw new DomainRuleException("报废单 {$req->scrap_no} 已处理，不能重复审批。");
        }
        $req->update([
            'status' => 'rejected',
            'approved_by' => $by,
            'approved_at' => now(),
            'approval_note' => $note ?: '驳回，库存不做变动。',
        ]);

        return $req;
    }

    // ---------- 散场盘点 ----------

    /**
     * 按场次生成盘点记录（账面数量快照），返回待录入实际数量的记录列表。
     */
    public function snapshotCount(\App\Models\Performance $performance, string $by = '运营'): array
    {
        return DB::transaction(function () use ($performance, $by) {
            $records = [];
            foreach ($performance->versions()->whereIn('status', ['locked', 'superseded'])->get() as $version) {
                foreach ($version->stockByLocation() as $loc => $sysQty) {
                    if (in_array($loc, [Location::AUDIENCE, Location::SCRAPPED], true)) {
                        continue; // 只盘点还在仓/入口的实物
                    }
                    $count = StockCount::firstOrCreate(
                        [
                            'performance_id' => $performance->id,
                            'program_version_id' => $version->id,
                            'location' => $loc,
                        ],
                        [
                            'system_qty' => $sysQty,
                            'actual_qty' => $sysQty,
                            'counted_by' => $by,
                            'counted_at' => now(),
                        ],
                    );
                    $records[] = $count;
                }
            }

            return $records;
        });
    }

    /** 录入实际数量；与账面差异以 count_adjust 流水调整，使账实相符并留痕 */
    public function submitActual(StockCount $count, int $actualQty, string $note = null): StockMovement|false
    {
        return DB::transaction(function () use ($count, $actualQty, $note) {
            $count = StockCount::lockForUpdate()->find($count->id);
            $system = (int) $count->system_qty;
            $delta = $actualQty - $system;

            $count->update([
                'actual_qty' => $actualQty,
                'note' => $note,
                'counted_at' => now(),
            ]);

            if ($delta === 0) {
                return false;
            }

            $loc = $count->location;
            // 盘亏：实物减少；盘盈：实物增加（均走调整流水，不凭空捏造业务来源）
            if ($delta < 0) {
                return $this->move(
                    versionId: $count->program_version_id,
                    batchId: null,
                    type: 'count_adjust',
                    from: $loc,
                    to: Location::SCRAPPED,
                    qty: -$delta,
                    refType: StockCount::class,
                    refId: $count->id,
                    operator: $count->counted_by,
                );
            }

            return $this->move(
                versionId: $count->program_version_id,
                batchId: null,
                type: 'count_adjust',
                from: null,
                to: $loc,
                qty: $delta,
                refType: StockCount::class,
                refId: $count->id,
                operator: $count->counted_by,
            );
        });
    }

    // ---------- 核对 ----------

    /**
     * 每个版本：交货总数 = 总仓 + 各入口 + 已发放 + 已报废（含盘点调整）。
     */
    public function reconcile(ProgramVersion $version): array
    {
        $stock = $version->stockByLocation();
        $delivered = (int) $version->printBatches()
            ->where('status', 'delivered')->sum('quantity_delivered');

        $warehouse = $stock[Location::WAREHOUSE] ?? 0;
        $entrances = [];
        $entranceTotal = 0;
        foreach ($stock as $loc => $qty) {
            if (str_starts_with($loc, 'entrance:')) {
                $entrances[$loc] = $qty;
                $entranceTotal += $qty;
            }
        }
        $issued = $stock[Location::AUDIENCE] ?? 0;
        $scrapped = $stock[Location::SCRAPPED] ?? 0;
        $onHand = $warehouse + $entranceTotal;
        $accounted = $onHand + $issued + $scrapped;

        return [
            'version_id' => $version->id,
            'version_no' => $version->version_no,
            'file_name' => $version->file_name,
            'delivered' => $delivered,
            'warehouse' => $warehouse,
            'entrances' => $entrances,
            'entrance_total' => $entranceTotal,
            'issued' => $issued,
            'scrapped' => $scrapped,
            'on_hand' => $onHand,
            'accounted' => $accounted,
            'diff' => $delivered - $accounted,
            'balanced' => $delivered === $accounted,
        ];
    }

    // ---------- 内部 ----------

    private function move(
        int $versionId,
        ?int $batchId,
        string $type,
        ?string $from,
        ?string $to,
        int $qty,
        string $refType,
        int $refId,
        string $operator,
    ): StockMovement {
        return StockMovement::create([
            'program_version_id' => $versionId,
            'print_batch_id' => $batchId,
            'type' => $type,
            'location_from' => $from,
            'location_to' => $to,
            'quantity' => $qty,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'operator' => $operator,
        ]);
    }

    private function locationStock(int $versionId, string $location): int
    {
        $in = (int) StockMovement::where('program_version_id', $versionId)
            ->where('location_to', $location)->sum('quantity');
        $out = (int) StockMovement::where('program_version_id', $versionId)
            ->where('location_from', $location)->sum('quantity');

        return $in - $out;
    }

    private function batchWarehouseStock(int $batchId): int
    {
        $in = (int) StockMovement::where('print_batch_id', $batchId)
            ->where('location_to', Location::WAREHOUSE)->sum('quantity');
        $out = (int) StockMovement::where('print_batch_id', $batchId)
            ->where('location_from', Location::WAREHOUSE)->sum('quantity');

        return $in - $out;
    }

    private function nextNo(string $prefix, string $model, string $column): string
    {
        $last = $model::query()->latest('id')->value('id') ?? 0;

        return sprintf('%s-%s-%04d', $prefix, now()->format('md'), $last + 1);
    }
}
