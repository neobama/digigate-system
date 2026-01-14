<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskResource\Pages;
use App\Models\Task;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Kalender Pekerjaan';
    protected static ?string $navigationGroup = 'HR';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('Judul Pekerjaan')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\Textarea::make('description')
                        ->label('Deskripsi')
                        ->rows(3),
                    Forms\Components\DatePicker::make('start_date')
                        ->label('Tanggal Mulai')
                        ->required()
                        ->default(now()),
                    Forms\Components\DatePicker::make('end_date')
                        ->label('Tanggal Selesai')
                        ->required()
                        ->default(now())
                        ->after('start_date'),
                    Forms\Components\Select::make('employees')
                        ->label('Assign ke Karyawan')
                        ->multiple()
                        ->relationship('employees', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'pending' => 'Pending',
                            'in_progress' => 'In Progress',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled',
                        ])
                        ->default('pending')
                        ->required(),
                ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('employees.name')
                    ->label('Karyawan')
                    ->badge()
                    ->separator(','),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Mulai')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Selesai')
                    ->date()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'in_progress',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ]),
                Tables\Columns\IconColumn::make('is_self_assigned')
                    ->label('Self Assigned')
                    ->boolean(),
                Tables\Columns\TextColumn::make('proof_images')
                    ->label('Bukti Pekerjaan')
                    ->getStateUsing(function (Task $record) {
                        $images = $record->proof_images ?? [];
                        return is_array($images) && count($images) > 0 ? count($images) . ' foto' : '-';
                    })
                    ->badge()
                    ->color(fn ($state) => $state !== '-' ? 'success' : 'gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('employees')
                    ->label('Karyawan')
                    ->relationship('employees', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informasi Pekerjaan')
                    ->schema([
                        Infolists\Components\TextEntry::make('title')
                            ->label('Judul Pekerjaan'),
                        Infolists\Components\TextEntry::make('description')
                            ->label('Deskripsi')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('employees.name')
                            ->label('Karyawan')
                            ->badge()
                            ->separator(','),
                        Infolists\Components\TextEntry::make('start_date')
                            ->label('Tanggal Mulai')
                            ->date(),
                        Infolists\Components\TextEntry::make('end_date')
                            ->label('Tanggal Selesai')
                            ->date(),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($state) => match($state) {
                                'pending' => 'warning',
                                'in_progress' => 'info',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\IconEntry::make('is_self_assigned')
                            ->label('Self Assigned')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Catatan')
                            ->columnSpanFull()
                            ->visible(fn ($record) => !empty($record->notes)),
                    ])->columns(2),
                Infolists\Components\Section::make('Bukti Pekerjaan')
                    ->schema([
                        Infolists\Components\ViewEntry::make('proof_images')
                            ->label('')
                            ->view('filament.infolists.components.task-proof-images')
                            ->viewData(function (Task $record) {
                                return [
                                    'images' => is_array($record->proof_images) ? $record->proof_images : [],
                                ];
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Task $record) => !empty($record->proof_images)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'view' => Pages\ViewTask::route('/{record}'),
            'edit' => Pages\EditTask::route('/{record}/edit'),
            'calendar' => Pages\TaskCalendar::route('/calendar'),
        ];
    }
}
