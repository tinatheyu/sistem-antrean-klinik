<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    /**
     * Apakah integrasi Telegram diaktifkan dan token tersedia.
     */
    public function isEnabled(): bool
    {
        return (bool) config('services.telegram.enabled', false)
            && ! empty(config('services.telegram.bot_token'));
    }

    /**
     * Username bot Telegram (tanpa tanda @).
     */
    public function botUsername(): ?string
    {
        $username = config('services.telegram.bot_username');

        return $username ? ltrim((string) $username, '@') : null;
    }

    /**
     * Bangun deep link untuk membuka bot dengan token penghubung.
     *
     * Contoh: https://t.me/KlinikQueueBot?start=<token>
     */
    public function buildStartLink(string $token): ?string
    {
        $username = $this->botUsername();

        if (! $username) {
            return null;
        }

        return 'https://t.me/'.$username.'?start='.urlencode($token);
    }

    /**
     * Kirim pesan Telegram melalui Bot API.
     *
     * @return array{success: bool, simulated: bool, message: string}
     */
    public function send(string $chatId, string $message): array
    {
        if (! $this->isEnabled()) {
            Log::info('[Telegram Simulated] Pesan tidak dikirim karena integrasi Telegram belum aktif.', [
                'chat_id_present' => $chatId !== '',
                'message_length' => mb_strlen($message),
            ]);

            return [
                'success' => true,
                'simulated' => true,
                'message' => 'Pesan Telegram berhasil disimulasikan (kredensial bot Telegram belum diatur pada .env).',
            ];
        }

        $botToken = config('services.telegram.bot_token');

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'simulated' => false,
                    'message' => 'Pesan Telegram berhasil dikirim ke bot.',
                ];
            }

            Log::warning("[Telegram Failed] Status: {$response->status()} | Body: {$response->body()}");

            return [
                'success' => false,
                'simulated' => false,
                'message' => "Telegram Bot API merespons status {$response->status()}: ".substr($response->body(), 0, 150),
            ];
        } catch (\Throwable $e) {
            Log::error('[Telegram Error] '.$e->getMessage());

            return [
                'success' => false,
                'simulated' => false,
                'message' => 'Koneksi ke Telegram Bot API gagal: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Daftarkan webhook Telegram (secret token dikirim via header standar Telegram).
     *
     * @return array{success: bool, message: string}
     */
    public function setWebhook(string $url, string $secret): array
    {
        return $this->callWebhookApi('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message'],
        ], 'Webhook berhasil didaftarkan.');
    }

    /**
     * Hapus webhook Telegram.
     *
     * @return array{success: bool, message: string}
     */
    public function deleteWebhook(): array
    {
        return $this->callWebhookApi('deleteWebhook', [], 'Webhook berhasil dihapus.');
    }

    /**
     * Ambil informasi webhook saat ini.
     *
     * @return array{success: bool, message: string}
     */
    public function getWebhookInfo(): array
    {
        if (! $this->isEnabled()) {
            return ['success' => false, 'message' => 'Integrasi Telegram belum aktif.'];
        }

        $botToken = config('services.telegram.bot_token');

        try {
            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$botToken}/getWebhookInfo");

            return [
                'success' => $response->successful(),
                'message' => (string) $response->body(),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Helper pemanggilan endpoint manajemen webhook.
     *
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string}
     */
    protected function callWebhookApi(string $method, array $payload, string $successMessage): array
    {
        if (! $this->isEnabled()) {
            return ['success' => false, 'message' => 'Integrasi Telegram belum aktif (TELEGRAM_ENABLED/TELEGRAM_BOT_TOKEN belum diatur).'];
        }

        $botToken = config('services.telegram.bot_token');

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$botToken}/{$method}", $payload);

            if ($response->successful() && ($response->json('ok') === true)) {
                return ['success' => true, 'message' => $successMessage];
            }

            Log::warning("[Telegram {$method} Failed] Status: {$response->status()} | Body: {$response->body()}");

            return [
                'success' => false,
                'message' => "Telegram API menolak permintaan: ".substr((string) $response->body(), 0, 200),
            ];
        } catch (\Throwable $e) {
            Log::error("[Telegram {$method} Error] ".$e->getMessage());

            return ['success' => false, 'message' => 'Koneksi ke Telegram Bot API gagal: '.$e->getMessage()];
        }
    }
}
