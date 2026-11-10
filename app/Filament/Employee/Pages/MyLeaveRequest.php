<?php

namespace App\Filament\Employee\Pages;

use App\Enums\LeaveRequestType;
use App\Models\LeaveRequest;
use App\Services\TelegramNotifier;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MyLeaveRequest extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static string $view = 'filament.employee.pages.my-leave-request';

    protected static ?string $navigationLabel = 'Izin / Cuti / Lembur';

    protected static ?string $title = 'Pengajuan Izin / Cuti / Lembur';

    protected static ?int $navigationSort = 4;

    public function table(Table $table): Table
    {
        $employee = Auth::user()?->employee;
        $isSupervisor = $employee?->isSupervisor() ?? false;

        return $table
            ->query($this->leaveQuery($employee?->id, $isSupervisor))
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable()
                    ->visible($isSupervisor),
                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof LeaveRequestType ? $state->label() : ($state ?? '-'))
                    ->color(fn ($state) => match ($state instanceof LeaveRequestType ? $state : null) {
                        LeaveRequestType::Sakit => 'danger',
                        LeaveRequestType::Izin => 'warning',
                        LeaveRequestType::Cuti => 'info',
                        LeaveRequestType::Lembur => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Mulai')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Selesai')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('hours')
                    ->label('Jam')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Alasan')
                    ->limit(40),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pending',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('review_notes')
                    ->label('Catatan')
                    ->limit(30)
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Disetujui',
                        'rejected' => 'Ditolak',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Jenis')
                    ->options(LeaveRequestType::options()),
                Tables\Filters\SelectFilter::make('employee_id')
                    ->label('Karyawan')
                    ->relationship('employee', 'name')
                    ->searchable()
                    ->preload()
                    ->visible($isSupervisor),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Ajukan Baru')
                    ->form($this->requestFormSchema())
                    ->mutateFormDataUsing(function (array $data) use ($employee): array {
                        $data['employee_id'] = $employee?->id;
                        $data['status'] = 'pending';

                        if (($data['type'] ?? null) !== LeaveRequestType::Lembur->value) {
                            $data['hours'] = null;
                        }

                        return $data;
                    })
                    ->using(function (array $data): LeaveRequest {
                        $request = LeaveRequest::create($data);
                        app(TelegramNotifier::class)->leaveRequestCreated($request);

                        return $request;
                    })
                    ->successNotificationTitle('Pengajuan terkirim'),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (?LeaveRequest $record) => $isSupervisor
                        && $record?->status === 'pending'
                        && $record->employee_id !== $employee?->id)
                    ->form([
                        Forms\Components\Textarea::make('review_notes')
                            ->label('Catatan (opsional)')
                            ->rows(2),
                    ])
                    ->action(function (LeaveRequest $record, array $data): void {
                        $record->update([
                            'status' => 'approved',
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                            'review_notes' => $data['review_notes'] ?? null,
                        ]);
                        app(TelegramNotifier::class)->leaveRequestStatusChanged($record);
                        Notification::make()->title('Pengajuan disetujui')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (?LeaveRequest $record) => $isSupervisor
                        && $record?->status === 'pending'
                        && $record->employee_id !== $employee?->id)
                    ->form([
                        Forms\Components\Textarea::make('review_notes')
                            ->label('Alasan penolakan')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (LeaveRequest $record, array $data): void {
                        $record->update([
                            'status' => 'rejected',
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                            'review_notes' => $data['review_notes'] ?? null,
                        ]);
                        app(TelegramNotifier::class)->leaveRequestStatusChanged($record);
                        Notification::make()->title('Pengajuan ditolak')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Belum ada pengajuan')
            ->emptyStateDescription('Ajukan izin, sakit, cuti, atau lembur lewat tombol di atas.');
    }

    protected function leaveQuery(?string $employeeId, bool $isSupervisor): Builder
    {
        $query = LeaveRequest::query()->with(['employee', 'reviewer']);

        if ($isSupervisor) {
            return $query;
        }

        return $query->where('employee_id', $employeeId);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected function requestFormSchema(): array
    {
        return [
            Forms\Components\Select::make('type')
                ->label('Jenis')
                ->options(LeaveRequestType::options())
                ->required()
                ->live(),
            Forms\Components\DatePicker::make('start_date')
                ->label('Tanggal Mulai')
                ->required()
                ->default(now()),
            Forms\Components\DatePicker::make('end_date')
                ->label('Tanggal Selesai')
                ->required()
                ->default(now())
                ->afterOrEqual('start_date'),
            Forms\Components\TextInput::make('hours')
                ->label('Jam Lembur')
                ->numeric()
                ->minValue(0.5)
                ->step(0.5)
                ->visible(fn (Forms\Get $get): bool => $get('type') === LeaveRequestType::Lembur->value)
                ->required(fn (Forms\Get $get): bool => $get('type') === LeaveRequestType::Lembur->value),
            Forms\Components\Textarea::make('reason')
                ->label('Alasan')
                ->required()
                ->rows(3)
                ->helperText('Izin/sakit/cuti yang disetujui dihitung sebagai hari sah untuk perhitungan gaji.'),
        ];
    }
}
