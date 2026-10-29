<?php

namespace App\Http\Controllers;

use App\Services\TelegramLinkService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Menerima update dari Telegram Bot API (metode integrasi: webhook).
 *
 * Keamanan:
 *  - Webhook dilindungi secret token yang dikirim Telegram melalui header
 *    X-Telegram-Bot-Api-Secret-Token (dibandingkan dengan hash_equals).
 *  - Secret disimpan pada .env (TELEGRAM_WEBHOOK_SECRET), bukan di source code.
 */
class TelegramWebhookController extends Controller
{
    public function __construct(
        protected TelegramLinkService $linker,
        protected TelegramService $telegram
    ) {}

    /**
     * Tangani update webhook Telegram.
     */
    public function handle(Request $request): Response
    {
        if (! $this->hasValidSecret($request)) {
            Log::warning('[Telegram Webhook] Permintaan ditolak: secret token tidak valid.');

            abort(403, 'Invalid webhook secret.');
        }

        $chatId = $request->input('message.chat.id');
        $text = trim((string) $request->input('message.text', ''));
        $username = $request->input('message.from.username');

        if ($chatId === null) {
            return response()->noContent();
        }

        $chatId = (string) $chatId;

        if (preg_match('/^\/start(?:\s+(.+))?$/s', $text, $matches) === 1) {
            $this->handleStart($chatId, $matches[1] ?? '', is_string($username) ? $username : null);
        }

        return response()->noContent();
    }

    /**
     * Tangani perintah /start dengan token penghubung.
     */
    protected function handleStart(string $chatId, string $token, ?string $username): void
    {
        $token = trim($token);

        if ($token === '') {
            $this->telegram->send(
                $chatId,
                "Halo! Untuk menghubungkan akun klinik Anda, silakan masuk ke sistem antrean, buka halaman Profil, lalu tekan tombol \"Hubungkan Telegram\"."
            );

            return;
        }

        $result = $this->linker->consume($token, $chatId, $username);

        if ($result['success']) {
            $name = $result['user']?->name ?? 'Pasien';

            $this->telegram->send(
                $chatId,
                "Terima kasih, {$name}!\n"
                ."Akun Telegram Anda berhasil dihubungkan dengan sistem antrean klinik.\n"
                .'Notifikasi konfirmasi pendaftaran dan pengingat antrean akan dikirim ke chat ini.'
            );

            return;
        }

        $message = $result['reason'] === 'expired'
            ? "Tautan penghubung sudah kedaluwarsa. Silakan buka kembali halaman Profil pada sistem antrean untuk memperoleh tautan baru."
            : "Tautan penghubung tidak valid. Silakan buka halaman Profil pada sistem antrean untuk memperoleh tautan baru.";

        $this->telegram->send($chatId, $message);
    }

    /**
     * Verifikasi secret token webhook.
     */
    protected function hasValidSecret(Request $request): bool
    {
        $secret = (string) config('services.telegram.webhook_secret', '');

        if ($secret === '') {
            return false;
        }

        $provided = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        return hash_equals($secret, $provided);
    }
}
