<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    protected $fillable = [
        'program_version_id', 'from_entrance_id', 'to_entrance_id',
        'quantity', 'transferred_by', 'transferred_at', 'note',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
        'quantity' => 'integer',
    ];

    public function programVersion(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class);
    }

    public function fromEntrance(): BelongsTo
    {
        return $this->belongsTo(Entrance::class, 'from_entrance_id');
    }

    public function toEntrance(): BelongsTo
    {
        return $this->belongsTo(Entrance::class, 'to_entrance_id');
    }
}
