<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCount extends Model
{
    protected $fillable = [
        'performance_id', 'program_version_id', 'location',
        'system_qty', 'actual_qty', 'counted_by', 'counted_at', 'note',
    ];

    protected $casts = ['counted_at' => 'datetime'];

    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class, 'program_version_id');
    }
}
