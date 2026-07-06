<?php

namespace App\Enums;

enum AttendanceType: string
{
    case TapIn = 'tap_in';
    case TapOut = 'tap_out';

    public function label(): string
    {
        return match ($this) {
            self::TapIn => 'Tap In',
            self::TapOut => 'Tap Out',
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
