<?php

namespace App\Services;

use App\Exceptions\LockedVersionException;
use App\Models\Allocation;
use App\Models\Entrance;
use App\Models\Issue;
use App\Models\Performance;
use App\Models\PrintBatch;
use App\Models\ProgramVersion;
use App\Models\Scrap;
use App\Models\StockCount;
use App\Models\Transfer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProgramService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly PrinterService $printer,
    ) {}

    /* ---------------- 版本与文件 ---------------- */

    public function createVersion(int $productionId, array $data): ProgramVersion
    {
        $nextNo = (int) ProgramVersion::where('production_id', $productionId)->max('version_no') + 1;
        return ProgramVersion::create([
            'production_id' => $productionId,
            'version_no' => $nextNo,
            'label' => $data['label'] ?? "v{$nextNo}",
            'change_reason' => $data['change_reason'] ?? null,
            'status' => 'draft',
        ]);
    }

    /**
     * 更新待印文件信息。
     * 已锁定版本一律拒绝——旧版本不能改名顶替。
     */
    public function updateFile(ProgramVersion $version, array $data, ?UploadedFile $file = null): ProgramVersion
    {
        $version->assertEditable();

        if ($file) {
            $contents = file_get_contents($file->getRealPath());
            $path = $file->store('program-files');
            $data['file_path'] = $path;
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_hash'] = hash('sha256', $contents);
            $data['file_size'] = strlen($contents);
        }

        $version->fill(array_filter(
            ['file_name' => $data['file_name'] ?? null,
             'file_path' => $data['file_path'] ?? null,
             'file_hash' => $data['file_hash'] ?? null,
             'file_size' => $data['file_size'] ?? null,
             'label' => $data['label'] ?? null,
             'change_reason' => $data['change_reason'] ?? null],
            fn ($v) => $v !== null
        ))->save();

        return $version;
    }

    /** 演出信息确认后锁定待印文件。 */
    public function lockVersion(ProgramVersion $version, string $by = '编辑'): ProgramVersion
    {
        if (! $version->file_path && ! $version->file_name) {
            throw ValidationException::withMessages(['file' => '待印文件尚未上传，不能锁定。']);
        }
        if (! $version->isLocked()) {
            $version->update([
                'status' => 'locked',
                'locked_at' => now(),
                'locked_by' => $by,
            ]);
        }
        return $version;
    }

    /* ---------------- 场次 ---------------- */

    /** 确认场次：锁定版本待印文件，并确定为本场允许发放的唯一版本。 */
    public function confirmPerformance(Performance $performance, int $versionId, string $by = '编辑'): Performance
    {
        if ($performance->status !== 'scheduled') {
            throw ValidationException::withMessages([
                'performance' => '演出信息已确认，允许发放的版本不能更换；如需变更请新建版本并另开场次。',
            ]);
        }
        $version = ProgramVersion::findOrFail($versionId);
        $this->lockVersion($version, $by);

        $performance->update([
            'program_version_id' => $version->id,
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'confirmed_by' => $by,
        ]);
        return $performance->load('programVersion');
    }

    /* ---------------- 印厂交货 ---------------- */

    public function orderPrint(ProgramVersion $version, int $qty, string $printerName = '市第一印厂'): PrintBatch
    {
        if (! $version->isLocked()) {
            throw ValidationException::withMessages(['version' => '待印文件尚未锁定，不能下单印刷。']);
        }
        if ($qty <= 0) {
            throw ValidationException::withMessages(['ordered_qty' => '订货数量必须大于 0。']);
        }
        $nextNo = 'B' . str_pad((string) ((int) PrintBatch::where('program_version_id', $version->id)->count() + 1), 3, '0', STR_PAD_LEFT);

        return PrintBatch::create([
            'program_version_id' => $version->id,
            'batch_no' => $nextNo,
            'ordered_qty' => $qty,
            'printer_name' => $printerName,
            'status' => 'ordered',
        ]);
    }

    /**
     * 印厂交货（回执来自本地模拟印厂接口），数量入前台总库。
     */
    public function deliverBatch(PrintBatch $batch, ?int $deliveredQty = null): PrintBatch
    {
        if ($batch->status === 'delivered') {
            throw ValidationException::withMessages(['batch' => "批次 {$batch->batch_no} 已交货，不能重复登记。"]);
        }
        $deliveredQty ??= $batch->ordered_qty;
        if ($deliveredQty < 0 || $deliveredQty > $batch->ordered_qty) {
            throw ValidationException::withMessages(['delivered_qty' => '交货数量必须介于 0 与订货数量之间。']);
        }

        return DB::transaction(function () use ($batch, $deliveredQty) {
            $receipt = $this->printer->receive(
                $batch->printer_name, $batch->batch_no, $batch->ordered_qty, $deliveredQty
            );

            $batch->update([
                'delivered_qty' => $deliveredQty,
                'receipt_no' => $receipt['receipt_no'],
                'delivered_at' => now(),
                'status' => $deliveredQty > 0 ? 'delivered' : 'ordered',
            ]);

            if ($deliveredQty > 0) {
                $this->inventory->move(
                    $batch->program_version_id, null, $deliveredQty,
                    'deliver', $batch, "印厂回执 {$receipt['receipt_no']}"
                );
            }
            return $batch;
        });
    }

    /* ---------------- 前台分配 ---------------- */

    /** 从某批次（前台总库）分配给入口领用。 */
    public function allocate(PrintBatch $batch, Entrance $entrance, int $qty, string $by = '前台'): Allocation
    {
        if ($batch->status !== 'delivered') {
            throw ValidationException::withMessages(['batch' => '批次尚未交货，不能分配。']);
        }
        $this->assertPositive($qty);

        return DB::transaction(function () use ($batch, $entrance, $qty, $by) {
            $this->assertFrontStock($batch->program_version_id, $qty);

            $allocation = Allocation::create([
                'print_batch_id' => $batch->id,
                'entrance_id' => $entrance->id,
                'quantity' => $qty,
                'allocated_by' => $by,
            ]);

            $this->inventory->move($batch->program_version_id, null, -$qty, 'allocate_out', $allocation, "分配至{$entrance->name}");
            $this->inventory->move($batch->program_version_id, $entrance->id, $qty, 'allocate_in', $allocation, "领自前台 {$batch->batch_no}");

            return $allocation->load('entrance', 'printBatch');
        });
    }

    /* ---------------- 入口发放 ---------------- */

    public function issue(Performance $performance, Entrance $entrance, int $qty, string $by = '入口工作人员'): Issue
    {
        if ($performance->status === 'scheduled') {
            throw ValidationException::withMessages(['performance' => '演出尚未确认允许发放的版本。']);
        }
        if ($performance->isClosed()) {
            throw ValidationException::withMessages(['performance' => '本场已散场盘点，不能再发放。']);
        }
        $this->assertPositive($qty);
        $versionId = $performance->program_version_id;

        return DB::transaction(function () use ($performance, $entrance, $qty, $by, $versionId) {
            $available = $this->inventory->stockAt($versionId, $entrance->id);
            if ($qty > $available) {
                throw ValidationException::withMessages([
                    'quantity' => "{$entrance->name} 本场版本可用库存仅 {$available} 份，无法发放 {$qty} 份。",
                ]);
            }

            $issue = Issue::create([
                'performance_id' => $performance->id,
                'entrance_id' => $entrance->id,
                'program_version_id' => $versionId,
                'quantity' => $qty,
                'issued_by' => $by,
                'issued_at' => now(),
            ]);

            $this->inventory->move($versionId, $entrance->id, -$qty, 'issue', $issue, "《{$performance->name}》发放");
            return $issue;
        });
    }

    /* ---------------- 入口调拨 ---------------- */

    /**
     * 入口间调拨：同时记录调出和调入，总库存不变。
     */
    public function transfer(ProgramVersion $version, Entrance $from, Entrance $to, int $qty, string $by = '前台'): Transfer
    {
        if ($from->id === $to->id) {
            throw ValidationException::withMessages(['entrance' => '调出与调入入口不能相同。']);
        }
        $this->assertPositive($qty);

        return DB::transaction(function () use ($version, $from, $to, $qty, $by) {
            $available = $this->inventory->stockAt($version->id, $from->id);
            if ($qty > $available) {
                throw ValidationException::withMessages([
                    'quantity' => "{$from->name} 仅有 {$available} 份可调出，无法调拨 {$qty} 份。",
                ]);
            }

            $transfer = Transfer::create([
                'program_version_id' => $version->id,
                'from_entrance_id' => $from->id,
                'to_entrance_id' => $to->id,
                'quantity' => $qty,
                'transferred_by' => $by,
                'transferred_at' => now(),
            ]);

            $this->inventory->move($version->id, $from->id, -$qty, 'transfer_out', $transfer, "调出至{$to->name}");
            $this->inventory->move($version->id, $to->id, $qty, 'transfer_in', $transfer, "由{$from->name}调入");

            return $transfer->load('fromEntrance', 'toEntrance');
        });
    }

    /* ---------------- 错版报废审批 ---------------- */

    public function requestScrap(ProgramVersion $version, int $qty, string $reason, ?int $entranceId = null, string $by = '运营'): Scrap
    {
        $this->assertPositive($qty);

        $locName = $entranceId ? Entrance::findOrFail($entranceId)->name : '前台总库';
        $available = $this->inventory->stockAt($version->id, $entranceId);
        if ($qty > $available) {
            throw ValidationException::withMessages([
                'quantity' => "{$locName} 当前仅有 {$available} 份，无法报废 {$qty} 份。",
            ]);
        }

        return Scrap::create([
            'program_version_id' => $version->id,
            'entrance_id' => $entranceId,
            'quantity' => $qty,
            'reason' => $reason,
            'status' => 'pending',
            'requested_by' => $by,
        ]);
    }

    /** 审批通过才真正扣库存；驳回不动账。 */
    public function reviewScrap(Scrap $scrap, bool $approve, string $by = '运营主管', ?string $note = null): Scrap
    {
        if ($scrap->status !== 'pending') {
            throw ValidationException::withMessages(['scrap' => '该报废申请已审批，不能重复操作。']);
        }

        if (! $approve) {
            $scrap->update([
                'status' => 'rejected',
                'reviewed_by' => $by,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);
            return $scrap;
        }

        return DB::transaction(function () use ($scrap, $by, $note) {
            $available = $this->inventory->stockAt($scrap->program_version_id, $scrap->entrance_id);
            if ($scrap->quantity > $available) {
                throw ValidationException::withMessages([
                    'quantity' => "审批时发现可用库存仅 {$available} 份，不足以报废 {$scrap->quantity} 份。",
                ]);
            }

            $scrap->update([
                'status' => 'approved',
                'reviewed_by' => $by,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            $this->inventory->move(
                $scrap->program_version_id, $scrap->entrance_id,
                -$scrap->quantity, 'scrap', $scrap, "报废审批通过：{$scrap->reason}"
            );
            return $scrap;
        });
    }

    /* ---------------- 散场盘点 ---------------- */

    /**
     * 录入散场实际结余。运营按各入口实物点数录入，作为补印依据。
     * 同时返回账实差异（账面剩余 - 实点结余）。
     */
    public function countStock(Performance $performance, Entrance $entrance, int $remaining, string $by = '运营', ?string $note = null): array
    {
        if ($performance->status === 'scheduled') {
            throw ValidationException::withMessages(['performance' => '演出尚未确认，不能盘点。']);
        }
        if ($remaining < 0) {
            throw ValidationException::withMessages(['remaining_qty' => '结余数量不能为负。']);
        }
        $versionId = $performance->program_version_id;

        $count = DB::transaction(function () use ($performance, $entrance, $remaining, $by, $note, $versionId) {
            $count = StockCount::updateOrCreate(
                ['performance_id' => $performance->id, 'entrance_id' => $entrance->id, 'program_version_id' => $versionId],
                ['remaining_qty' => $remaining, 'counted_by' => $by, 'note' => $note, 'counted_at' => now()]
            );
            return $count;
        });

        $bookRemaining = $this->inventory->stockAt($versionId, $entrance->id);

        return [
            'count' => $count,
            'book_remaining' => $bookRemaining,
            'actual_remaining' => $remaining,
            'variance' => $bookRemaining - $remaining,
        ];
    }

    /** 所有入口盘点完成后关闭场次，并给出补印建议。 */
    public function closePerformance(Performance $performance): array
    {
        $entranceIds = Entrance::pluck('id');
        $counted = StockCount::where('performance_id', $performance->id)
            ->where('program_version_id', $performance->program_version_id)
            ->get()
            ->keyBy('entrance_id');

        $missing = $entranceIds->reject(fn ($id) => $counted->has($id));
        if ($missing->isNotEmpty()) {
            $names = Entrance::whereIn('id', $missing)->pluck('name')->implode('、');
            throw ValidationException::withMessages(['performance' => "还有入口未盘点：{$names}"]);
        }

        return DB::transaction(function () use ($performance, $counted) {
            $performance->update(['status' => 'closed']);

            $actualRemaining = (int) $counted->sum('remaining_qty');
            // 总库未分发的剩余也纳入补印判断
            $frontStock = $this->inventory->stockAt($performance->program_version_id, null);
            $totalRemaining = $actualRemaining + $frontStock;

            $totals = $this->inventory->totals($performance->program_version_id);

            return [
                'performance' => $performance->fresh(),
                'actual_remaining_at_entrances' => $actualRemaining,
                'front_stock' => $frontStock,
                'total_remaining' => $totalRemaining,
                'issued_total' => $totals['issued'],
                'scrapped_total' => $totals['scrapped'],
                'delivered_total' => $totals['delivered'],
                'reprint_suggested' => $totalRemaining < $totals['issued'] * 0.2,
                'reprint_reason' => $totalRemaining < $totals['issued'] * 0.2
                    ? '结余不足已发放量的 20%，建议补印'
                    : '结余充足，无需补印',
            ];
        });
    }

    /* ---------------- 查询辅助 ---------------- */

    /** 本场该入口累计发放（账）。 */
    public function issuedForEntrance(Performance $performance, int $entranceId): int
    {
        return (int) Issue::where('performance_id', $performance->id)
            ->where('entrance_id', $entranceId)
            ->sum('quantity');
    }

    /** 该入口当前账面结存。 */
    public function receivedForEntrance(Performance $performance, int $entranceId): int
    {
        return $this->inventory->stockAt($performance->program_version_id, $entranceId)
            + $this->issuedForEntrance($performance, $entranceId);
    }

    private function assertPositive(int $qty): void
    {
        if ($qty <= 0) {
            throw ValidationException::withMessages(['quantity' => '数量必须大于 0。']);
        }
    }

    private function assertFrontStock(int $versionId, int $qty): void
    {
        $available = $this->inventory->stockAt($versionId, null);
        if ($qty > $available) {
            throw ValidationException::withMessages([
                'quantity' => "前台总库可用仅 {$available} 份，无法分配 {$qty} 份。",
            ]);
        }
    }
}
