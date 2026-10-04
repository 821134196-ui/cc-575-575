<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = null;

    protected $fillable = [
        'program_version_id', 'entrance_id', 'quantity',
        'kind', 'ref_type', 'ref_id', 'note', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'quantity' => 'integer',
    ];

    public function programVersion(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class);
    }

    public function entrance(): BelongsTo
    {
        return $this->belongsTo(Entrance::class);
    }
}
