<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Terkirim - DigiGate Repair</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <div class="mx-auto flex min-h-screen max-w-xl items-center px-4 py-10">
        <div class="w-full rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-200">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-2xl text-green-700">✓</div>
            <h1 class="text-2xl font-bold">Pengajuan Berhasil Dikirim</h1>
            <p class="mt-2 text-slate-600">
                Tim DigiGate akan menindaklanjuti garansi/service Anda.
            </p>

            <div class="mt-6 rounded-xl bg-slate-50 px-4 py-3">
                <p class="text-xs uppercase tracking-wide text-slate-500">Nomor Tiket</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ $ticketNumber }}</p>
            </div>

            <p class="mt-4 text-sm text-slate-500">
                @if ($isAssemblyLinked)
                    Serial number terhubung otomatis ke data perangkat di sistem.
                @else
                    Serial number belum terdeteksi di sistem; tim akan verifikasi secara manual.
                @endif
            </p>

            <a href="{{ route('repair.portal.create') }}"
                class="mt-8 inline-flex rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">
                Kirim Pengajuan Lain
            </a>
        </div>
    </div>
</body>
</html>
