<?php

namespace App\Services;

use App\Support\DonoApplicationCatalog;
use App\Support\DonoFallbackIntentParser;
use App\Support\DonoPromptCache;
use App\Support\DonoTaskFieldNormalizer;

class DonoChatService
{
    /**
     * @param  array<int, array{role: string, text: string}>  $history
     * @return array{reply: string, intent: string, action: ?array{label: string, url: string}}
     */
    public function parseIntent(string $message, array $history, string $panel): array
    {
        $employeeNames = DonoPromptCache::employeeNames();

        $prompt = "Anda adalah asisten internal bernama Dono untuk ERP DigiGate.\n"
            ."Pengguna saat ini di panel: **{$panel}** (employee = karyawan /employee, admin = panel utama).\n\n"
            .DonoPromptCache::knowledgeBase()."\n\n"
            .DonoPromptCache::taskCalendarContext()."\n"
            .DonoPromptCache::taskFieldRules()."\n\n"
            .DonoPromptCache::jsonSchema()."\n\n"
            .'Nama karyawan valid (untuk task / assign): '.implode(', ', $employeeNames)."\n"
            .'Riwayat chat singkat: '.json_encode($history, JSON_UNESCAPED_UNICODE)."\n"
            .'User message: '.$message;

        $result = app(GeminiService::class)->generateJsonFromPrompt($prompt);

        if (! $result) {
            $result = DonoFallbackIntentParser::parse($message, $panel);
        }

        if (! $result) {
            return [
                'reply' => 'Dono tidak bisa memproses perintah ini (layanan AI belum dikonfigurasi atau sibuk). Tambahkan GEMINI_API_KEY di .env dan pakai model yang valid (mis. gemini-2.0-flash). Atau buka **Financial → Pengeluaran** di sidebar untuk input manual.',
                'intent' => 'unknown',
                'action' => null,
            ];
        }

        $intent = $result['intent'] ?? 'unknown';
        $fields = is_array($result['fields'] ?? null) ? $result['fields'] : [];

        if ($intent === 'create_task') {
            $fields = DonoTaskFieldNormalizer::normalize($fields, $message);
        }

        $reply = $result['reply'] ?? 'Siap, saya bantu.';

        if (in_array($intent, ['app_help', 'unknown'], true)) {
            return [
                'reply' => $reply,
                'intent' => $intent,
                'action' => null,
            ];
        }

        return [
            'reply' => $reply,
            'intent' => $intent,
            'action' => DonoApplicationCatalog::buildAction($intent, $fields, $panel),
        ];
    }
}
