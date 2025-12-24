<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Jika user_id sudah dipilih, gunakan itu
        if (isset($data['user_id']) && $data['user_id']) {
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
        unset($data['email'], $data['password']);

        return $data;
    }
}
