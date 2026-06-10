<?php

namespace App\Filament\Concerns;

use Filament\Forms\Components\Component;

trait SupportsBrowserCredentialSaving
{
    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->autocomplete('username')
            ->extraInputAttributes([
                'name' => 'email',
                'id' => 'email',
            ], merge: true);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->autocomplete('current-password')
            ->extraInputAttributes([
                'name' => 'password',
                'id' => 'password',
            ], merge: true);
    }

    protected function getRememberFormComponent(): Component
    {
        return parent::getRememberFormComponent()
            ->extraInputAttributes([
                'name' => 'remember',
            ], merge: true);
    }
}
