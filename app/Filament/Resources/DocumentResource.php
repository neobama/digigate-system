<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentResource\Pages;
use App\Models\Document;
use App\Models\Invoice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Dokumen';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Dokumen')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Dokumen')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: Kontrak Client ABC'),
                        Forms\Components\Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('category')
                            ->label('Kategori')
                            ->options([
                                'invoice' => 'Invoice',
                                'contract' => 'Kontrak',
                                'certificate' => 'Sertifikat',
                                'license' => 'Lisensi',
                                'legal' => 'Legal',
                                'financial' => 'Keuangan',
                                'hr' => 'HR',
                                'technical' => 'Technical',
                                'other' => 'Lainnya',
                            ])
                            ->searchable(),
                        Forms\Components\Select::make('related_invoice_id')
                            ->label('Link ke Invoice (Opsional)')
                            ->relationship('relatedInvoice', 'invoice_number')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('access_level')
                            ->label('Akses')
                            ->options([
                                'public' => 'Public (Semua bisa akses)',
                                'private' => 'Private (Hanya uploader)',
                                'restricted' => 'Restricted (Admin saja)',
                            ])
                            ->default('private')
                            ->required(),
                    ])->columns(2),
                Forms\Components\Section::make('Upload File')
                    ->schema([
                        Forms\Components\FileUpload::make('file_path')
                            ->label('File')
                            ->directory('documents')
                            ->disk(config('filesystems.default') === 's3' ? 's3_public' : 'public')
                            ->visibility('public')
                            ->acceptedFileTypes([
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'image/*',
                                'application/zip',
                                'application/x-rar-compressed',
                            ])
                            ->maxSize(10240) // 10MB
                            ->required()
                            ->columnSpanFull()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                if ($state) {
                                    // Auto-set name dari filename jika belum diisi
                                    if (!$get('name')) {
                                        $fileName = basename($state);
                                        $set('name', pathinfo($fileName, PATHINFO_FILENAME));
                                    }
                                }
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('file_icon')
                    ->label('')
                    ->icon(fn (Document $record): string => $record->file_icon)
                    ->size('lg'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Dokumen')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        'invoice' => 'Invoice',
                        'contract' => 'Kontrak',
                        'certificate' => 'Sertifikat',
                        'license' => 'Lisensi',
                        'legal' => 'Legal',
                        'financial' => 'Keuangan',
                        'hr' => 'HR',
                        'technical' => 'Technical',
                        'other' => 'Lainnya',
                        default => $state,
                    })
                    ->color('info'),
                Tables\Columns\TextColumn::make('formatted_file_size')
                    ->label('Ukuran')
                    ->sortable('file_size'),
                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('Diupload Oleh')
                    ->sortable(),
                Tables\Columns\TextColumn::make('relatedInvoice.invoice_number')
                    ->label('Invoice Terkait')
                    ->sortable(),
                Tables\Columns\TextColumn::make('access_level')
                    ->label('Akses')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        'public' => 'Public',
                        'private' => 'Private',
                        'restricted' => 'Restricted',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        'public' => 'success',
                        'private' => 'warning',
                        'restricted' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Upload')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Kategori')
                    ->options([
                        'invoice' => 'Invoice',
                        'contract' => 'Kontrak',
                        'certificate' => 'Sertifikat',
                        'license' => 'Lisensi',
                        'legal' => 'Legal',
                        'financial' => 'Keuangan',
                        'hr' => 'HR',
                        'technical' => 'Technical',
                        'other' => 'Lainnya',
                    ]),
                Tables\Filters\SelectFilter::make('access_level')
                    ->label('Akses')
                    ->options([
                        'public' => 'Public',
                        'private' => 'Private',
                        'restricted' => 'Restricted',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn (Document $record): string => Storage::disk('s3_public')->url($record->file_path))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
        ];
    }
}
