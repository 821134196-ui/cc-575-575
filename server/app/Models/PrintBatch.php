<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintBatch extends Model
{
    protected $fillable = [
        'program_version_id', 'batch_no', 'ordered_qty',
        'delivered_qty', 'status', 'printer_name',
        'receipt_no', 'delivered_at',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'ordered_qty' => 'integer',
        'delivered_qty' => 'integer',
    ];

    public function programVersion(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }
}
