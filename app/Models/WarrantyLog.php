<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyLog extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'warranty_claim_id',
        'status',
        'old_component_sn',
        'new_component_sn',
        'component_type',
        'notes',
        'changed_by',
    ];
    
    public function warrantyClaim(): BelongsTo
    {
        return $this->belongsTo(WarrantyClaim::class);
    }
    
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'changed_by');
    }
    
    public function oldComponent()
    {
        return \App\Models\Component::where('sn', $this->old_component_sn)->first();
    }
    
    public function newComponent()
    {
        return \App\Models\Component::where('sn', $this->new_component_sn)->first();
    }
}
