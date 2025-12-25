<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentDocumentsWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Dokumen Terbaru')
            ->description('5 dokumen yang baru diupload')
            ->query(
                Document::query()->latest()->limit(5)
            )
            ->columns([
                Tables\Columns\IconColumn::make('file_icon')
                    ->label('')
                    ->icon(fn (Document $record): string => $record->file_icon),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->limit(30)
                    ->searchable()
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
                    }),
                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('Uploader'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->paginated(false);
    }
}

