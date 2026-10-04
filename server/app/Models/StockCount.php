<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCount extends Model
{
    protected $fillable = [
        'performance_id', 'entrance_id', 'program_version_id',
        'remaining_qty', 'counted_by', 'note', 'counted_at',
    ];

    protected $casts = [
        'counted_at' => 'datetime',
        'remaining_qty' => 'integer',
    ];

    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    public function entrance(): BelongsTo
    {
        return $this->belongsTo(Entrance::class);
    }

    public function programVersion(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class);
    }
}
