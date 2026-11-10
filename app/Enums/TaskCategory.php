<?php

namespace App\Enums;

enum TaskCategory: string
{
    case PemasanganFtth = 'pemasangan_ftth';
    case KomplainFtth = 'komplain_ftth';
    case DigiGate = 'digigate';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::PemasanganFtth => 'Pemasangan FTTH',
            self::KomplainFtth => 'Komplain FTTH',
            self::DigiGate => 'DigiGate',
            self::Internal => 'Internal',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $category) => [$category->value => $category->label()])
            ->all();
    }
}
