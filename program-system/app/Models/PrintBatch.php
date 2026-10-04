<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintBatch extends Model
{
    protected $fillable = [
        'program_version_id', 'batch_no', 'quantity_ordered',
        'quantity_delivered', 'status', 'printer_receipt_no',
        'delivered_at', 'ordered_by',
    ];

    protected $casts = ['delivered_at' => 'datetime'];

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class, 'program_version_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }
}
