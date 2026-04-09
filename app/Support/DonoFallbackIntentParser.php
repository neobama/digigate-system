<?php

namespace App\Support;

/**
 * Parser sederhana tanpa Gemini untuk perintah umum (pengeluaran, pemasukan, buka menu).
 */
final class DonoFallbackIntentParser
{
    /**
     * @return array{intent: string, fields: array<string, mixed>, reply: string}|null
     */
    public static function parse(string $message, string $panel): ?array
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return null;
        }

        $lower = mb_strtolower($trimmed, 'UTF-8');

        if ($panel === 'admin') {
            $expense = self::tryParseExpense($trimmed, $lower);
            if ($expense !== null) {
                return $expense;
            }

            $income = self::tryParseIncome($trimmed, $lower);
            if ($income !== null) {
                return $income;
            }

            $openExpenses = self::tryOpenExpensesList($lower);
            if ($openExpenses !== null) {
                return $openExpenses;
            }
        }

        return null;
    }

    /**
     * @return array{intent: string, fields: array<string, mixed>, reply: string}|null
     */
    private static function tryParseExpense(string $original, string $lower): ?array
    {
        if (! self::looksLikeExpenseIntent($lower)) {
            return null;
        }

        $amount = self::extractAmount($original);
        $description = self::extractTrailingDescription($original);

        if ($description === '') {
            $description = 'Pengeluaran';
        }

        $fields = [
            'description' => $description,
            'amount' => $amount,
        ];

        if ($amount !== null && $amount > 0) {
            $fmt = number_format($amount, 0, ',', '.');

            return [
                'intent' => 'create_expense',
                'fields' => $fields,
                'reply' => "Membuka form Pengeluaran dengan nominal Rp {$fmt} — \"{$description}\". Lengkapi sumber dana dan bukti jika perlu.",
            ];
        }

        return [
            'intent' => 'create_expense',
            'fields' => ['description' => $description],
            'reply' => 'Membuka form Pengeluaran. Isi nominal dan detail di form.',
        ];
    }

    /**
     * @return array{intent: string, fields: array<string, mixed>, reply: string}|null
     */
    private static function tryParseIncome(string $original, string $lower): ?array
    {
        if (! preg_match('/\b(pemasukan|income|terima\s*uang|catat\s*pemasukan)\b/u', $lower)) {
            return null;
        }

        $amount = self::extractAmount($original);
        $description = self::extractTrailingDescription($original);
        if ($description === '') {
            $description = 'Pemasukan';
        }

        $fields = ['description' => $description, 'amount' => $amount];

        if ($amount !== null && $amount > 0) {
            $fmt = number_format($amount, 0, ',', '.');

            return [
                'intent' => 'create_income',
                'fields' => $fields,
                'reply' => "Membuka form Pemasukan dengan nominal Rp {$fmt} — \"{$description}\".",
            ];
        }

        return [
            'intent' => 'create_income',
            'fields' => ['description' => $description],
            'reply' => 'Membuka form Pemasukan. Isi nominal dan detail di form.',
        ];
    }

    /**
     * @return array{intent: string, fields: array<string, mixed>, reply: string}|null
     */
    private static function tryOpenExpensesList(string $lower): ?array
    {
        if (! preg_match('/^(buka|ke|tampilkan|menu)\s*(halaman\s*)?(daftar\s*)?(pengeluaran|expense)/u', $lower)
            && ! preg_match('/\b(daftar|list|lihat)\s+(pengeluaran|expense)\b/u', $lower)) {
            return null;
        }

        return [
            'intent' => 'open_feature',
            'fields' => ['feature_key' => 'expenses'],
            'reply' => 'Membuka halaman daftar Pengeluaran.',
        ];
    }

    private static function looksLikeExpenseIntent(string $lower): bool
    {
        return (bool) preg_match(
            '/\b(pengeluaran|expense|biaya\s*operasional|lapor\s*pengeluaran|catat\s*pengeluaran|input\s*pengeluaran|tambah\s*pengeluaran|belanja|pembelian)\b/u',
            $lower
        );
    }

    private static function extractTrailingDescription(string $original): string
    {
        if (preg_match('/(?:buat|untuk|beli(?:kan)?)\s+(.+)/iu', $original, $m)) {
            return trim($m[1], " \t\n\r\0\x0B.,");
        }

        $stripped = preg_replace('/\d[\d.\s,]*/u', ' ', $original) ?? '';
        $stripped = preg_replace('/\b(saya|aku|tolong|mau|ingin|lapor|catat|input|tambah|admin|buat|pengeluaran|expense|biaya|rp\.?|idr)\b/iu', ' ', $stripped);
        $stripped = trim(preg_replace('/\s+/u', ' ', $stripped) ?? '');

        return $stripped;
    }

    private static function extractAmount(string $message): ?float
    {
        $normalized = preg_replace('/\brp\.?\s*/iu', '', $message);
        $candidates = [];

        // Format Indonesia: 1.500.000 atau 50.000
        if (preg_match_all('/\d{1,3}(?:\.\d{3})+(?:,\d+)?/u', (string) $normalized, $m)) {
            foreach ($m[0] as $raw) {
                $n = self::normalizeIndonesianNumber($raw);
                if ($n !== null && $n >= 100) {
                    $candidates[] = $n;
                }
            }
        }

        // Sisanya: angka utuh tanpa titik ribuan (mis. 360000) — jangan pecah jadi 360 + 000
        $stripped = preg_replace('/\d{1,3}(?:\.\d{3})+(?:,\d+)?/u', ' ', (string) $normalized);
        if (preg_match_all('/\d+/u', $stripped, $m)) {
            foreach ($m[0] as $raw) {
                $n = (float) $raw;
                if ($n >= 100) {
                    $candidates[] = $n;
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        return max($candidates);
    }

    private static function normalizeIndonesianNumber(string $raw): ?float
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $raw)) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);

            return (float) $raw;
        }

        if (preg_match('/^\d+$/', $raw)) {
            return (float) $raw;
        }

        return null;
    }
}
