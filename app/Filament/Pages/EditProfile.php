<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;
use Illuminate\Support\Facades\Hash;

class EditProfile extends BaseEditProfile
{


    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Profil')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                    ]),
                Forms\Components\Section::make('Ubah Password')
                    ->description('Kosongkan jika tidak ingin mengubah password')
                    ->schema([
                        Forms\Components\TextInput::make('current_password')
                            ->label('Password Saat Ini')
                            ->password()
                            ->revealable()
                            ->requiredWith('new_password')
                            ->currentPassword(),
                        Forms\Components\TextInput::make('new_password')
                            ->label('Password Baru')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->helperText('Minimal 8 karakter')
                            ->requiredWith('current_password'),
                        Forms\Components\TextInput::make('new_password_confirmation')
                            ->label('Konfirmasi Password Baru')
                            ->password()
                            ->revealable()
                            ->same('new_password')
                            ->requiredWith('new_password'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle password update
        if (!empty($data['current_password']) && !empty($data['new_password'])) {
            $user = auth()->user();
            
            // Verify current password
            if (!Hash::check($data['current_password'], $user->password)) {
                Notification::make()
                    ->title('Password saat ini salah!')
                    ->danger()
                    ->send();
                throw new \Illuminate\Validation\ValidationException(
                    validator([], []),
                    ['current_password' => ['Password saat ini salah.']]
                );
            }

            // Set new password
            $data['password'] = Hash::make($data['new_password']);
        }
        
        // Remove password fields from data
        unset($data['current_password'], $data['new_password'], $data['new_password_confirmation']);
        
        return $data;
    }
}
