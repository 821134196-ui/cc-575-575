<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Allocation extends Model
{
    protected $fillable = [
        'print_batch_id', 'program_version_id', 'entrance_id',
        'quantity', 'allocated_by',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PrintBatch::class, 'print_batch_id');
    }

    public function entrance(): BelongsTo
    {
        return $this->belongsTo(Entrance::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class, 'program_version_id');
    }
}
