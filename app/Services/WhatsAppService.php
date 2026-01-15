<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $apiUrl;
    private string $apiKey;
    private string $session;

    public function __construct()
    {
        $this->apiUrl = config('services.whatsapp.api_url', 'http://10.11.10.55:3000/api/sendText');
        $this->apiKey = config('services.whatsapp.api_key', '1d7a97d985a245f699b0eb42567670aa');
        $this->session = config('services.whatsapp.session', 'default');
    }

    /**
     * Format nomor telepon ke format WhatsApp (62xxxxxxxxxx@c.us)
     */
    public function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove all non-numeric characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // If starts with 0, replace with 62
        if (str_starts_with($phoneNumber, '0')) {
            $phoneNumber = '62' . substr($phoneNumber, 1);
        }
        
        // If doesn't start with 62, add it
        if (!str_starts_with($phoneNumber, '62')) {
            $phoneNumber = '62' . $phoneNumber;
        }
        
        return $phoneNumber . '@c.us';
    }

    /**
     * Send WhatsApp message
     */
    public function sendMessage(string $phoneNumber, string $message): bool
    {
        try {
            $chatId = $this->formatPhoneNumber($phoneNumber);
            
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Api-Key' => $this->apiKey,
            ])->post($this->apiUrl, [
                'session' => $this->session,
                'chatId' => $chatId,
                'text' => $message,
            ]);

            if ($response->successful()) {
                Log::info('WhatsApp message sent successfully', [
                    'phone' => $phoneNumber,
                    'chatId' => $chatId,
                ]);
                return true;
            } else {
                Log::error('Failed to send WhatsApp message', [
                    'phone' => $phoneNumber,
                    'chatId' => $chatId,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception while sending WhatsApp message', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send message to admin
     */
    public function sendToAdmin(string $message): bool
    {
        $adminPhone = config('services.whatsapp.admin_phone', '081511207866');
        return $this->sendMessage($adminPhone, $message);
    }

    /**
     * Send message to multiple recipients
     */
    public function sendBulk(array $phoneNumbers, string $message): array
    {
        $results = [];
        foreach ($phoneNumbers as $phoneNumber) {
            if (!empty($phoneNumber)) {
                $results[$phoneNumber] = $this->sendMessage($phoneNumber, $message);
            }
        }
        return $results;
    }
}
