<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Issue extends Model
{
    protected $fillable = [
        'entrance_id', 'program_version_id', 'print_batch_id',
        'quantity', 'issued_by', 'issued_at',
    ];

    protected $casts = ['issued_at' => 'datetime'];

    public function entrance(): BelongsTo
    {
        return $this->belongsTo(Entrance::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class, 'program_version_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PrintBatch::class, 'print_batch_id');
    }
}
