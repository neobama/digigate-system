<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected ?string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('gemini.api_key') ?: null;
        $model = config('gemini.model', 'gemini-1.5-pro');
        $this->baseUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
    }
    
    /**
     * Check if API key is configured
     */
    protected function hasApiKey(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Parse reimbursement invoice/bon untuk extract informasi
     * 
     * @param string $imageBase64 Base64 encoded image
     * @return array|null
     */
    public function parseReimbursementInvoice(string $imageBase64): ?array
    {
        if (!$this->hasApiKey()) {
            Log::error('Gemini API Key not configured');
            return null;
        }
        
        $prompt = "Analyze this invoice/receipt image and extract the following information in JSON format:
{
  \"purpose\": \"Short description of what the expense is for (e.g., 'Transport ke client', 'Makan siang meeting')\",
  \"expense_date\": \"Date in YYYY-MM-DD format (extract from invoice date)\",
  \"amount\": \"Total amount as number (without currency symbol)\",
  \"description\": \"Additional details or notes from the invoice\"
}

Only return valid JSON, no other text. If any field cannot be determined, use null.";

        try {
            $response = Http::timeout(30)->post($this->baseUrl . '?key=' . $this->apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt
                            ],
                            [
                                'inline_data' => [
                                    'mime_type' => $this->detectMimeType($imageBase64),
                                    'data' => $imageBase64
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $content = $response->json();
                $text = $content['candidates'][0]['content']['parts'][0]['text'] ?? null;
                
                if ($text) {
                    // Extract JSON from response (might have markdown code blocks)
                    $text = preg_replace('/```json\s*/', '', $text);
                    $text = preg_replace('/```\s*/', '', $text);
                    $text = trim($text);
                    
                    $parsed = json_decode($text, true);
                    
                    if ($parsed && is_array($parsed)) {
                        return [
                            'purpose' => $parsed['purpose'] ?? null,
                            'expense_date' => $parsed['expense_date'] ?? null,
                            'amount' => $parsed['amount'] ? (float) $parsed['amount'] : null,
                            'description' => $parsed['description'] ?? null,
                        ];
                    }
                }
            }

            Log::error('Gemini API Error', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Gemini Service Exception', [
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Parse component invoice untuk extract multiple items
     * 
     * @param string $imageBase64 Base64 encoded image
     * @return array|null Array of items
     */
    public function parseComponentInvoice(string $imageBase64): ?array
    {
        if (!$this->hasApiKey()) {
            Log::error('Gemini API Key not configured');
            return null;
        }
        
        $prompt = "Analyze this invoice/receipt image and extract ALL items/products listed. Return as JSON array:
[
  {
    \"name\": \"Product/Item name (e.g., 'Processor i7 11700K', 'RAM DDR4', 'SSD')\",
    \"supplier\": \"Supplier/vendor name from invoice\",
    \"purchase_date\": \"Date in YYYY-MM-DD format (extract from invoice date)\",
    \"quantity\": \"Quantity as number (if available)\"
  },
  ...
]

Only return valid JSON array, no other text. If invoice date is not found, use today's date. Extract all items from the invoice.";

        try {
            $response = Http::timeout(30)->post($this->baseUrl . '?key=' . $this->apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt
                            ],
                            [
                                'inline_data' => [
                                    'mime_type' => $this->detectMimeType($imageBase64),
                                    'data' => $imageBase64
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $content = $response->json();
                $text = $content['candidates'][0]['content']['parts'][0]['text'] ?? null;
                
                if ($text) {
                    // Extract JSON from response (might have markdown code blocks)
                    $text = preg_replace('/```json\s*/', '', $text);
                    $text = preg_replace('/```\s*/', '', $text);
                    $text = trim($text);
                    
                    $parsed = json_decode($text, true);
                    
                    if ($parsed && is_array($parsed)) {
                        // Normalize items
                        $items = [];
                        foreach ($parsed as $item) {
                            if (isset($item['name'])) {
                                $items[] = [
                                    'name' => $item['name'] ?? null,
                                    'supplier' => $item['supplier'] ?? null,
                                    'purchase_date' => $item['purchase_date'] ?? now()->format('Y-m-d'),
                                    'quantity' => isset($item['quantity']) ? (int) $item['quantity'] : 1,
                                ];
                            }
                        }
                        
                        return $items;
                    }
                }
            }

            Log::error('Gemini API Error', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Gemini Service Exception', [
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Detect MIME type from base64 image
     * 
     * @param string $base64
     * @return string
     */
    protected function detectMimeType(string $base64): string
    {
        // Remove data URI prefix if present
        $base64 = preg_replace('/^data:image\/[^;]+;base64,/', '', $base64);
        
        // Decode to get image info
        $imageData = base64_decode($base64, true);
        
        if ($imageData === false) {
            return 'image/jpeg'; // Default
        }
        
        // Use finfo to detect MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $imageData);
        finfo_close($finfo);
        
        // Validate and return
        if (in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
            return $mimeType;
        }
        
        return 'image/jpeg'; // Default fallback
    }
}

