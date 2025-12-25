<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasUuids;
    
    protected $fillable = [
        'name',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'category',
        'description',
        'uploaded_by',
        'related_invoice_id',
        'related_project_id',
        'access_level',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function relatedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'related_invoice_id');
    }

    // Helper untuk format file size
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    // Helper untuk icon berdasarkan mime type
    public function getFileIconAttribute(): string
    {
        $mime = $this->mime_type ?? '';
        
        if (str_contains($mime, 'pdf')) return 'heroicon-o-document-text';
        if (str_contains($mime, 'image')) return 'heroicon-o-photo';
        if (str_contains($mime, 'word') || str_contains($mime, 'document')) return 'heroicon-o-document';
        if (str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet')) return 'heroicon-o-table-cells';
        if (str_contains($mime, 'zip') || str_contains($mime, 'rar')) return 'heroicon-o-archive-box';
        
        return 'heroicon-o-document';
    }
}

