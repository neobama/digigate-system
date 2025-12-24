<?php

namespace App\Filament\Pages;

use Filament\Pages\Auth\Login as BaseLogin;

class CustomLogin extends BaseLogin
{
    protected function getFooterActions(): array
    {
        return [
            \Filament\Actions\Action::make('employeeLogin')
                ->label('Login sebagai Karyawan')
                ->url('/employee')
                ->color('info')
                ->outlined(),
        ];
    }
}
