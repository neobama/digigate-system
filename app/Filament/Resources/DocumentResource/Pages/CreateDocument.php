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
        
        // Extract file info
        if (isset($data['file_path'])) {
            $filePath = $data['file_path'];
            $data['file_name'] = basename($filePath);
            
            // Get file info from S3
            try {
                $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
                if (Storage::disk($disk)->exists($filePath)) {
                    $fileInfo = Storage::disk($disk)->getMetadata($filePath);
                    $data['mime_type'] = $fileInfo['mimetype'] ?? null;
                    $data['file_size'] = $fileInfo['size'] ?? 0;
                } else {
                    // Fallback jika metadata tidak tersedia
                    $data['mime_type'] = 'application/octet-stream';
                    $data['file_size'] = 0;
                }
            } catch (\Exception $e) {
                // Fallback
                $data['mime_type'] = 'application/octet-stream';
                $data['file_size'] = 0;
            }
        }
        
        return $data;
    }
}
