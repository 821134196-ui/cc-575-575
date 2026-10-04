<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Allocation extends Model
{
    protected $fillable = [
        'print_batch_id', 'entrance_id', 'quantity',
        'allocated_by', 'note',
    ];

    protected $casts = ['quantity' => 'integer'];

    public function printBatch(): BelongsTo
    {
        return $this->belongsTo(PrintBatch::class);
    }

    public function entrance(): BelongsTo
    {
        return $this->belongsTo(Entrance::class);
    }
}
