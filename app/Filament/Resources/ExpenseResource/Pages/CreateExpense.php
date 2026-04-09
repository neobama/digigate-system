<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

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
            'vendor_invoice_number',
            'description',
            'account_code',
            'fund_source',
            'expense_date',
            'amount',
        ]));

        if ($allowed !== []) {
            $this->form->fill($allowed);
        }
    }
}
