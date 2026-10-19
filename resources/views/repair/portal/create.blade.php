<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Garansi / Service - DigiGate</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <div class="mx-auto max-w-2xl px-4 py-10">
        <div class="mb-8">
            <p class="text-sm font-semibold uppercase tracking-wide text-amber-700">DigiGate Repair</p>
            <h1 class="mt-2 text-3xl font-bold">Form Garansi / Service</h1>
            <p class="mt-2 text-slate-600">
                Isi formulir di bawah untuk mengajukan garansi atau service perangkat DigiGate.
            </p>
        </div>

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('repair.portal.store') }}" class="space-y-5 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            @csrf

            <div>
                <label class="mb-1.5 block text-sm font-medium">Nomor Invoice *</label>
                <input type="text" name="invoice_number" value="{{ old('invoice_number') }}" required
                    class="w-full rounded-lg border-slate-300 px-3 py-2 shadow-sm focus:border-amber-500 focus:ring-amber-500"
                    placeholder="Contoh: INV/2026/001">
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium">Nama Perusahaan / Perorangan *</label>
                <input type="text" name="client_name" value="{{ old('client_name') }}" required
                    class="w-full rounded-lg border-slate-300 px-3 py-2 shadow-sm focus:border-amber-500 focus:ring-amber-500">
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium">Nomor Telepon yang Bisa Dihubungi *</label>
                <input type="text" name="phone_number" value="{{ old('phone_number') }}" required
                    class="w-full rounded-lg border-slate-300 px-3 py-2 shadow-sm focus:border-amber-500 focus:ring-amber-500"
                    placeholder="08xxxxxxxxxx">
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium">Nama Perangkat *</label>
                <select name="device_type" required
                    class="w-full rounded-lg border-slate-300 px-3 py-2 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    <option value="">Pilih perangkat</option>
                    @foreach ($deviceTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('device_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium">Serial Number *</label>
                <input type="text" name="assembly_serial_number" value="{{ old('assembly_serial_number') }}" required
                    class="w-full rounded-lg border-slate-300 px-3 py-2 uppercase shadow-sm focus:border-amber-500 focus:ring-amber-500"
                    placeholder="Contoh: DG202610001">
                <p class="mt-1 text-xs text-slate-500">
                    Jika SN ada di sistem DigiGate, akan otomatis terhubung ke data assembly.
                </p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium">Keluhan *</label>
                <textarea name="complaint" rows="5" required
                    class="w-full rounded-lg border-slate-300 px-3 py-2 shadow-sm focus:border-amber-500 focus:ring-amber-500"
                    placeholder="Jelaskan keluhan atau kerusakan perangkat...">{{ old('complaint') }}</textarea>
            </div>

            <button type="submit"
                class="w-full rounded-xl bg-amber-600 px-4 py-3 text-sm font-semibold text-white hover:bg-amber-700">
                Kirim Pengajuan
            </button>
        </form>
    </div>
</body>
</html>
