<div class="space-y-4">
    @php
        $mimeType = $document->mime_type ?? 'application/octet-stream';
        $isPdf = str_contains($mimeType, 'pdf');
        $isImage = str_starts_with($mimeType, 'image/');
        $isOffice = str_contains($mimeType, 'word') || 
                    str_contains($mimeType, 'excel') || 
                    str_contains($mimeType, 'powerpoint') ||
                    str_contains($mimeType, 'spreadsheet') ||
                    str_contains($mimeType, 'document');
    @endphp

    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="font-semibold">Nama File:</span>
                <p class="text-gray-700 dark:text-gray-300">{{ $document->file_name }}</p>
            </div>
            <div>
                <span class="font-semibold">Ukuran:</span>
                <p class="text-gray-700 dark:text-gray-300">{{ $document->formatted_file_size }}</p>
            </div>
            <div>
                <span class="font-semibold">Tipe:</span>
                <p class="text-gray-700 dark:text-gray-300">{{ $mimeType }}</p>
            </div>
            <div>
                <span class="font-semibold">Kategori:</span>
                <p class="text-gray-700 dark:text-gray-300">
                    @php
                        $categoryLabels = [
                            'invoice' => 'Invoice',
                            'contract' => 'Kontrak',
                            'certificate' => 'Sertifikat',
                            'license' => 'Lisensi',
                            'legal' => 'Legal',
                            'financial' => 'Keuangan',
                            'hr' => 'HR',
                            'technical' => 'Technical',
                            'other' => 'Lainnya',
                        ];
                    @endphp
                    {{ $categoryLabels[$document->category] ?? $document->category ?? '-' }}
                </p>
            </div>
        </div>
        @if($document->description)
            <div class="mt-4">
                <span class="font-semibold">Deskripsi:</span>
                <p class="text-gray-700 dark:text-gray-300">{{ $document->description }}</p>
            </div>
        @endif
    </div>

    <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
        @if($isPdf)
            {{-- PDF Preview --}}
            <iframe 
                src="{{ $fileUrl }}#toolbar=1" 
                class="w-full" 
                style="height: 70vh; min-height: 600px;"
                frameborder="0">
            </iframe>
        @elseif($isImage)
            {{-- Image Preview --}}
            <div class="flex items-center justify-center bg-gray-100 dark:bg-gray-900 p-4" style="min-height: 400px;">
                <img 
                    src="{{ $fileUrl }}" 
                    alt="{{ $document->name }}"
                    class="max-w-full max-h-[70vh] object-contain rounded-lg shadow-lg"
                    onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'400\' height=\'300\'%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%23999\'%3EGagal memuat gambar%3C/text%3E%3C/svg%3E';">
            </div>
        @elseif($isOffice)
            {{-- Office Document Preview (using Google Docs Viewer or Office Online) --}}
            <div class="bg-gray-50 dark:bg-gray-800 p-8 text-center">
                <div class="mb-4">
                    <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    Preview tidak tersedia untuk file Office. Silakan download untuk membuka.
                </p>
                <a 
                    href="{{ $fileUrl }}" 
                    target="_blank"
                    class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Download File
                </a>
            </div>
        @else
            {{-- Other File Types --}}
            <div class="bg-gray-50 dark:bg-gray-800 p-8 text-center">
                <div class="mb-4">
                    <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <p class="text-gray-600 dark:text-gray-400 mb-4">
                    Preview tidak tersedia untuk tipe file ini. Silakan download untuk membuka.
                </p>
                <a 
                    href="{{ $fileUrl }}" 
                    target="_blank"
                    class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Download File
                </a>
            </div>
        @endif
    </div>
</div>

