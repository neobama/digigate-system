<?php

namespace App\Filament\Resources\LeaveRequestResource\Pages;

use App\Filament\Resources\LeaveRequestResource;
use App\Services\TelegramNotifier;
use Filament\Resources\Pages\CreateRecord;

class CreateLeaveRequest extends CreateRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'pending';

        return $data;
    }

    protected function afterCreate(): void
    {
        app(TelegramNotifier::class)->leaveRequestCreated($this->record);
    }
}
