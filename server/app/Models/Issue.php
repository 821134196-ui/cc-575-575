<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Issue extends Model
{
    protected $fillable = [
        'performance_id', 'entrance_id', 'program_version_id',
        'quantity', 'issued_by', 'issued_at', 'note',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'quantity' => 'integer',
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
