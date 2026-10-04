<?php

namespace App\Models;

use App\Exceptions\VersionLockedException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramVersion extends Model
{
    protected $fillable = [
        'performance_id', 'source_version_id', 'version_no',
        'file_name', 'file_hash', 'cast_list', 'change_reason',
        'status', 'locked_at', 'locked_by',
    ];

    protected $casts = ['locked_at' => 'datetime'];

    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    public function printBatches(): HasMany
    {
        return $this->hasMany(PrintBatch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isLocked(): bool
    {
        return $this->status !== 'draft';
    }

    /**
     * 锁定后任何内容修改都被拒绝 —— 已发出的旧节目单不能改名顶替。
     * 演员名单临时变化只能新建版本。
     */
    public function guardEditable(array $fields = ['file_name', 'cast_list', 'file_hash']): void
    {
        if ($this->isLocked()) {
            throw new VersionLockedException(
                "版本 v{$this->version_no} 已锁定（{$this->status}），待印文件不可修改，请新建版本。"
            );
        }
    }

    public function locationStock(string $location): int
    {
        $in = (int) $this->movements()
            ->where('location_to', $location)->sum('quantity');
        $out = (int) $this->movements()
            ->where('location_from', $location)->sum('quantity');
        // 流水恒为正数：入库记 to，出库记 from；库位余量 = 流入 − 流出
        return $in - $out;
    }

    /** 各库位当前数量：to 为流入(+)，from 为流出(-) */
    public function stockByLocation(): array
    {
        $stock = [];
        foreach ($this->movements as $m) {
            if ($m->location_to) {
                $stock[$m->location_to] = ($stock[$m->location_to] ?? 0) + $m->quantity;
            }
            if ($m->location_from) {
                $stock[$m->location_from] = ($stock[$m->location_from] ?? 0) - $m->quantity;
            }
        }
        return $stock;
    }

    public function totalDelivered(): int
    {
        return (int) $this->printBatches()->where('status', 'delivered')->sum('quantity_delivered');
    }
}
