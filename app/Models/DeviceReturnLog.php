<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceReturnLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'device_return_id',
        'status',
        'description',
        'logged_by',
        'logged_at',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    public function deviceReturn(): BelongsTo
    {
        return $this->belongsTo(DeviceReturn::class);
    }

    public function loggedByUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'logged_by');
    }
}
