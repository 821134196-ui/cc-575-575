<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Exceptions\LockedVersionException;

class ProgramVersion extends Model
{
    protected $fillable = [
        'production_id', 'version_no', 'label', 'change_reason',
        'file_name', 'file_path', 'file_hash', 'file_size',
        'status', 'locked_at', 'locked_by',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
        'file_size' => 'integer',
        'version_no' => 'integer',
    ];

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function printBatches(): HasMany
    {
        return $this->hasMany(PrintBatch::class);
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    /**
     * 锁定后的待印文件不允许修改。
     * @throws LockedVersionException
     */
    public function assertEditable(): void
    {
        if ($this->isLocked()) {
            throw new LockedVersionException(
                "版本 v{$this->version_no}（{$this->label}）已锁定，待印文件不可修改；如需变更请新建版本。"
            );
        }
    }
}
