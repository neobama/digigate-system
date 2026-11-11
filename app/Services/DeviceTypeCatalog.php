<?php

namespace App\Services;

use App\Models\Component;
use App\Models\DeviceType;
use App\Models\DeviceTypeComponent;
use Filament\Forms;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DeviceTypeCatalog
{
    public const SLOT_OPTIONS = [
        'chassis' => 'Chassis',
        'processor' => 'Processor',
        'ram_1' => 'RAM Slot 1',
        'ram_2' => 'RAM Slot 2',
        'ssd' => 'SSD',
    ];

    private const CACHE_TTL = 600;

    public static function forgetCache(): void
    {
        Cache::forget('device_type_catalog.options.active');
        Cache::forget('device_type_catalog.options.all');
        Cache::forget('device_type_catalog.component_names');
    }

    /**
     * @return array<string, string> code => name
     */
    public static function deviceTypeOptions(bool $activeOnly = true): array
    {
        $key = 'device_type_catalog.options'.($activeOnly ? '.active' : '.all');

        return Cache::remember($key, self::CACHE_TTL, function () use ($activeOnly) {
            $query = DeviceType::query()->ordered();
            if ($activeOnly) {
                $query->active();
            }

            return $query->pluck('name', 'code')->all();
        });
    }

    /**
     * Options for assembly product_type select (with short code key).
     *
     * @return array<string, string>
     */
    public static function productTypeOptions(): array
    {
        return self::deviceTypeOptions(true);
    }

    /**
     * All component names employees/admin may stock — from device type definitions,
     * plus any names already used in inventory (backward compatible).
     *
     * @return array<string, string>
     */
    public static function componentNameOptions(): array
    {
        return Cache::remember('device_type_catalog.component_names', self::CACHE_TTL, function () {
            $fromCatalog = DeviceTypeComponent::query()
                ->whereHas('deviceType', fn ($q) => $q->active())
                ->orderBy('component_name')
                ->pluck('component_name');

            $fromStock = Component::query()
                ->whereNotNull('name')
                ->where('name', '!=', '')
                ->distinct()
                ->orderBy('name')
                ->pluck('name');

            return $fromCatalog
                ->merge($fromStock)
                ->unique()
                ->sort()
                ->mapWithKeys(fn (string $name) => [$name => $name])
                ->all();
        });
    }

    public static function findByCode(string $code): ?DeviceType
    {
        return DeviceType::query()
            ->with('components')
            ->where('code', $code)
            ->first();
    }

    /**
     * @return Collection<int, DeviceTypeComponent>
     */
    public static function slotsForDeviceType(string $code): Collection
    {
        $device = self::findByCode($code);
        if (! $device) {
            return collect();
        }

        return $device->components;
    }

    /**
     * Component name strings allowed for a slot (optionally scoped to device type).
     *
     * @return list<string>
     */
    public static function componentNamesForSlot(string $slotKey, ?string $deviceTypeCode = null): array
    {
        $query = DeviceTypeComponent::query()
            ->where('slot_key', $slotKey)
            ->whereHas('deviceType', fn ($q) => $q->active());

        if ($deviceTypeCode) {
            $query->whereHas('deviceType', fn ($q) => $q->where('code', $deviceTypeCode));
        }

        $names = $query->pluck('component_name')->unique()->values()->all();

        if ($names !== []) {
            return $names;
        }

        // Fallback if catalog empty for this slot
        return match ($slotKey) {
            'chassis' => ['Chassis Macan', 'Chassis Maleo', 'Chassis Komodo'],
            'processor' => ['Processor i7 11700K', 'Processor i7 8700K', 'Processor i7 14700K'],
            'ram_1', 'ram_2' => ['RAM DDR4', 'RAM DDR5'],
            'ssd' => ['SSD'],
            default => [],
        };
    }

    public static function slotLabel(string $slotKey): string
    {
        return self::SLOT_OPTIONS[$slotKey] ?? strtoupper(str_replace('_', ' ', $slotKey));
    }

    /**
     * Stable Filament selects for assembly sn_details.
     *
     * Field names are ALWAYS the same (chassis/processor/ram_1/ram_2/ssd) so Livewire
     * keeps wire:model identity across product_type changes. Labels, options, required,
     * and visibility come from the device-type BOM via Get closures.
     *
     * Returning brand-new Select instances from a schema(fn) based on product_type
     * remounts inputs and causes "required" errors even when the UI shows values.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function assemblySnDetailFields(): array
    {
        $fields = [];

        foreach (array_keys(self::SLOT_OPTIONS) as $slotKey) {
            $fields[] = Forms\Components\Select::make($slotKey)
                ->label(function (Forms\Get $get) use ($slotKey) {
                    $type = $get('../product_type');
                    if (! filled($type)) {
                        return 'SN '.self::slotLabel($slotKey);
                    }

                    $slot = self::slotsForDeviceType((string) $type)->firstWhere('slot_key', $slotKey);

                    return 'SN '.($slot?->displayLabel() ?? self::slotLabel($slotKey));
                })
                ->options(function (Forms\Get $get) use ($slotKey) {
                    $type = $get('../product_type');
                    if (! filled($type)) {
                        return [];
                    }

                    $names = self::componentNamesForSlot($slotKey, (string) $type);
                    if ($names === []) {
                        return [];
                    }

                    return Component::query()
                        ->whereIn('name', $names)
                        ->where('status', 'available')
                        ->orderBy('sn')
                        ->pluck('sn', 'sn')
                        ->all();
                })
                ->required(function (Forms\Get $get) use ($slotKey): bool {
                    $type = $get('../product_type');
                    if (! filled($type)) {
                        return false;
                    }

                    $slot = self::slotsForDeviceType((string) $type)->firstWhere('slot_key', $slotKey);

                    return $slot ? (bool) $slot->is_required : false;
                })
                ->visible(function (Forms\Get $get) use ($slotKey): bool {
                    $type = $get('../product_type');
                    if (! filled($type)) {
                        return false;
                    }

                    return self::slotsForDeviceType((string) $type)
                        ->contains(fn (DeviceTypeComponent $slot) => $slot->slot_key === $slotKey);
                });
        }

        return [
            Forms\Components\Group::make($fields)
                ->statePath('sn_details')
                ->columns(2),
        ];
    }
}
