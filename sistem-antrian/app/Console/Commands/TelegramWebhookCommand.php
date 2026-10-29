<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:webhook {action=info : set, delete, atau info}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kelola webhook Bot Telegram (set/delete/info) untuk integrasi penghubungan akun pasien';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegram): int
    {
        $action = strtolower((string) $this->argument('action'));

        return match ($action) {
            'set' => $this->setWebhook($telegram),
            'delete' => $this->deleteWebhook($telegram),
            'info' => $this->infoWebhook($telegram),
            default => $this->invalidAction(),
        };
    }

    protected function setWebhook(TelegramService $telegram): int
    {
        $secret = (string) config('services.telegram.webhook_secret', '');

        if ($secret === '') {
            $this->error('TELEGRAM_WEBHOOK_SECRET belum diatur pada .env. Webhook tidak didaftarkan.');

            return Command::FAILURE;
        }

        if (! $telegram->isEnabled()) {
            $this->error('Integrasi Telegram belum aktif (TELEGRAM_ENABLED / TELEGRAM_BOT_TOKEN).');

            return Command::FAILURE;
        }

        $url = route('telegram.webhook');

        if (! str_starts_with($url, 'https://')) {
            $this->warn('Peringatan: URL webhook bukan HTTPS. Telegram mensyaratkan URL publik HTTPS.');
        }

        $this->line("Mendaftarkan webhook ke: {$url}");

        $result = $telegram->setWebhook($url, $secret);

        $result['success'] ? $this->info($result['message']) : $this->error($result['message']);

        return $result['success'] ? Command::SUCCESS : Command::FAILURE;
    }

    protected function deleteWebhook(TelegramService $telegram): int
    {
        $result = $telegram->deleteWebhook();

        $result['success'] ? $this->info($result['message']) : $this->error($result['message']);

        return $result['success'] ? Command::SUCCESS : Command::FAILURE;
    }

    protected function infoWebhook(TelegramService $telegram): int
    {
        $result = $telegram->getWebhookInfo();

        $this->line($result['message']);

        return $result['success'] ? Command::SUCCESS : Command::FAILURE;
    }

    protected function invalidAction(): int
    {
        $this->error('Aksi tidak dikenal. Gunakan salah satu: set, delete, info.');

        return Command::FAILURE;
    }
}
