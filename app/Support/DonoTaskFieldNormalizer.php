<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Menyetel ulang field task dari Dono: tanggal mengikuti server (bukan tahun halusinasi model),
 * judul rapi (title case), deskripsi tidak sama dengan judul.
 */
final class DonoTaskFieldNormalizer
{
    public static function normalize(array $fields, string $userMessage): array
    {
        $tz = 'Asia/Jakarta';
        $today = Carbon::now($tz)->startOfDay();

        $fields = self::resolveRelativeDatesInMessage($fields, $userMessage, $today);
        $fields['start_date'] = self::sanitizeDateString($fields['start_date'] ?? null, $userMessage, $today);
        $fields['end_date'] = self::sanitizeDateString($fields['end_date'] ?? null, $userMessage, $today);

        if (! empty($fields['start_date']) && ! empty($fields['end_date'])) {
            try {
                $s = Carbon::parse($fields['start_date'], $tz)->startOfDay();
                $e = Carbon::parse($fields['end_date'], $tz)->startOfDay();
                if ($e->lt($s)) {
                    $fields['end_date'] = $fields['start_date'];
                }
            } catch (\Throwable) {
                // abaikan; form akan validasi
            }
        }

        $title = isset($fields['title']) ? trim((string) $fields['title']) : '';
        if ($title !== '') {
            $fields['title'] = self::formatTitle($title);
        }

        $desc = isset($fields['description']) ? trim((string) $fields['description']) : '';
        if ($title !== '' && ($desc === '' || mb_strtolower($desc) === mb_strtolower($title))) {
            $fields['description'] = self::buildDistinctDescription($title, $userMessage);
        } elseif ($desc !== '' && mb_strtolower($desc) === mb_strtolower($title)) {
            $fields['description'] = self::buildDistinctDescription($title, $userMessage);
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolveRelativeDatesInMessage(array $fields, string $userMessage, Carbon $today): array
    {
        $msg = mb_strtolower($userMessage, 'UTF-8');

        if (preg_match('/\b(hari\s*ini|today|sekarang)\b/u', $userMessage)) {
            $d = $today->format('Y-m-d');
            $fields['start_date'] = $d;
            $fields['end_date'] = $d;

            return $fields;
        }

        if (preg_match('/\b(besok)\b/u', $msg)) {
            $d = $today->copy()->addDay()->format('Y-m-d');
            $fields['start_date'] = $d;
            $fields['end_date'] = $d;

            return $fields;
        }

        if (preg_match('/\b(kemarin)\b/u', $msg)) {
            $d = $today->copy()->subDay()->format('Y-m-d');
            $fields['start_date'] = $d;
            $fields['end_date'] = $d;

            return $fields;
        }

        if (preg_match('/\b(lusa)\b/u', $msg)) {
            $d = $today->copy()->addDays(2)->format('Y-m-d');
            $fields['start_date'] = $d;
            $fields['end_date'] = $d;

            return $fields;
        }

        return $fields;
    }

    private static function sanitizeDateString(?string $date, string $userMessage, Carbon $today): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        try {
            $c = Carbon::parse($date, 'Asia/Jakarta');
        } catch (\Throwable) {
            return $today->format('Y-m-d');
        }

        $year = (int) $c->format('Y');
        $currentYear = (int) $today->format('Y');

        $userMentionedYear = (bool) preg_match('/\b(19|20)\d{2}\b/', $userMessage);

        // Model sering mengeluarkan tahun lama (mis. 2023) padahal maksudnya hari ini / tahun berjalan
        if ($year < $currentYear && ! $userMentionedYear) {
            return $today->format('Y-m-d');
        }

        if ($year > $currentYear + 2 && ! $userMentionedYear) {
            return $today->format('Y-m-d');
        }

        return $c->format('Y-m-d');
    }

    private static function formatTitle(string $title): string
    {
        $t = preg_replace('/\s+/', ' ', trim($title)) ?? $title;

        return Str::title(Str::lower($t));
    }

    private static function buildDistinctDescription(string $title, string $userMessage): string
    {
        $notes = [];
        if (preg_match_all('/\(([^)]+)\)/', $userMessage, $m)) {
            foreach ($m[1] as $inner) {
                $inner = trim($inner);
                if ($inner !== '') {
                    $notes[] = $inner;
                }
            }
        }

        $lines = [
            'Ringkasan pekerjaan mengacu pada judul di atas.',
            'Lengkapi di sini: urutan langkah kerja, material/komponen, dependensi, dan pengecekan hasil.',
        ];

        if ($notes !== []) {
            $lines[] = 'Catatan / syarat khusus: '.implode('; ', $notes).'.';
        }

        return implode("\n\n", $lines);
    }
}
