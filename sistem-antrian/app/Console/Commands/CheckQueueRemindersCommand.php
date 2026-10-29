<?php

namespace App\Console\Commands;

use App\Services\QueueReminderService;
use Illuminate\Console\Command;

class CheckQueueRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:check-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Periksa estimasi waktu tunggu antrean hari ini dan kirimkan reminder WhatsApp/Telegram';

    /**
     * Execute the console command.
     */

    public function handle(QueueReminderService $service): int
    {
        $this->info('Memeriksa antrean aktif untuk pengiriman notifikasi...');

        // Menangani konfirmasi pendaftaran (registration_confirmation) yang
        // sudah jatuh tempo sekaligus reminder estimasi waktu tunggu.
        $result = $service->processReminders();

        $this->line("Antrean diperiksa: {$result['processed']}");
        $this->line("Notifikasi terkirim: {$result['sent']}");
        $this->line("Antrean dilewati: {$result['skipped']}");

        foreach ($result['logs'] as $log) {
            $this->comment(" - {$log}");
        }

        $this->info('Pemeriksaan notifikasi selesai.');

        return Command::SUCCESS;
    }

}
