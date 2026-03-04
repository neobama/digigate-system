<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarrantyClaim extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'assembly_serial_number',
        'status',
        'entry_date',
        'completed_at',
        'notes',
        'created_by',
    ];
    
    protected $casts = [
        'entry_date' => 'datetime',
        'completed_at' => 'datetime',
    ];
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
    
    public function logs(): HasMany
    {
        return $this->hasMany(WarrantyLog::class)->orderBy('created_at', 'desc');
    }
    
    public function assembly()
    {
        return Assembly::where('serial_number', $this->assembly_serial_number)->first();
    }
}
