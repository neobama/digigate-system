<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking Retur Perangkat - Digigate</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="mb-6">
                <a href="{{ route('device-returns.portal.index') }}" class="text-blue-600 hover:text-blue-800 mb-4 inline-block">
                    ← Kembali ke Portal
                </a>
                <h1 class="text-3xl font-bold text-gray-900">Tracking Retur Perangkat</h1>
                <p class="text-gray-600 mt-2">Masukkan nomor resi untuk melihat status retur Anda</p>
            </div>

            <!-- Search Form -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <form action="{{ route('device-returns.portal.tracking') }}" method="GET" class="flex gap-4">
                    <input type="text" name="tracking_number" value="{{ $trackingNumber }}" 
                        placeholder="Masukkan nomor resi (contoh: RT-20240206-ABC123)"
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                        Cari
                    </button>
                </form>
            </div>

            @if($trackingNumber && !$deviceReturn)
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg">
                    Nomor resi tidak ditemukan. Pastikan nomor resi yang Anda masukkan benar.
                </div>
            @endif

            @if($deviceReturn)
                <!-- Return Info -->
                <div class="bg-white rounded-lg shadow-lg p-8 mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Informasi Retur</h2>
                    
                    <div class="grid md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Nomor Resi</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $deviceReturn->tracking_number }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Status</p>
                            <span class="inline-block px-3 py-1 rounded-full text-sm font-medium
                                @if($deviceReturn->status == 'pending') bg-yellow-100 text-yellow-800
                                @elseif($deviceReturn->status == 'received') bg-blue-100 text-blue-800
                                @elseif($deviceReturn->status == 'in_progress') bg-purple-100 text-purple-800
                                @elseif($deviceReturn->status == 'completed') bg-green-100 text-green-800
                                @else bg-red-100 text-red-800
                                @endif">
                                @if($deviceReturn->status == 'pending') Pending
                                @elseif($deviceReturn->status == 'received') Received
                                @elseif($deviceReturn->status == 'in_progress') In Progress
                                @elseif($deviceReturn->status == 'completed') Completed
                                @else Cancelled
                                @endif
                            </span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Jenis Perangkat</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $deviceReturn->device_type }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Serial Number</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $deviceReturn->serial_number }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Nomor Invoice</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $deviceReturn->invoice_number }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Tanggal Pembelian</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $deviceReturn->purchase_date->format('d/m/Y') }}</p>
                        </div>
                    </div>

                    @if($deviceReturn->issue_details)
                        <div class="mb-6">
                            <p class="text-sm text-gray-500 mb-1">Detail Kendala</p>
                            <p class="text-gray-900">{{ $deviceReturn->issue_details }}</p>
                        </div>
                    @endif

                    @if($deviceReturn->proof_files && count($deviceReturn->proof_files) > 0)
                        <div>
                            <p class="text-sm text-gray-500 mb-2">Bukti Video/Foto</p>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                @foreach($deviceReturn->proof_files as $file)
                                    @php
                                        $disk = config('filesystems.default') === 's3' ? 's3_public' : 'public';
                                        $isVideo = in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['mp4', 'mov', 'avi', 'mkv', 'webm']);
                                        $url = \Illuminate\Support\Facades\Storage::disk($disk)->url($file);
                                    @endphp
                                    <a href="{{ $url }}" target="_blank" class="block">
                                        @if($isVideo)
                                            <div class="relative w-full aspect-video bg-gray-900 rounded-lg flex items-center justify-center">
                                                <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l11.032-5.925a1.5 1.5 0 000-2.538L6.3 2.84z"/>
                                                </svg>
                                            </div>
                                        @else
                                            <img src="{{ $url }}" alt="Bukti" class="w-full h-auto rounded-lg object-cover" style="max-height: 150px;">
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Status Logs -->
                <div class="bg-white rounded-lg shadow-lg p-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Riwayat Status</h2>
                    
                    <div class="space-y-4">
                        @forelse($deviceReturn->logs as $log)
                            <div class="flex gap-4 pb-4 border-b border-gray-200 last:border-0">
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center
                                        @if($log->status == 'pending') bg-yellow-100 text-yellow-800
                                        @elseif($log->status == 'received') bg-blue-100 text-blue-800
                                        @elseif($log->status == 'in_progress') bg-purple-100 text-purple-800
                                        @elseif($log->status == 'completed') bg-green-100 text-green-800
                                        @else bg-red-100 text-red-800
                                        @endif">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="font-semibold text-gray-900">
                                            @if($log->status == 'pending') Pending
                                            @elseif($log->status == 'received') Received
                                            @elseif($log->status == 'in_progress') In Progress
                                            @elseif($log->status == 'completed') Completed
                                            @else Cancelled
                                            @endif
                                        </span>
                                        <span class="text-sm text-gray-500">{{ $log->logged_at->format('d/m/Y H:i') }}</span>
                                    </div>
                                    @if($log->description)
                                        <p class="text-sm text-gray-600">{{ $log->description }}</p>
                                    @endif
                                    @if($log->loggedByUser)
                                        <p class="text-xs text-gray-400 mt-1">Ditambahkan oleh: {{ $log->loggedByUser->name }}</p>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500 text-center py-8">Belum ada riwayat status</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
