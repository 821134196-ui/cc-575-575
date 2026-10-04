<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Performance extends Model
{
    protected $fillable = ['title', 'performed_at', 'venue', 'status', 'notes'];

    protected $casts = ['performed_at' => 'datetime'];

    public function versions(): HasMany
    {
        return $this->hasMany(ProgramVersion::class)->orderBy('version_no');
    }

    public function activeVersion(): HasMany
    {
        return $this->versions()->whereIn('status', ['locked', 'superseded']);
    }

    public function currentVersion(): ?ProgramVersion
    {
        return $this->versions()->where('status', 'locked')->latest('version_no')->first();
    }

    public function stockCounts(): HasMany
    {
        return $this->hasMany(StockCount::class);
    }
}
