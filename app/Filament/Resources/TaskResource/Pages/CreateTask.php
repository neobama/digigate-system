<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    public function mount(): void
    {
        parent::mount();

        $prefillRaw = request()->query('prefill');
        if (! $prefillRaw) {
            return;
        }

        $decoded = json_decode(base64_decode((string) $prefillRaw), true);
        if (! is_array($decoded)) {
            return;
        }

        $allowed = array_intersect_key($decoded, array_flip([
            'title',
            'description',
            'start_date',
            'end_date',
            'employees',
        ]));

        if (! empty($allowed)) {
            $this->form->fill($allowed);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        $data['is_self_assigned'] = false;

        return $data;
    }
}
