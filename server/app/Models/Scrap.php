<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Scrap extends Model
{
    protected $fillable = [
        'program_version_id', 'print_batch_id', 'entrance_id',
        'quantity', 'reason', 'status', 'requested_by',
        'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'quantity' => 'integer',
    ];

    public function programVersion(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class);
    }

    public function printBatch(): BelongsTo
    {
        return $this->belongsTo(PrintBatch::class);
    }

    public function entrance(): BelongsTo
    {
        return $this->belongsTo(Entrance::class);
    }
}
