<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Send a WhatsApp message.
     *
     * @return array{success: bool, simulated: bool, message: string}
     */
    public function send(string $phone, string $message): array
    {
        $enabled = (bool) config('services.whatsapp.enabled', false);
        $apiUrl = config('services.whatsapp.api_url', 'https://api.fonnte.com/send');
        $token = config('services.whatsapp.api_token');

        // Normalize phone number to standard Indonesian format (e.g., 0812 -> 62812)
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($cleanPhone, '0')) {
            $cleanPhone = '62'.substr($cleanPhone, 1);
        }

        // If service is disabled or credentials are empty, operate in safe simulation mode
        if (! $enabled || empty($token)) {
            Log::info("[WhatsApp Simulated] To: {$cleanPhone} | Message: {$message}");

            return [
                'success' => true,
                'simulated' => true,
                'message' => 'Pesan WhatsApp berhasil disimulasikan (Kredensial WHATSAPP_API_TOKEN belum diatur pada .env).',
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->timeout(10)->post($apiUrl, [
                'target' => $cleanPhone,
                'message' => $message,
                'countryCode' => '62',
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'simulated' => false,
                    'message' => 'Pesan WhatsApp berhasil terkirim melalui gateway.',
                ];
            }

            Log::warning("[WhatsApp Failed] Status: {$response->status()} | Body: {$response->body()}");

            return [
                'success' => false,
                'simulated' => false,
                'message' => "Gateway WhatsApp merespons status {$response->status()}: ".substr($response->body(), 0, 150),
            ];
        } catch (\Throwable $e) {
            Log::error('[WhatsApp Error] '.$e->getMessage());

            return [
                'success' => false,
                'simulated' => false,
                'message' => 'Koneksi ke gateway WhatsApp gagal: '.$e->getMessage(),
            ];
        }
    }
}
