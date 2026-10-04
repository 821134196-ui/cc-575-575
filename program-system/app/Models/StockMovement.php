<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'program_version_id', 'print_batch_id', 'type',
        'location_from', 'location_to', 'quantity',
        'ref_type', 'ref_id', 'operator',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class, 'program_version_id');
    }
}
