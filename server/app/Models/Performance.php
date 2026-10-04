<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Performance extends Model
{
    protected $fillable = [
        'production_id', 'program_version_id', 'name',
        'performed_at', 'status', 'confirmed_at', 'confirmed_by',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    public function programVersion(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function stockCounts(): HasMany
    {
        return $this->hasMany(StockCount::class);
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
