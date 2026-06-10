<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Concerns\SupportsBrowserCredentialSaving;
use Filament\Pages\Auth\Login as FilamentLogin;

abstract class BaseLogin extends FilamentLogin
{
    use SupportsBrowserCredentialSaving;

    protected static string $view = 'filament.pages.auth.login';
}
