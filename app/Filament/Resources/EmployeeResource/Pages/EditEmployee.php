<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Jika user_id sudah dipilih, gunakan itu
        if (isset($data['user_id']) && $data['user_id']) {
            // Jika update_password diisi, update password user
            if (isset($data['update_password']) && $data['update_password']) {
                User::where('id', $data['user_id'])->update([
                    'password' => Hash::make($data['update_password']),
                ]);
            }
            unset($data['email'], $data['password'], $data['update_password']);
            return $data;
        }

        // Jika email dan password diisi, buat user baru
        if (isset($data['email']) && isset($data['password']) && $data['email'] && $data['password']) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $data['user_id'] = $user->id;
        }

        // Hapus field yang tidak ada di tabel employees
        unset($data['email'], $data['password'], $data['update_password']);

        return $data;
    }
}
