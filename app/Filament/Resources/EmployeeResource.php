<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'HR';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()->schema([
                    Forms\Components\TextInput::make('nik')
                        ->label('NIK')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(16),
                    Forms\Components\TextInput::make('name')
                        ->label('Nama')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\DatePicker::make('birth_date')
                        ->label('Tanggal Lahir')
                        ->displayFormat('d/m/Y')
                        ->required(),
                    Forms\Components\TextInput::make('position')
                        ->label('Posisi')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('phone_number')
                        ->label('Nomor WhatsApp')
                        ->tel()
                        ->placeholder('081234567890')
                        ->helperText('Format: 081234567890 (untuk notifikasi WhatsApp)')
                        ->maxLength(20),
                    Forms\Components\TextInput::make('base_salary')
                        ->label('Gaji')
                        ->numeric()
                        ->prefix('Rp')
                        ->required(),
                    Forms\Components\TextInput::make('bpjs_allowance')
                        ->label('Potongan BPJS')
                        ->numeric()
                        ->prefix('Rp')
                        ->default(0)
                        ->required(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true),
                ])->columns(2),
                Forms\Components\Section::make('Akun Login Karyawan')
                    ->description('Atur email dan password untuk login karyawan di /employee')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Pilih User yang Sudah Ada')
                            ->relationship('user', 'email')
                            ->searchable()
                            ->preload()
                            ->helperText('Pilih user yang sudah ada, atau buat user baru di bawah')
                            ->reactive(),
                        Forms\Components\Placeholder::make('user_info')
                            ->label('Info User')
                            ->content(fn ($record) => $record && $record->user ? 'User: ' . $record->user->email : 'Belum ada user')
                            ->visible(fn ($get, $record) => ($get('user_id') || $record?->user_id)),
                        Forms\Components\TextInput::make('email')
                            ->label('Email (Buat User Baru)')
                            ->email()
                            ->rules([
                                'unique:users,email',
                            ])
                            ->helperText('Isi email dan password untuk membuat user baru')
                            ->visible(fn ($get) => !$get('user_id'))
                            ->required(fn ($get) => !$get('user_id')),
                        Forms\Components\TextInput::make('password')
                            ->label('Password (Buat User Baru)')
                            ->password()
                            ->minLength(8)
                            ->helperText('Minimal 8 karakter')
                            ->visible(fn ($get) => !$get('user_id'))
                            ->required(fn ($get) => !$get('user_id')),
                        Forms\Components\TextInput::make('update_password')
                            ->label('Update Password')
                            ->password()
                            ->minLength(8)
                            ->helperText('Kosongkan jika tidak ingin mengubah password')
                            ->visible(fn ($get, $record) => ($get('user_id') || $record?->user_id)),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nik')
                    ->label('NIK')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('position')
                    ->label('Posisi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone_number')
                    ->label('Nomor WhatsApp')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_salary')
                    ->label('Gaji')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif')
                    ->falseLabel('Tidak Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('generateSalarySlip')
                    ->label('Slip Gaji')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->url(fn (Employee $record) => route('employee.salary-slip', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
