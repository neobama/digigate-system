<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Component extends Model
{
    use HasUuids, LogsActivity;
    protected $fillable = ['name', 'sn', 'supplier', 'invoice_number', 'purchase_date', 'status'];

    // Opsional: Untuk membantu pencarian SN di proses rakit
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Get assemblies that use this component (by SN)
     */
    public function getAssembliesUsingThisComponent()
    {
        $sn = $this->sn;
        
        return \App\Models\Assembly::with('invoice')
            ->where(function ($query) use ($sn) {
                $query->where('sn_details->chassis', $sn)
                    ->orWhere('sn_details->processor', $sn)
                    ->orWhere('sn_details->ram_1', $sn)
                    ->orWhere('sn_details->ram_2', $sn)
                    ->orWhere('sn_details->ssd', $sn);
            })
            ->get();
    }
}