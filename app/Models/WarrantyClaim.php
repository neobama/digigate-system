<?php

namespace App\Models;

use App\Services\WarrantyClaimService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarrantyClaim extends Model
{
    use HasUuids;

    protected $fillable = [
        'ticket_number',
        'invoice_number',
        'invoice_date',
        'claim_category',
        'client_name',
        'phone_number',
        'device_type',
        'complaint',
        'assembly_serial_number',
        'is_assembly_linked',
        'source',
        'status',
        'entry_date',
        'completed_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'entry_date' => 'datetime',
        'completed_at' => 'datetime',
        'is_assembly_linked' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (WarrantyClaim $claim) {
            if (filled($claim->invoice_date)) {
                $claim->claim_category = WarrantyClaimService::resolveClaimCategory($claim->invoice_date);
            }
        });

        static::creating(function (WarrantyClaim $claim) {
            if (blank($claim->ticket_number)) {
                $claim->ticket_number = WarrantyClaimService::generateTicketNumber();
            }

            if (filled($claim->assembly_serial_number)) {
                $claim->assembly_serial_number = strtoupper(trim($claim->assembly_serial_number));
                $link = WarrantyClaimService::resolveAssemblyLink($claim->assembly_serial_number);
                $claim->is_assembly_linked = $link['is_assembly_linked'];

                if (blank($claim->device_type) && $link['device_type']) {
                    $claim->device_type = $link['device_type'];
                }
            }
        });

        static::updating(function (WarrantyClaim $claim) {
            if (! $claim->isDirty('assembly_serial_number')) {
                return;
            }

            if (blank($claim->assembly_serial_number)) {
                $claim->is_assembly_linked = false;

                return;
            }

            $claim->assembly_serial_number = strtoupper(trim($claim->assembly_serial_number));
            $link = WarrantyClaimService::resolveAssemblyLink($claim->assembly_serial_number);
            $claim->is_assembly_linked = $link['is_assembly_linked'];
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WarrantyLog::class)->orderBy('created_at', 'desc');
    }

    public function findAssembly(): ?Assembly
    {
        if (blank($this->assembly_serial_number)) {
            return null;
        }

        return Assembly::query()
            ->where('serial_number', $this->assembly_serial_number)
            ->first();
    }

    /** @deprecated Use findAssembly() */
    public function assembly()
    {
        return $this->findAssembly();
    }

    public function deviceTypeLabel(): string
    {
        return WarrantyClaimService::DEVICE_TYPES[$this->device_type] ?? ($this->device_type ?: '-');
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            'client_portal' => 'Portal Client',
            default => 'Internal',
        };
    }

    public function claimCategoryLabel(): string
    {
        return WarrantyClaimService::CLAIM_CATEGORIES[$this->claim_category] ?? ($this->claim_category ?: '-');
    }

    public function isOutOfWarranty(): bool
    {
        return $this->claim_category === 'repair';
    }
}
