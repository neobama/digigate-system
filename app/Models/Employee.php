<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasUuids;
    protected $fillable = ['user_id', 'nik', 'name', 'birth_date', 'position', 'base_salary', 'bpjs_allowance', 'is_active'];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function cashbons(): HasMany
    {
        return $this->hasMany(Cashbon::class);
    }

    public function logbooks(): HasMany
    {
        return $this->hasMany(Logbook::class);
    }
}
