<?php

namespace App\Filament\Resources\IncomeResource\Pages;

use App\Filament\Resources\IncomeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIncome extends CreateRecord
{
    protected static string $resource = IncomeResource::class;

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
            'description',
            'income_date',
            'amount',
        ]));

        if ($allowed !== []) {
            $this->form->fill($allowed);
        }
    }
}
