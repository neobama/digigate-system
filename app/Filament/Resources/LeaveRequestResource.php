<?php

namespace App\Filament\Resources;

use App\Enums\LeaveRequestType;
use App\Filament\Resources\LeaveRequestResource\Pages;
use App\Models\LeaveRequest;
use App\Services\TelegramNotifier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Izin / Cuti / Lembur';

    protected static ?string $navigationGroup = 'HR';

    protected static ?string $modelLabel = 'Pengajuan';

    protected static ?string $pluralModelLabel = 'Izin / Cuti / Lembur';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()->schema([
                    Forms\Components\Select::make('employee_id')
                        ->label('Karyawan')
                        ->relationship('employee', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
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
                        ->columnSpanFull(),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'pending' => 'Pending',
                            'approved' => 'Disetujui',
                            'rejected' => 'Ditolak',
                        ])
                        ->disabled()
                        ->dehydrated(false)
                        ->visibleOn('edit')
                        ->helperText('Ubah status lewat tombol Approve / Reject di tabel.'),
                    Forms\Components\Textarea::make('review_notes')
                        ->label('Catatan Review')
                        ->rows(2)
                        ->visibleOn('edit')
                        ->columnSpanFull(),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['employee', 'reviewer']))
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable(),
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
                    ->placeholder('-')
                    ->toggleable(),
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
                Tables\Columns\TextColumn::make('reviewer.name')
                    ->label('Direview')
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
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (?LeaveRequest $record) => $record?->status === 'pending')
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
                    })
                    ->successNotificationTitle('Pengajuan disetujui')
                    ->refreshAfter(),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (?LeaveRequest $record) => $record?->status === 'pending')
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
                    })
                    ->successNotificationTitle('Pengajuan ditolak')
                    ->refreshAfter(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeaveRequests::route('/'),
            'create' => Pages\CreateLeaveRequest::route('/create'),
            'edit' => Pages\EditLeaveRequest::route('/{record}/edit'),
        ];
    }
}
