<?php

namespace App\Enums;

enum EmployeeRole: string
{
    case Employee = 'employee';
    case Finance = 'finance';

    public function label(): string
    {
        return match ($this) {
            self::Employee => 'Karyawan',
            self::Finance => 'Finance',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->label()])
            ->all();
    }
}
