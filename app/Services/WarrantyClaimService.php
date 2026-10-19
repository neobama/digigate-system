<?php

namespace App\Services;

use App\Models\Assembly;
use App\Models\Component;
use App\Models\WarrantyClaim;
use App\Models\WarrantyLog;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarrantyClaimService
{
    public const WARRANTY_YEARS = 1;

    public const CLAIM_CATEGORIES = [
        'warranty' => 'Garansi',
        'repair' => 'Repair',
    ];

    public const DEVICE_TYPES = [
        'Maleo' => 'DigiGate Maleo',
        'Macan' => 'DigiGate Macan',
        'Komodo' => 'DigiGate Komodo',
    ];

    public const COMPONENT_TYPES = [
        'chassis' => 'Chassis',
        'processor' => 'Processor',
        'ram_1' => 'RAM Slot 1',
        'ram_2' => 'RAM Slot 2',
        'ssd' => 'SSD',
    ];

    public static function generateTicketNumber(): string
    {
        return DB::transaction(function () {
            $prefix = 'GS-'.now()->format('Ymd').'-';

            $latest = WarrantyClaim::query()
                ->where('ticket_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('ticket_number')
                ->value('ticket_number');

            $sequence = $latest ? ((int) substr($latest, -4)) + 1 : 1;

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Record a status-only change with WarrantyLog (used by Filament form saves).
     */
    public static function logStatusChange(WarrantyClaim $claim, string $oldStatus, string $newStatus, ?string $notes = null): ?WarrantyLog
    {
        if ($oldStatus === $newStatus) {
            return null;
        }

        return WarrantyLog::create([
            'warranty_claim_id' => $claim->id,
            'status' => $newStatus,
            'action_type' => 'status_update',
            'notes' => $notes ?: 'Status diubah dari '.$oldStatus.' ke '.$newStatus,
            'changed_by' => Auth::id(),
        ]);
    }

    public static function refreshAssemblyLink(WarrantyClaim $claim): WarrantyClaim
    {
        $serial = trim((string) $claim->assembly_serial_number);
        if ($serial === '') {
            $claim->update([
                'is_assembly_linked' => false,
            ]);

            return $claim->fresh();
        }

        $link = static::resolveAssemblyLink($serial);
        $claim->update([
            'is_assembly_linked' => $link['is_assembly_linked'],
            'device_type' => $claim->device_type ?: $link['device_type'],
        ]);

        return $claim->fresh();
    }

    public static function resolveAssemblyLink(string $serialNumber): array
    {
        $assembly = Assembly::query()
            ->where('serial_number', $serialNumber)
            ->first();

        return [
            'assembly' => $assembly,
            'is_assembly_linked' => $assembly !== null,
            'device_type' => $assembly?->product_type,
        ];
    }

    public static function warrantyExpiresAt(CarbonInterface|string $invoiceDate): Carbon
    {
        return Carbon::parse($invoiceDate)->startOfDay()->addYears(static::WARRANTY_YEARS);
    }

    public static function resolveClaimCategory(CarbonInterface|string $invoiceDate, ?CarbonInterface $asOf = null): string
    {
        $asOf = ($asOf ? Carbon::parse($asOf) : now())->startOfDay();

        return $asOf->greaterThan(static::warrantyExpiresAt($invoiceDate))
            ? 'repair'
            : 'warranty';
    }

    public static function createFromClientPortal(array $data): WarrantyClaim
    {
        $serialNumber = strtoupper(trim($data['assembly_serial_number']));
        $link = static::resolveAssemblyLink($serialNumber);
        $category = static::resolveClaimCategory($data['invoice_date']);

        return DB::transaction(function () use ($data, $serialNumber, $link, $category) {
            $claim = WarrantyClaim::create([
                'ticket_number' => static::generateTicketNumber(),
                'invoice_number' => $data['invoice_number'],
                'invoice_date' => $data['invoice_date'],
                'claim_category' => $category,
                'client_name' => $data['client_name'],
                'phone_number' => $data['phone_number'],
                'device_type' => $data['device_type'],
                'complaint' => $data['complaint'],
                'assembly_serial_number' => $serialNumber,
                'is_assembly_linked' => $link['is_assembly_linked'],
                'source' => 'client_portal',
                'status' => 'pending',
                'entry_date' => now(),
                'notes' => null,
                'created_by' => null,
            ]);

            $notes = 'Pengajuan dari portal client repair.digigate.id — kategori: '
                .(static::CLAIM_CATEGORIES[$category] ?? $category).'.';
            if ($link['is_assembly_linked']) {
                $notes .= ' SN terhubung otomatis ke assembly.';
            } else {
                $notes .= ' SN belum ditemukan di ERP, perlu link manual.';
            }

            WarrantyLog::create([
                'warranty_claim_id' => $claim->id,
                'status' => 'pending',
                'action_type' => 'client_submit',
                'notes' => $notes,
                'changed_by' => null,
            ]);

            return $claim;
        });
    }

    public static function availableComponentOptions(string $componentType, ?string $deviceType = null): array
    {
        $names = match ($componentType) {
            'chassis' => match ($deviceType) {
                'Macan' => ['Chassis Macan'],
                'Maleo' => ['Chassis Maleo'],
                'Komodo' => ['Chassis Komodo'],
                default => ['Chassis Macan', 'Chassis Maleo', 'Chassis Komodo'],
            },
            'processor' => match ($deviceType) {
                'Macan' => ['Processor i7 11700K'],
                'Maleo' => ['Processor i7 8700K'],
                'Komodo' => ['Processor i7 14700K'],
                default => ['Processor i7 11700K', 'Processor i7 8700K', 'Processor i7 14700K'],
            },
            'ram_1', 'ram_2' => $deviceType === 'Komodo'
                ? ['RAM DDR5']
                : ($deviceType ? ['RAM DDR4'] : ['RAM DDR4', 'RAM DDR5']),
            'ssd' => ['SSD'],
            default => [],
        };

        return Component::query()
            ->whereIn('name', $names)
            ->where('status', 'available')
            ->pluck('sn', 'sn')
            ->all();
    }

    public static function addWorkLog(WarrantyClaim $claim, array $data): WarrantyLog
    {
        $actionType = $data['action_type'] ?? 'status_update';
        $newStatus = $data['status'] ?? $claim->status;

        return DB::transaction(function () use ($claim, $data, $actionType, $newStatus) {
            $claim->update([
                'status' => $newStatus,
                'completed_at' => $newStatus === 'completed' ? ($claim->completed_at ?? now()) : null,
            ]);

            $logData = [
                'warranty_claim_id' => $claim->id,
                'status' => $newStatus,
                'action_type' => $actionType,
                'notes' => $data['notes'] ?? null,
                'changed_by' => Auth::id(),
                'component_type' => $data['component_type'] ?? null,
                'old_component_sn' => null,
                'new_component_sn' => null,
            ];

            if ($actionType === 'service') {
                if (empty($data['component_type'])) {
                    throw ValidationException::withMessages([
                        'component_type' => 'Pilih komponen yang di-service.',
                    ]);
                }

                $assembly = $claim->findAssembly();
                $logData['old_component_sn'] = $assembly?->sn_details[$data['component_type']] ?? null;
            }

            if ($actionType === 'replace') {
                if (empty($data['component_type']) || empty($data['new_component_sn'])) {
                    throw ValidationException::withMessages([
                        'new_component_sn' => 'Penggantian komponen wajib memilih part dari stok.',
                    ]);
                }

                $assembly = $claim->findAssembly();
                if (! $assembly) {
                    throw ValidationException::withMessages([
                        'component_type' => 'SN perangkat belum terhubung ke assembly. Link manual dulu sebelum ganti part.',
                    ]);
                }

                $oldSn = $assembly->sn_details[$data['component_type']] ?? null;
                $newSn = $data['new_component_sn'];

                $newComponent = Component::query()
                    ->where('sn', $newSn)
                    ->where('status', 'available')
                    ->first();

                if (! $newComponent) {
                    throw ValidationException::withMessages([
                        'new_component_sn' => 'Komponen baru tidak tersedia di stok.',
                    ]);
                }

                $snDetails = $assembly->sn_details ?? [];
                $snDetails[$data['component_type']] = $newSn;

                // Observer would mark removed SN as available; warranty wants damaged instead.
                Assembly::$skipComponentStatusSync = true;
                try {
                    $assembly->update(['sn_details' => $snDetails]);
                } finally {
                    Assembly::$skipComponentStatusSync = false;
                }

                if ($oldSn) {
                    Component::where('sn', $oldSn)->update(['status' => 'damaged']);
                }

                $newComponent->update(['status' => 'used']);

                $logData['old_component_sn'] = $oldSn;
                $logData['new_component_sn'] = $newSn;
            }

            return WarrantyLog::create($logData);
        });
    }
}
