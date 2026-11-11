<?php

namespace App\Models;

use App\Services\DeviceTypeCatalog;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceTypeComponent extends Model
{
    use HasUuids;

    protected $fillable = [
        'device_type_id',
        'slot_key',
        'component_name',
        'label',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => DeviceTypeCatalog::forgetCache());
        static::deleted(fn () => DeviceTypeCatalog::forgetCache());
    }

    public function deviceType(): BelongsTo
    {
        return $this->belongsTo(DeviceType::class);
    }

    public function displayLabel(): string
    {
        return $this->label ?: $this->component_name;
    }
}
