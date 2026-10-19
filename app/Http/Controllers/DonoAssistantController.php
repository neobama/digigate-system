<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDonoChatJob;
use App\Support\DonoApplicationCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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

        $jobId = (string) Str::uuid();

        Cache::put(ProcessDonoChatJob::cacheKeyFor($jobId), [
            'status' => 'pending',
            'payload' => null,
        ], now()->addMinutes(10));

        ProcessDonoChatJob::dispatch(
            $jobId,
            $message,
            $panel,
            $history,
            auth()->id() ? (string) auth()->id() : null,
        );

        // Simpan user message dulu; balasan assistant dilengkapi saat status=done
        session([
            $memoryKey.'_pending_job' => $jobId,
            $memoryKey.'_pending_message' => $message,
        ]);

        return response()->json([
            'job_id' => $jobId,
            'status' => 'pending',
        ]);
    }

    public function status(Request $request, string $jobId): JsonResponse
    {
        $cached = Cache::get(ProcessDonoChatJob::cacheKeyFor($jobId));

        if (! is_array($cached)) {
            return response()->json([
                'status' => 'missing',
                'reply' => 'Hasil chat tidak ditemukan atau sudah kedaluwarsa. Kirim ulang pesan.',
                'intent' => 'unknown',
                'action' => null,
            ], 404);
        }

        $status = $cached['status'] ?? 'pending';

        if ($status === 'pending') {
            return response()->json([
                'status' => 'pending',
            ]);
        }

        $payload = is_array($cached['payload'] ?? null) ? $cached['payload'] : [
            'reply' => 'Siap.',
            'intent' => 'unknown',
            'action' => null,
        ];

        $memoryKey = 'dono_memory_'.(auth()->id() ?? session()->getId());
        if (session($memoryKey.'_pending_job') === $jobId) {
            $history = session($memoryKey, []);
            $pendingMessage = session($memoryKey.'_pending_message');
            if (is_string($pendingMessage) && $pendingMessage !== '') {
                $history[] = ['role' => 'user', 'text' => $pendingMessage];
            }
            $history[] = ['role' => 'assistant', 'text' => $payload['reply'] ?? ''];
            session([
                $memoryKey => array_slice($history, -12),
                $memoryKey.'_pending_job' => null,
                $memoryKey.'_pending_message' => null,
            ]);
        }

        return response()->json([
            'status' => $status === 'failed' ? 'failed' : 'done',
            'reply' => $payload['reply'] ?? 'Siap.',
            'intent' => $payload['intent'] ?? 'unknown',
            'action' => $payload['action'] ?? null,
        ]);
    }
}
