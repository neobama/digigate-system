<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Retur Perangkat - Digigate</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-12">
        <div class="max-w-4xl mx-auto">
            <div class="text-center mb-12">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">Portal Retur Perangkat</h1>
                <p class="text-lg text-gray-600">Layanan retur perangkat Digigate</p>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <!-- Create New Return Card -->
                <div class="bg-white rounded-lg shadow-lg p-8 hover:shadow-xl transition-shadow">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-semibold text-gray-900 mb-2">Buat Retur Baru</h2>
                        <p class="text-gray-600 mb-6">Ajukan retur perangkat baru dengan mengisi formulir retur</p>
                        <a href="{{ route('device-returns.portal.create') }}" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                            Buat Retur Baru
                        </a>
                    </div>
                </div>

                <!-- Track Return Card -->
                <div class="bg-white rounded-lg shadow-lg p-8 hover:shadow-xl transition-shadow">
                    <div class="text-center">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-semibold text-gray-900 mb-2">Tracking Retur</h2>
                        <p class="text-gray-600 mb-6">Lacak status retur perangkat Anda menggunakan nomor resi</p>
                        <a href="{{ route('device-returns.portal.tracking') }}" class="inline-block bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors font-medium">
                            Tracking Retur
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
