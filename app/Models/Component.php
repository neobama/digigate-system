<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Component extends Model
{
    use HasUuids;
    protected $fillable = ['name', 'sn', 'supplier', 'purchase_date', 'status'];

    // Opsional: Untuk membantu pencarian SN di proses rakit
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }
}