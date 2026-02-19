<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DeviceReturn extends Model
{
    use HasUuids;

    protected $fillable = [
        'tracking_number',
        'invoice_number',
        'purchase_date',
        'device_type',
        'serial_number',
        'include_mikrotik_license',
        'customer_name',
        'company_name',
        'phone_number',
        'issue_details',
        'proof_files',
        'status',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'include_mikrotik_license' => 'boolean',
        'proof_files' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($deviceReturn) {
            if (!$deviceReturn->tracking_number) {
                $deviceReturn->tracking_number = static::generateTrackingNumber();
            }
        });
    }

    /**
     * Generate unique tracking number
     * Format: RT-YYYYMMDD-XXXXXX (6 random alphanumeric)
     */
    public static function generateTrackingNumber(): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(6));
        
        do {
            $trackingNumber = "RT-{$date}-{$random}";
            $random = strtoupper(Str::random(6));
        } while (static::where('tracking_number', $trackingNumber)->exists());
        
        return $trackingNumber;
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DeviceReturnLog::class)->orderBy('logged_at', 'desc');
    }

    public function loggedBy()
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
