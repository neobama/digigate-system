<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Terkirim - DigiGate Repair</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef7fc',
                            100: '#d9eef9',
                            200: '#b6dff3',
                            400: '#00aae6',
                            500: '#0a6eb4',
                            600: '#085a96',
                            700: '#064876',
                            ink: '#50505a',
                        },
                    },
                },
            },
        };
    </script>
    <style>
        body {
            background:
                radial-gradient(ellipse 80% 50% at 50% -10%, rgba(0, 170, 230, 0.18), transparent 55%),
                linear-gradient(180deg, #eef7fc 0%, #f4f7fb 45%, #e8f1f8 100%);
        }
    </style>
</head>
@php
    $logoUrl = \Illuminate\Support\Facades\Storage::disk('s3_public')->url('assets/digigate-logo.png');
    $isRepair = $claimCategory === 'repair';
@endphp
<body class="min-h-screen text-brand-ink">
    <div class="mx-auto flex min-h-screen max-w-xl items-center px-4 py-10">
        <div class="w-full rounded-2xl bg-white/95 p-8 text-center shadow-sm ring-1 ring-brand-200/70 backdrop-blur">
            <img
                src="{{ $logoUrl }}"
                alt="DigiGate"
                class="mx-auto mb-6 h-11 w-auto"
            >
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-brand-100 text-2xl font-semibold text-brand-500">✓</div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-800">Pengajuan Berhasil Dikirim</h1>
            <p class="mt-2 text-slate-600">
                Tim DigiGate akan menindaklanjuti pengajuan Anda.
            </p>

            <div class="mt-6 rounded-xl bg-brand-50 px-4 py-3 ring-1 ring-brand-100">
                <p class="text-xs uppercase tracking-wide text-brand-500">Nomor Tiket</p>
                <p class="mt-1 text-xl font-bold text-slate-800">{{ $ticketNumber }}</p>
                <p class="mt-2 text-sm font-medium {{ $isRepair ? 'text-amber-700' : 'text-brand-600' }}">
                    Kategori: {{ $isRepair ? 'Repair' : 'Garansi' }}
                </p>
            </div>

            @if ($isRepair)
                <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-left text-sm text-amber-900">
                    <p class="font-semibold">Perangkat sudah lewat masa garansi.</p>
                    <p class="mt-1">
                        Pengajuan diproses sebagai repair berbayar. Informasi biaya repair akan dikirim ke nomor
                        <strong>{{ $phoneNumber }}</strong>.
                    </p>
                </div>
            @endif

            <a href="{{ route('repair.portal.create') }}"
                class="mt-8 inline-flex rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-400 focus:ring-offset-2">
                Kirim Pengajuan Lain
            </a>
        </div>
    </div>
</body>
</html>
