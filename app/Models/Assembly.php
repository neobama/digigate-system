<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Assembly extends Model
{
    use HasUuids, LogsActivity;
    protected $fillable = ['invoice_id', 'product_type', 'serial_number', 'sn_details', 'assembly_date'];

    protected $casts = [
        'sn_details' => 'array', // Supaya data JSON otomatis jadi Array di PHP
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
