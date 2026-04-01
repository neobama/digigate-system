<?php

namespace App\Http\Controllers;

use App\Filament\Resources\BudgetRequestResource;
use App\Filament\Resources\CashbonResource;
use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\IncomeResource;
use App\Filament\Resources\ReimbursementResource;
use App\Filament\Resources\TaskResource;
use App\Models\Employee;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonoAssistantController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $message = trim($request->string('message')->toString());
        $memoryKey = 'dono_memory_' . (auth()->id() ?? session()->getId());
        $history = session($memoryKey, []);

        $employeeNames = Employee::query()->pluck('name')->take(200)->values()->all();
        $assistantPayload = $this->parseIntent($message, $history, $employeeNames);

        $history[] = ['role' => 'user', 'text' => $message];
        $history[] = ['role' => 'assistant', 'text' => $assistantPayload['reply'] ?? ''];
        session([$memoryKey => array_slice($history, -12)]);

        return response()->json($assistantPayload);
    }

    private function parseIntent(string $message, array $history, array $employeeNames): array
    {
        $prompt = "Anda adalah asisten internal bernama Dono untuk ERP DigiGate.\n"
            . "Tugas Anda: pahami intent user dan keluarkan JSON valid saja.\n"
            . "Output JSON schema:\n"
            . "{\n"
            . "  \"reply\": \"balasan singkat bahasa indonesia\",\n"
            . "  \"intent\": \"create_task|create_cashbon|create_budget_request|create_reimbursement|create_expense|create_income|open_feature|unknown\",\n"
            . "  \"fields\": {\n"
            . "    \"title\": \"\",\n"
            . "    \"description\": \"\",\n"
            . "    \"start_date\": \"YYYY-MM-DD or null\",\n"
            . "    \"end_date\": \"YYYY-MM-DD or null\",\n"
            . "    \"employee_names\": [\"nama1\", \"nama2\"],\n"
            . "    \"amount\": number or null\n"
            . "  }\n"
            . "}\n"
            . "Nama karyawan valid (gunakan jika disebut): " . implode(', ', $employeeNames) . "\n"
            . "Riwayat chat singkat: " . json_encode($history) . "\n"
            . "User message: {$message}";

        $gemini = new GeminiService();
        $result = $this->callGemini($gemini, $prompt);

        if (!$result) {
            return [
                'reply' => 'Dono belum bisa memahami perintah itu. Coba lebih spesifik, misalnya: buat task besok jam 13 untuk Arda.',
                'intent' => 'unknown',
                'action' => null,
            ];
        }

        $intent = $result['intent'] ?? 'unknown';
        $fields = $result['fields'] ?? [];

        return [
            'reply' => $result['reply'] ?? 'Siap, saya bantu proseskan.',
            'intent' => $intent,
            'action' => $this->buildAction($intent, $fields),
        ];
    }

    private function buildAction(string $intent, array $fields): ?array
    {
        return match ($intent) {
            'create_task' => $this->taskAction($fields),
            'create_cashbon' => ['label' => 'Buka Form Cashbon', 'url' => CashbonResource::getUrl('create')],
            'create_budget_request' => ['label' => 'Buka Form Budget Request', 'url' => BudgetRequestResource::getUrl('create')],
            'create_reimbursement' => ['label' => 'Buka Form Reimbursement', 'url' => ReimbursementResource::getUrl('create')],
            'create_expense' => ['label' => 'Buka Form Pengeluaran', 'url' => ExpenseResource::getUrl('create')],
            'create_income' => ['label' => 'Buka Form Pemasukan', 'url' => IncomeResource::getUrl('create')],
            default => null,
        };
    }

    private function taskAction(array $fields): array
    {
        $names = collect($fields['employee_names'] ?? [])->filter()->values()->all();
        $employeeIds = Employee::query()->whereIn('name', $names)->pluck('id')->all();

        $prefill = array_filter([
            'title' => $fields['title'] ?? null,
            'description' => $fields['description'] ?? null,
            'start_date' => $fields['start_date'] ?? null,
            'end_date' => $fields['end_date'] ?? null,
            'employees' => $employeeIds,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);

        return [
            'label' => 'Buka Form Task (Prefill)',
            'url' => TaskResource::getUrl('create', ['prefill' => base64_encode(json_encode($prefill))]),
        ];
    }

    private function callGemini(GeminiService $gemini, string $prompt): ?array
    {
        $reflection = new \ReflectionClass($gemini);
        $apiKeyProp = $reflection->getProperty('apiKey');
        $apiKeyProp->setAccessible(true);
        $apiKey = $apiKeyProp->getValue($gemini);

        if (!$apiKey) {
            return null;
        }

        $response = \Illuminate\Support\Facades\Http::timeout((int) config('gemini.timeout', 30))
            ->post("https://generativelanguage.googleapis.com/v1beta/models/" . config('gemini.model', 'gemini-1.5-pro') . ":generateContent?key={$apiKey}", [
                'contents' => [[
                    'parts' => [['text' => $prompt]],
                ]],
            ]);

        if (!$response->successful()) {
            return null;
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (!$text) {
            return null;
        }

        $text = preg_replace('/```json\s*/', '', $text);
        $text = preg_replace('/```\s*/', '', $text);
        $decoded = json_decode(trim($text), true);

        return is_array($decoded) ? $decoded : null;
    }
}

