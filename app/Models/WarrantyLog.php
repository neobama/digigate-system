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
        'action_type',
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
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function actionLabel(): string
    {
        return match ($this->action_type) {
            'client_submit' => 'Pengajuan Client',
            'service' => 'Service Part',
            'replace' => 'Ganti Part',
            default => 'Update Status',
        };
    }
}
