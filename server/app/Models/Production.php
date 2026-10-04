<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Production extends Model
{
    protected $fillable = ['name', 'code', 'note'];

    public function versions(): HasMany
    {
        return $this->hasMany(ProgramVersion::class)->orderBy('version_no');
    }

    public function performances(): HasMany
    {
        return $this->hasMany(Performance::class);
    }
}
