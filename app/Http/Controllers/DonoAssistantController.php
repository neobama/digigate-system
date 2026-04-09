<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\GeminiService;
use App\Support\DonoApplicationCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonoAssistantController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'panel' => ['nullable', 'in:admin,employee'],
        ]);

        $message = trim($request->string('message')->toString());
        $panel = DonoApplicationCatalog::resolvePanel($request);

        $memoryKey = 'dono_memory_'.(auth()->id() ?? session()->getId());
        $history = session($memoryKey, []);

        $employeeNames = Employee::query()->pluck('name')->take(200)->values()->all();
        $assistantPayload = $this->parseIntent($message, $history, $employeeNames, $panel);

        $history[] = ['role' => 'user', 'text' => $message];
        $history[] = ['role' => 'assistant', 'text' => $assistantPayload['reply'] ?? ''];
        session([$memoryKey => array_slice($history, -12)]);

        return response()->json($assistantPayload);
    }

    /**
     * @param  array<int, array{role: string, text: string}>  $history
     * @param  array<int, string>  $employeeNames
     * @return array{reply: string, intent: string, action: ?array{label: string, url: string}}
     */
    private function parseIntent(string $message, array $history, array $employeeNames, string $panel): array
    {
        $prompt = "Anda adalah asisten internal bernama Dono untuk ERP DigiGate.\n"
            ."Pengguna saat ini di panel: **{$panel}** (employee = karyawan /employee, admin = panel utama).\n\n"
            .DonoApplicationCatalog::knowledgeBaseForPrompt()."\n\n"
            .DonoApplicationCatalog::jsonSchemaInstruction()."\n\n"
            .'Nama karyawan valid (untuk task / assign): '.implode(', ', $employeeNames)."\n"
            .'Riwayat chat singkat: '.json_encode($history, JSON_UNESCAPED_UNICODE)."\n"
            .'User message: '.$message;

        $gemini = new GeminiService;
        $result = $gemini->generateJsonFromPrompt($prompt);

        if (! $result) {
            return [
                'reply' => 'Dono belum bisa memahami perintah itu (layanan AI tidak tersedia atau sibuk). Coba lagi, atau gunakan menu di sidebar. Contoh: buat task besok untuk Budi, atau tanya: apa itu cashbon?',
                'intent' => 'unknown',
                'action' => null,
            ];
        }

        $intent = $result['intent'] ?? 'unknown';
        $fields = is_array($result['fields'] ?? null) ? $result['fields'] : [];

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
