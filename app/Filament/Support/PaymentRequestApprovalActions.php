<?php

namespace App\Filament\Support;

use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;

final class PaymentRequestApprovalActions
{
    /**
     * Shared Approve / Reject / Set Paid for cashbon, budget request, reimbursement.
     *
     * @param  callable(Model): bool|null  $extraVisible  Extra gate (e.g. hide for cashbon term loans)
     * @return array<int, Action>
     */
    public static function make(string $subject, ?callable $extraVisible = null): array
    {
        $canAct = function (?Model $record, string $requiredStatus) use ($extraVisible): bool {
            if (! $record || ($record->status ?? null) !== $requiredStatus) {
                return false;
            }

            return $extraVisible ? (bool) $extraVisible($record) : true;
        };

        return [
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (?Model $record) => $canAct($record, 'pending'))
                ->action(function (Model $record): void {
                    $record->update(['status' => 'approved']);
                })
                ->successNotificationTitle("{$subject} approved")
                ->refreshAfter(),
            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (?Model $record) => $canAct($record, 'pending'))
                ->action(function (Model $record): void {
                    $record->update(['status' => 'rejected']);
                })
                ->successNotificationTitle("{$subject} rejected")
                ->refreshAfter(),
            Action::make('markAsPaid')
                ->label('Set Paid')
                ->icon('heroicon-o-banknotes')
                ->color('info')
                ->visible(fn (?Model $record) => $canAct($record, 'approved'))
                ->action(function (Model $record): void {
                    $record->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                    ]);
                })
                ->successNotificationTitle("{$subject} marked as paid")
                ->refreshAfter(),
        ];
    }
}
