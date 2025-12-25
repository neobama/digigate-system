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
                    // Get file size
                    $data['file_size'] = Storage::disk($disk)->size($filePath);
                    
                    // Get mime type
                    try {
                        $data['mime_type'] = Storage::disk($disk)->mimeType($filePath);
                    } catch (\Exception $e) {
                        // Fallback: determine mime type from extension
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
                    }
                } else {
                    // Fallback jika file tidak ditemukan
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
