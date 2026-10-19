<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Assembly extends Model
{
    use HasUuids, LogsActivity;

    /**
     * When true, AssemblyObserver skips auto component status sync from sn_details.
     * Used by warranty replace which sets old part to damaged (not available).
     */
    public static bool $skipComponentStatusSync = false;

    protected $fillable = ['invoice_id', 'product_type', 'serial_number', 'sn_details', 'assembly_date'];

    protected $casts = [
        'sn_details' => 'array', // Supaya data JSON otomatis jadi Array di PHP
        'assembly_date' => 'date',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
