<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mengelola penghubungan akun pasien dengan akun Telegram.
 *
 * Mekanisme:
 *  - Pasien yang sudah login meminta token penghubung (unik, sementara, sekali pakai).
 *  - Pasien membuka deep link bot (?start=<token>) dan menekan Start.
 *  - Webhook Telegram memverifikasi token lalu menyimpan chat_id + username.
 *
 * Penghubungan TIDAK pernah dilakukan hanya berdasarkan nomor telepon atau
 * username; token adalah satu-satunya faktor verifikasi.
 */
class TelegramLinkService
{
    public function __construct(protected TelegramService $telegram) {}

    /**
     * Apakah fitur penghubungan Telegram dapat dipakai.
     */
    public function isAvailable(): bool
    {
        return $this->telegram->isEnabled() && $this->telegram->botUsername() !== null;
    }

    /**
     * Ambil token yang masih berlaku, atau buat baru bila belum ada / kedaluwarsa.
     * Mengembalikan null bila akun sudah terhubung atau fitur tidak tersedia.
     */
    public function ensureToken(User $user): ?string
    {
        if (! $this->isAvailable() || $user->hasTelegramLinked()) {
            return null;
        }

        if (
            empty($user->telegram_link_token)
            || $user->telegram_link_token_expires_at === null
            || $user->telegram_link_token_expires_at->isPast()
        ) {
            return $this->issueToken($user);
        }

        return $user->telegram_link_token;
    }

    /**
     * Terbitkan token penghubung baru (membatalkan token sebelumnya).
     */
    public function issueToken(User $user): string
    {
        $token = Str::random(48);
        $ttl = (int) config('services.telegram.link_token_ttl', 15);

        $user->forceFill([
            'telegram_link_token' => $token,
            'telegram_link_token_expires_at' => now()->addMinutes($ttl > 0 ? $ttl : 15),
        ])->save();

        return $token;
    }

    /**
     * Deep link untuk membuka bot Telegram beserta token penghubung.
     */
    public function startLinkUrl(User $user): ?string
    {
        $token = $this->ensureToken($user);

        if ($token === null) {
            return null;
        }

        return $this->telegram->buildStartLink($token);
    }

    /**
     * Verifikasi token dan hubungkan akun dalam satu transaksi (sekali pakai).
     *
     * @return array{success: bool, reason: string, user: User|null}
     */
    public function consume(string $token, string $chatId, ?string $username): array
    {
        $token = trim($token);

        if ($token === '') {
            return ['success' => false, 'reason' => 'invalid', 'user' => null];
        }

        return DB::transaction(function () use ($token, $chatId, $username) {
            $user = User::where('telegram_link_token', $token)->lockForUpdate()->first();

            if (! $user) {
                return ['success' => false, 'reason' => 'invalid', 'user' => null];
            }

            if ($user->telegram_link_token_expires_at === null || $user->telegram_link_token_expires_at->isPast()) {
                return ['success' => false, 'reason' => 'expired', 'user' => null];
            }

            $this->link($user, $chatId, $username);

            return ['success' => true, 'reason' => 'linked', 'user' => $user];
        });
    }

    /**
     * Simpan chat_id dan username Telegram, lalu habiskan token penghubung.
     */
    public function link(User $user, string $chatId, ?string $username): void
    {
        $user->forceFill([
            'telegram_chat_id' => $chatId,
            'telegram_username' => self::normalizeUsername($username),
            'telegram_link_token' => null,
            'telegram_link_token_expires_at' => null,
            'telegram_linked_at' => now(),
        ])->save();
    }

    /**
     * Putuskan koneksi Telegram dari akun pasien.
     */
    public function unlink(User $user): void
    {
        $user->forceFill([
            'telegram_chat_id' => null,
            'telegram_username' => null,
            'telegram_link_token' => null,
            'telegram_link_token_expires_at' => null,
            'telegram_linked_at' => null,
        ])->save();
    }

    /**
     * Normalisasi username Telegram (menghapus tanda @ dan spasi).
     */
    public static function normalizeUsername(?string $username): ?string
    {
        if ($username === null) {
            return null;
        }

        $clean = ltrim(trim($username), '@');

        return $clean === '' ? null : $clean;
    }
}
