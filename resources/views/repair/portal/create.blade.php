<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Garansi / Service - DigiGate</title>
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
@endphp
<body class="min-h-screen text-brand-ink">
    <div class="mx-auto max-w-2xl px-4 py-10 sm:py-14">
        <header class="mb-8 text-center">
            <img
                src="{{ $logoUrl }}"
                alt="DigiGate"
                class="mx-auto h-12 w-auto sm:h-14"
            >
            <p class="mt-5 text-sm font-semibold uppercase tracking-[0.14em] text-brand-500">
                Repair Portal
            </p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-800">
                Form Garansi / Service
            </h1>
            <p class="mx-auto mt-2 max-w-md text-slate-600">
                Isi formulir di bawah untuk mengajukan garansi atau service perangkat DigiGate.
            </p>
        </header>

        @if ($errors->any())
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('repair.portal.store') }}" class="relative space-y-5 rounded-2xl bg-white/95 p-6 shadow-sm ring-1 ring-brand-200/70 backdrop-blur sm:p-8">
            @csrf

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Nomor Invoice *</label>
                    <input type="text" name="invoice_number" value="{{ old('invoice_number') }}" required
                        class="w-full rounded-lg border-slate-300 px-3 py-2 shadow-sm focus:border-brand-400 focus:ring-brand-400"
                        placeholder="Contoh: INV/2026/001">
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-slate-700">Tanggal Invoice *</label>
                    <input type="date" name="invoice_date" id="invoice_date" value="{{ old('invoice_date') }}" required
                        max="{{ now()->toDateString() }}"
                        class="w-full rounded-lg border-slate-300 px-3 py-2 shadow-sm focus:border-brand-400 focus:ring-brand-400">
                </div>
            </div>

            <div id="warranty-notice" class="hidden rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
                <p class="font-semibold">Perangkat sudah lewat masa garansi (1 tahun).</p>
                <p class="mt-1">
                    Pengajuan akan diproses sebagai <strong>repair berbayar</strong>.
                    Informasi biaya repair akan dikirim ke nomor telepon yang Anda lampirkan.
                </p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Nama Perusahaan / Perorangan *</label>
                <input type="text" name="client_name" value="{{ old('client_name') }}" required
                    class="w-full rounded-lg border-slate-300 px-3 py-2 shadow-sm focus:border-brand-400 focus:ring-brand-400">
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Nomor Telepon yang Bisa Dihubungi *</label>
                <input type="text" name="phone_number" id="phone_number" value="{{ old('phone_number') }}" required
                    class="w-full rounded-lg border-slate-300 px-3 py-2 shadow-sm focus:border-brand-400 focus:ring-brand-400"
                    placeholder="08xxxxxxxxxx">
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Nama Perangkat *</label>
                <select name="device_type" required
                    class="w-full rounded-lg border-slate-300 px-3 py-2 shadow-sm focus:border-brand-400 focus:ring-brand-400">
                    <option value="">Pilih perangkat</option>
                    @foreach ($deviceTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('device_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Serial Number *</label>
                <input type="text" name="assembly_serial_number" value="{{ old('assembly_serial_number') }}" required
                    class="w-full rounded-lg border-slate-300 px-3 py-2 uppercase shadow-sm focus:border-brand-400 focus:ring-brand-400"
                    placeholder="Contoh: DG202610001">
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Keluhan *</label>
                <textarea name="complaint" rows="5" required
                    class="w-full rounded-lg border-slate-300 px-3 py-2 shadow-sm focus:border-brand-400 focus:ring-brand-400"
                    placeholder="Jelaskan keluhan atau kerusakan perangkat...">{{ old('complaint') }}</textarea>
            </div>

            {{-- Honeypot anti-bot --}}
            <div class="absolute -left-[9999px] top-auto h-0 w-0 overflow-hidden" aria-hidden="true">
                <label for="company_website">Website</label>
                <input type="text" name="company_website" id="company_website" value="" tabindex="-1" autocomplete="off">
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-medium text-slate-700">Kode Keamanan (Captcha) *</label>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="overflow-hidden rounded-lg ring-1 ring-brand-200 bg-brand-50">
                        {!! $captchaSvg !!}
                    </div>
                    <a href="{{ route('repair.portal.captcha') }}"
                        id="refresh-captcha"
                        class="text-sm font-medium text-brand-500 hover:text-brand-600 underline-offset-2 hover:underline">
                        Ganti kode
                    </a>
                </div>
                <input type="text" name="captcha" value="" required autocomplete="off" maxlength="10"
                    class="mt-3 w-full rounded-lg border-slate-300 px-3 py-2 uppercase shadow-sm focus:border-brand-400 focus:ring-brand-400 @error('captcha') border-red-400 @enderror"
                    placeholder="Ketik kode di gambar">
                @error('captcha')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                class="w-full rounded-xl bg-brand-500 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-400 focus:ring-offset-2">
                Kirim Pengajuan
            </button>
        </form>
    </div>

    <script>
        (function () {
            const input = document.getElementById('invoice_date');
            const notice = document.getElementById('warranty-notice');
            if (input && notice) {
                function isOutOfWarranty(dateValue) {
                    if (!dateValue) return false;
                    const invoice = new Date(dateValue + 'T00:00:00');
                    if (Number.isNaN(invoice.getTime())) return false;
                    const expires = new Date(invoice);
                    expires.setFullYear(expires.getFullYear() + 1);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    return today > expires;
                }

                function refreshNotice() {
                    notice.classList.toggle('hidden', !isOutOfWarranty(input.value));
                }

                input.addEventListener('change', refreshNotice);
                input.addEventListener('input', refreshNotice);
                refreshNotice();
            }

            const refreshCaptcha = document.getElementById('refresh-captcha');
            if (refreshCaptcha) {
                refreshCaptcha.addEventListener('click', async function (event) {
                    event.preventDefault();
                    try {
                        const response = await fetch(refreshCaptcha.href, {
                            headers: { 'Accept': 'image/svg+xml' },
                            credentials: 'same-origin',
                        });
                        if (!response.ok) return;
                        const svg = await response.text();
                        const holder = refreshCaptcha.previousElementSibling;
                        if (holder) holder.innerHTML = svg;
                    } catch (e) {
                        window.location.reload();
                    }
                });
            }
        })();
    </script>
</body>
</html>
