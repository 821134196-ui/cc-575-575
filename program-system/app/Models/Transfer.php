<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    protected $fillable = [
        'transfer_no', 'program_version_id',
        'from_entrance_id', 'to_entrance_id',
        'quantity', 'operator', 'reason',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class, 'program_version_id');
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
