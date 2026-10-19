<?php

namespace App\Support;

use App\Models\Assembly;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AssemblySerialNumberGenerator
{
    public const PATTERN = '/^DG\d{4}(0[1-9]|1[0-2])\d{3}$/';

    /**
     * Auto-generate atau pakai override admin (format DGYYYYMM001).
     */
    public static function resolve(
        ?string $override,
        Carbon|string|null $assemblyDate = null,
        ?string $excludeAssemblyId = null
    ): string {
        if (filled($override)) {
            $serialNumber = strtoupper(trim($override));

            static::validateFormat($serialNumber);
            static::ensureUnique($serialNumber, $excludeAssemblyId);

            return $serialNumber;
        }

        return static::nextForDate($assemblyDate ?? now(), $excludeAssemblyId);
    }

    public static function nextForDate(Carbon|string $assemblyDate, ?string $excludeAssemblyId = null): string
    {
        $date = Carbon::parse($assemblyDate);
        $prefix = 'DG'.$date->format('Ym');
        $nextSequence = static::maxSequenceForPrefix($prefix, $excludeAssemblyId) + 1;

        return $prefix.str_pad((string) $nextSequence, 3, '0', STR_PAD_LEFT);
    }

    public static function validateFormat(string $serialNumber): void
    {
        if (! preg_match(self::PATTERN, $serialNumber)) {
            throw ValidationException::withMessages([
                'serial_number' => 'Format SN harus DGYYYYMM001 (contoh: DG202507001).',
            ]);
        }
    }

    public static function ensureUnique(string $serialNumber, ?string $excludeAssemblyId = null): void
    {
        $exists = Assembly::query()
            ->where('serial_number', $serialNumber)
            ->when($excludeAssemblyId, fn ($query) => $query->where('id', '!=', $excludeAssemblyId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'serial_number' => 'Serial Number sudah digunakan.',
            ]);
        }
    }

    private static function maxSequenceForPrefix(string $prefix, ?string $excludeAssemblyId = null): int
    {
        return (int) Assembly::query()
            ->when($excludeAssemblyId, fn ($query) => $query->where('id', '!=', $excludeAssemblyId))
            ->where('serial_number', 'like', $prefix.'%')
            ->pluck('serial_number')
            ->map(fn (string $serialNumber) => (int) substr($serialNumber, -3))
            ->max();
    }
}
