<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by'] = auth()->id();
        
        // Extract file info - use extension-based detection to avoid S3 access issues
        if (isset($data['file_path'])) {
            $filePath = $data['file_path'];
            $data['file_name'] = basename($filePath);
            
            // Determine mime type from extension (safer, no S3 access needed)
            $extension = pathinfo($filePath, PATHINFO_EXTENSION);
            $data['mime_type'] = match(strtolower($extension)) {
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'zip' => 'application/zip',
                'rar' => 'application/x-rar-compressed',
                default => 'application/octet-stream',
            };
            
            // File size will be updated after file is saved (in after hook)
            $data['file_size'] = 0;
        }
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Update file info after file is saved to S3
        $record = $this->record;
        if ($record->file_path) {
            try {
                $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
                if (Storage::disk($disk)->exists($record->file_path)) {
                    $fileSize = Storage::disk($disk)->size($record->file_path);
                    
                    // Try to get mime type from S3
                    try {
                        $mimeType = Storage::disk($disk)->mimeType($record->file_path);
                        $record->update([
                            'file_size' => $fileSize,
                            'mime_type' => $mimeType,
                        ]);
                    } catch (\Exception $e) {
                        // Keep extension-based mime type, just update size
                        $record->update([
                            'file_size' => $fileSize,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // Silently fail - file info already set from extension
            }
        }
    }
}
