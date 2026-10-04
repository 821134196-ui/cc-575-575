<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrapRequest extends Model
{
    protected $fillable = [
        'scrap_no', 'program_version_id', 'print_batch_id', 'location',
        'quantity', 'reason', 'status', 'requested_by',
        'approved_by', 'approved_at', 'approval_note',
    ];

    protected $casts = ['approved_at' => 'datetime'];

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class, 'program_version_id');
    }
}
