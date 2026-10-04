<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entrance extends Model
{
    protected $fillable = ['code', 'name'];

    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class);
    }
}
