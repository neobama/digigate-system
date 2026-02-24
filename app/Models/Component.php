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
        try {
            $sn = $this->sn;
            
            if (empty($sn)) {
                return collect([]);
            }
            
            return \App\Models\Assembly::with('invoice')
                ->where(function ($query) use ($sn) {
                    $query->where('sn_details->chassis', $sn)
                        ->orWhere('sn_details->processor', $sn)
                        ->orWhere('sn_details->ram_1', $sn)
                        ->orWhere('sn_details->ram_2', $sn)
                        ->orWhere('sn_details->ssd', $sn);
                })
                ->get();
        } catch (\Exception $e) {
            \Log::error('Error getting assemblies for component', [
                'component_id' => $this->id,
                'component_sn' => $this->sn ?? null,
                'error' => $e->getMessage(),
            ]);
            return collect([]);
        }
    }
}