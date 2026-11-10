<?php

namespace App\Enums;

enum LeaveRequestType: string
{
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Cuti = 'cuti';
    case Lembur = 'lembur';

    public function label(): string
    {
        return match ($this) {
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Cuti => 'Cuti',
            self::Lembur => 'Lembur',
        };
    }

    public function countsAsPresentDay(): bool
    {
        return match ($this) {
            self::Izin, self::Sakit, self::Cuti => true,
            self::Lembur => false,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }
}
