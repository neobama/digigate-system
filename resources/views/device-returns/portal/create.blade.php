<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Retur Perangkat - Digigate</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-3xl mx-auto">
            <div class="mb-6">
                <a href="{{ route('device-returns.portal.index') }}" class="text-blue-600 hover:text-blue-800 mb-4 inline-block">
                    ← Kembali ke Portal
                </a>
                <h1 class="text-3xl font-bold text-gray-900">Buat Retur Perangkat Baru</h1>
                <p class="text-gray-600 mt-2">Mohon isi formulir di bawah ini dengan lengkap</p>
            </div>

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('device-returns.portal.store') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-lg p-8">
                @csrf

                <div class="space-y-6">
                    <!-- Invoice & Purchase Date -->
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Invoice *</label>
                            <input type="text" name="invoice_number" value="{{ old('invoice_number') }}" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Pembelian *</label>
                            <input type="date" name="purchase_date" value="{{ old('purchase_date') }}" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>

                    <!-- Device Type & Serial Number -->
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Jenis Perangkat *</label>
                            <select name="device_type" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Pilih Jenis Perangkat</option>
                                <option value="Kasuari 6G 2S+" {{ old('device_type') == 'Kasuari 6G 2S+' ? 'selected' : '' }}>Kasuari 6G 2S+</option>
                                <option value="Maleo 6G 4S+" {{ old('device_type') == 'Maleo 6G 4S+' ? 'selected' : '' }}>Maleo 6G 4S+</option>
                                <option value="Macan 6G 4S+" {{ old('device_type') == 'Macan 6G 4S+' ? 'selected' : '' }}>Macan 6G 4S+</option>
                                <option value="Komodo 8G 4S+ 2QS28" {{ old('device_type') == 'Komodo 8G 4S+ 2QS28' ? 'selected' : '' }}>Komodo 8G 4S+ 2QS28</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Serial Number *</label>
                            <input type="text" name="serial_number" value="{{ old('serial_number') }}" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>

                    <!-- Mikrotik License -->
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="include_mikrotik_license" value="1" {{ old('include_mikrotik_license') ? 'checked' : '' }}
                                class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="ml-2 text-sm text-gray-700">Include License Mikrotik</span>
                        </label>
                    </div>

                    <!-- Customer Info -->
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nama *</label>
                            <input type="text" name="customer_name" value="{{ old('customer_name') }}" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Perusahaan</label>
                            <input type="text" name="company_name" value="{{ old('company_name') }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>

                    <!-- Phone Number -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nomor Telepon *</label>
                        <input type="tel" name="phone_number" value="{{ old('phone_number') }}" required
                            placeholder="081234567890"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <!-- Issue Details -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Detail Kendala *</label>
                        <textarea name="issue_details" rows="4" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="Jelaskan kendala yang dialami perangkat...">{{ old('issue_details') }}</textarea>
                    </div>

                    <!-- Proof Files -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Bukti Video/Foto Kendala</label>
                        <input type="file" name="proof_files[]" multiple accept="image/*,video/*"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <p class="mt-1 text-sm text-gray-500">Maksimal 10MB per file. Format: JPG, PNG, GIF, MP4, MOV, AVI</p>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4">
                        <button type="submit" class="w-full bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                            Submit Retur Perangkat
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
