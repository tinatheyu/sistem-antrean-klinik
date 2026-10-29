<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Queue;
use App\Models\QueueReminder;
use Illuminate\Support\Facades\Log;

class QueueReminderService
{
    /** Jenis notifikasi konfirmasi pendaftaran (bukan reminder estimasi). */
    public const TYPE_REGISTRATION_CONFIRMATION = 'registration_confirmation';

    /** Jenis notifikasi ketika estimasi waktu tunggu mencapai nol menit. */
    public const TYPE_ZERO_MIN = '0_min';

    public function __construct(
        protected WhatsAppService $whatsapp,
        protected TelegramService $telegram
    ) {}

    /**
     * Alias kompatibilitas untuk scheduler/command lama.
     *
     * @return array{processed: int, sent: int, skipped: int, logs: array<string>}
     */
    public function sendDueReminders(): array
    {
        return $this->processReminders();
    }

    /**
     * Proses seluruh notifikasi antrean:
     *   1. Konfirmasi pendaftaran yang sudah jatuh tempo.
     *   2. Reminder estimasi waktu tunggu sesuai ambang di pengaturan.
     *
     * @return array{processed: int, sent: int, skipped: int, logs: array<string>}
     */
    public function processReminders(): array
    {
        $confirmation = $this->processRegistrationConfirmations();

        $leadTimes = $this->resolveLeadTimes();

        $waitingQueues = Queue::with(['doctor', 'patient', 'schedule'])
            ->whereDate('queue_date', today())
            ->where('status', 'menunggu')
            ->orderedForCalling()
            ->get();

        $processed = 0;
        $sentCount = $confirmation['sent'];
        $skippedCount = 0;
        $logs = $confirmation['logs'];

        foreach ($waitingQueues as $queue) {
            $processed++;

            // Cek ulang status: jangan kirim untuk antrean yang sudah tidak menunggu.
            if ($queue->status !== 'menunggu') {
                $skippedCount++;

                continue;
            }

            $channels = $this->resolveChannels($queue);

            if (empty($channels)) {
                // Tidak ada kanal terverifikasi (chat_id Telegram valid / nomor telepon).
                $skippedCount++;

                continue;
            }

            $estimatedMinutes = $queue->getEstimatedWaitTimeMinutes();

            $sentForQueue = false;

            foreach ($channels as $channel => $recipient) {
                $alreadySent = QueueReminder::where('queue_id', $queue->id)
                    ->where('channel', $channel)
                    ->pluck('reminder_type')
                    ->all();

                $dueType = $this->resolveDueType($estimatedMinutes, $leadTimes, $alreadySent);

                if ($dueType === null) {
                    continue;
                }

                $message = $this->buildEstimateMessage($queue, $dueType);

                $sent = $this->dispatch($queue, $channel, $recipient, $dueType, $message, $estimatedMinutes);

                if ($sent) {
                    $sentForQueue = true;
                    $sentCount++;
                    $logs[] = "Reminder {$dueType} terkirim via {$channel} untuk antrean {$queue->queue_number} ({$recipient}).";
                } else {
                    $logs[] = "Reminder {$dueType} gagal/tidak terkirim via {$channel} untuk antrean {$queue->queue_number}. Lihat riwayat QueueReminder untuk detail.";
                }
            }

            if (! $sentForQueue) {
                $skippedCount++;
            }
        }

        return [
            'processed' => $processed,
            'sent' => $sentCount,
            'skipped' => $skippedCount,
            'logs' => $logs,
        ];
    }

    // ---------------------------------------------------------------------
    // Konfirmasi pendaftaran
    // ---------------------------------------------------------------------

    /**
     * Jadwalkan notifikasi konfirmasi pendaftaran untuk antrean baru.
     *
     * Konfirmasi dikirim oleh scheduler (queue:check-reminders) pada pemeriksaan
     * berikutnya. Pendaftaran tetap berhasil meskipun Telegram belum terhubung;
     * dalam kasus itu hanya dicatat pada log bahwa kanal notifikasi belum aktif.
     */
    public function scheduleRegistrationConfirmation(Queue $queue): void
    {
        $queue->loadMissing('patient');

        $channels = $this->resolveChannels($queue);

        if (empty($channels)) {
            Log::info('[Reminder] Konfirmasi pendaftaran tidak dikirim: belum ada kanal notifikasi terverifikasi.', [
                'queue_id' => $queue->id,
            ]);

            return;
        }

        foreach ($channels as $channel => $recipient) {
            QueueReminder::firstOrCreate(
                [
                    'queue_id' => $queue->id,
                    'channel' => $channel,
                    'reminder_type' => self::TYPE_REGISTRATION_CONFIRMATION,
                ],
                [
                    'recipient' => $recipient,
                    'estimated_remaining_minutes' => 0,
                    'message_body' => '',
                    'status' => 'pending',
                    'scheduled_at' => now(),
                ]
            );
        }
    }

    /**
     * Kirim konfirmasi pendaftaran yang sudah jatuh tempo (dipanggil oleh scheduler).
     *
     * @return array{sent: int, logs: array<string>}
     */
    public function processRegistrationConfirmations(): array
    {
        $reminders = QueueReminder::with(['queue.patient'])
            ->where('reminder_type', self::TYPE_REGISTRATION_CONFIRMATION)
            ->where('status', 'pending')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        $sentCount = 0;
        $logs = [];

        foreach ($reminders as $reminder) {
            $queue = $reminder->queue;

            if (! $queue) {
                $reminder->update([
                    'status' => 'cancelled',
                    'error_message' => 'Antrean tidak ditemukan.',
                ]);

                continue;
            }

            // Batalkan konfirmasi jika antrean sudah tidak relevan.
            if (! in_array($queue->status, ['menunggu', 'dipanggil', 'sedang_diperiksa'], true)) {
                $reminder->update([
                    'status' => 'cancelled',
                    'error_message' => "Antrean berstatus {$queue->status}; konfirmasi dibatalkan.",
                ]);

                $logs[] = "Konfirmasi pendaftaran antrean {$queue->queue_number} dibatalkan (status {$queue->status}).";

                continue;
            }

            $message = $this->formatRegistrationConfirmation($queue);

            $result = $reminder->channel === 'telegram'
                ? $this->telegram->send((string) $reminder->recipient, $message)
                : $this->whatsapp->send((string) $reminder->recipient, $message);

            $reminder->update([
                'message_body' => $message,
                'status' => ($result['simulated'] ?? false) ? 'simulated' : (($result['success'] ?? false) ? 'sent' : 'failed'),
                'sent_at' => ($result['success'] ?? false) ? now() : null,
                'error_message' => ($result['success'] ?? false) ? null : ($result['message'] ?? 'Pengiriman gagal.'),
            ]);

            if ($result['success'] ?? false) {
                $sentCount++;
                $logs[] = "Konfirmasi pendaftaran terkirim via {$reminder->channel} untuk antrean {$queue->queue_number}.";
            } else {
                $logs[] = "Konfirmasi pendaftaran gagal via {$reminder->channel} untuk antrean {$queue->queue_number}.";
            }
        }

        return ['sent' => $sentCount, 'logs' => $logs];
    }

    /**
     * Format pesan konfirmasi pendaftaran.
     */
    public function formatRegistrationConfirmation(Queue $queue): string
    {
        $name = $this->sanitizeForMarkdown($queue->patient_name ?? $queue->patient?->name ?? 'Pasien');

        $estimated = $queue->getEstimatedWaitTimeMinutes();
        $estimateLine = $estimated > 0
            ? "Estimasi waktu tunggu: sekitar {$estimated} menit (perkiraan sistem)."
            : 'Estimasi waktu tunggu: sesuai hasil estimasi sistem saat ini.';

        return "*PENDAFTARAN ANTREAN BERHASIL*\n"
            ."Halo, {$name}!\n"
            ."Pendaftaran antrean klinik kamu berhasil.\n\n"
            ."Nomor antrean: {$queue->queue_number}\n"
            ."Status: {$queue->status_label}\n"
            ."{$estimateLine}\n\n"
            .'Silakan pantau perkembangan antrean kamu melalui sistem. Estimasi waktu tunggu dapat berubah sesuai kondisi pelayanan klinik.';
    }

    // ---------------------------------------------------------------------
    // Reminder estimasi waktu tunggu
    // ---------------------------------------------------------------------

    /**
     * Bangun pesan reminder berdasarkan jenis ambang estimasi.
     */
    public function buildEstimateMessage(Queue $queue, string $type): string
    {
        if ($type === self::TYPE_ZERO_MIN) {
            return "*INFORMASI ANTREAN*\n"
                ."Nomor antrean: {$queue->queue_number}\n"
                .'Perkiraan waktu tunggu telah mencapai nol menit. Silakan bersiap dan ikuti arahan petugas klinik.';
        }

        $minutes = $this->thresholdLabel($type);

        return "*PENGINGAT ANTREAN KLINIK*\n"
            ."Nomor antrean: {$queue->queue_number}\n"
            ."Perkiraan waktu tunggu kamu sekitar {$minutes} lagi.\n\n"
            .'Mohon bersiap dan pantau informasi antrean. Waktu tunggu merupakan estimasi dan bukan jaminan dokter akan langsung memanggil pada waktu tersebut.';
    }

    /**
     * Tentukan jenis reminder yang jatuh tempo untuk sebuah kanal.
     *
     * Aturan ambang:
     *  - Reminder dikirim ketika estimasi sudah mencapai (<=) ambang tersebut.
     *  - Ambang yang lebih kecil hanya dikirim setelah ambang yang lebih besar
     *    (reminder bergerak turun seiring waktu), sehingga tidak ada burst
     *    beberapa reminder sekaligus ketika scheduler sempat tertinggal.
     *  - Setiap jenis reminder hanya dikirim satu kali (dicek melalui riwayat).
     *
     * @param  array<int, int>  $leadTimes
     * @param  array<int, string>  $alreadySent
     */
    protected function resolveDueType(int $estimatedMinutes, array $leadTimes, array $alreadySent): ?string
    {
        $candidates = [];

        foreach ($leadTimes as $minutes) {
            if ($estimatedMinutes <= $minutes) {
                $candidates[] = $minutes;
            }
        }

        if ($estimatedMinutes <= 0) {
            $candidates[] = 0;
        }

        // Urutkan dari ambang terkecil (paling mendesak) ke terbesar.
        $candidates = array_values(array_unique($candidates));
        sort($candidates);

        foreach ($candidates as $minutes) {
            $type = $minutes === 0 ? self::TYPE_ZERO_MIN : "{$minutes}_min";

            if (in_array($type, $alreadySent, true)) {
                continue;
            }

            // Jangan kirim ambang besar bila ambang yang lebih kecil sudah terkirim.
            $smallerAlreadySent = false;
            foreach ($candidates as $smaller) {
                if ($smaller >= $minutes) {
                    continue;
                }

                $smallerType = $smaller === 0 ? self::TYPE_ZERO_MIN : "{$smaller}_min";

                if (in_array($smallerType, $alreadySent, true)) {
                    $smallerAlreadySent = true;
                    break;
                }
            }

            if ($smallerAlreadySent) {
                continue;
            }

            return $type;
        }

        return null;
    }

    /**
     * Ambang reminder (menit) dari pengaturan database.
     *
     * @return array<int, int>
     */
    protected function resolveLeadTimes(): array
    {
        $config = AppSetting::get('reminder_lead_time', '30,60');

        $times = collect(explode(',', (string) $config))
            ->map(fn ($value) => (int) trim($value))
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return empty($times) ? [30, 60] : $times;
    }

    /**
     * Label teks untuk ambang tertentu.
     */
    protected function thresholdLabel(string $type): string
    {
        if ($type === self::TYPE_ZERO_MIN) {
            return '0 menit';
        }

        $minutes = (int) str_replace('_min', '', $type);

        return $minutes === 60 ? '60 menit (1 jam)' : "{$minutes} menit";
    }

    /**
     * Kirim pesan pada satu kanal dan catat riwayatnya.
     */
    protected function dispatch(Queue $queue, string $channel, string $recipient, string $type, string $message, int $estimatedMinutes): bool
    {
        // Cek ulang status antrean tepat sebelum mengirim.
        $current = Queue::find($queue->id);

        if (! $current || $current->status !== 'menunggu') {
            return false;
        }

        $result = $channel === 'telegram'
            ? $this->telegram->send($recipient, $message)
            : $this->whatsapp->send($recipient, $message);

        QueueReminder::updateOrCreate(
            [
                'queue_id' => $queue->id,
                'channel' => $channel,
                'reminder_type' => $type,
            ],
            [
                'recipient' => $recipient,
                'estimated_remaining_minutes' => $estimatedMinutes,
                'message_body' => $message,
                'status' => ($result['simulated'] ?? false) ? 'simulated' : (($result['success'] ?? false) ? 'sent' : 'failed'),
                'sent_at' => ($result['success'] ?? false) ? now() : null,
                'error_message' => ($result['success'] ?? false) ? null : ($result['message'] ?? 'Pengiriman gagal.'),
            ]
        );

        return (bool) ($result['success'] ?? false);
    }

    /**
     * Kanal notifikasi yang tersedia dan penerimanya.
     *
     * Telegram hanya dipakai bila chat_id terverifikasi (berupa angka).
     *
     * @return array<string, string>
     */
    protected function resolveChannels(Queue $queue): array
    {
        $channels = [];

        $chatId = $queue->telegram_chat_id ?: $queue->patient?->telegram_chat_id;

        if (! empty($chatId) && preg_match('/^-?\d+$/', (string) $chatId) === 1) {
            $channels['telegram'] = (string) $chatId;
        }

        $phone = $queue->patient_phone ?: $queue->patient?->phone;

        if (! empty($phone)) {
            $channels['whatsapp'] = (string) $phone;
        }

        return $channels;
    }

    /**
     * Bersihkan karakter khusus Markdown dari data dinamis (mis. nama pasien)
     * agar Telegram tidak menolak pesan karena entitas tidak valid.
     */
    protected function sanitizeForMarkdown(string $value): string
    {
        return trim(str_replace(['*', '_', '[', ']', '`'], '', $value));
    }
}
