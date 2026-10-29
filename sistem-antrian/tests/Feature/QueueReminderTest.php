<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\DoctorSchedule;
use App\Models\Queue;
use App\Models\QueueReminder;
use App\Models\User;
use App\Services\QueueReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class QueueReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        // Nonaktifkan Random Forest agar hasil estimasi deterministik (fallback
        // jumlah pasien di depan x rata-rata waktu konsultasi).
        Config::set('services.random_forest.enabled', false);
        Http::fake();
    }

    /**
     * Test reminder is only sent to patients in 'menunggu' status.
     */
    public function test_reminder_only_sent_to_waiting_patients(): void
    {
        $doctor = User::where('role', 'dokter')->first();
        $schedule = DoctorSchedule::where('doctor_id', $doctor->id)->first();

        // Queue that is already 'dipanggil'
        $calledQueue = Queue::create([
            'queue_number' => 'A-050',
            'daily_sequence' => 50,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Pasien Sudah Dipanggil',
            'patient_phone' => '081299990001',
            'telegram_chat_id' => '123456789',
            'status' => 'dipanggil',
            'priority_status' => 'normal',
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        $service = app(QueueReminderService::class);
        $result = $service->sendDueReminders();

        // No reminders should have been dispatched for the called queue
        $this->assertEquals(0, QueueReminder::where('queue_id', $calledQueue->id)->count());
    }

    /**
     * Test reminder prevents duplicate sends for the same queue, channel, and reminder type.
     */
    public function test_reminder_prevents_duplicate_sends(): void
    {
        $doctor = User::where('role', 'dokter')->first();
        $schedule = DoctorSchedule::where('doctor_id', $doctor->id)->first();

        Queue::where('doctor_id', $doctor->id)->delete();

        // Create 2 patients ahead so wait time is ~20-30 mins
        Queue::create([
            'queue_number' => 'A-001',
            'daily_sequence' => 1,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Pasien Depan 1',
            'status' => 'menunggu',
            'priority_status' => 'normal',
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        Queue::create([
            'queue_number' => 'A-002',
            'daily_sequence' => 2,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Pasien Depan 2',
            'status' => 'menunggu',
            'priority_status' => 'normal',
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        // Target patient: ~20-30 mins wait time with phone & telegram
        $target = Queue::create([
            'queue_number' => 'A-003',
            'daily_sequence' => 3,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Target Pasien',
            'patient_phone' => '081299990003',
            'telegram_chat_id' => '987654321',
            'status' => 'menunggu',
            'priority_status' => 'normal',
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        $service = app(QueueReminderService::class);

        // First pass
        $firstResult = $service->sendDueReminders();
        $targetRemindersCount = QueueReminder::where('queue_id', $target->id)->count();
        $this->assertGreaterThan(0, $targetRemindersCount);

        // Second pass immediately: duplicate prevention must ensure no new records created
        $secondResult = $service->sendDueReminders();
        $this->assertEquals($targetRemindersCount, QueueReminder::where('queue_id', $target->id)->count());
        $this->assertEquals(0, $secondResult['sent']);
    }

    /**
     * Test reminder estimation properly accounts for priority queues ahead.
     */
    public function test_reminder_calculates_priority_ahead_correctly(): void
    {
        $doctor = User::where('role', 'dokter')->first();
        $schedule = DoctorSchedule::where('doctor_id', $doctor->id)->first();

        Queue::where('doctor_id', $doctor->id)->delete();

        // Normal patient with lower sequence
        $normal1 = Queue::create([
            'queue_number' => 'A-001',
            'daily_sequence' => 1,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Normal 1',
            'status' => 'menunggu',
            'priority_status' => 'normal',
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        // Priority patient with higher sequence
        $priority1 = Queue::create([
            'queue_number' => 'A-002',
            'daily_sequence' => 2,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Prioritas 1',
            'status' => 'menunggu',
            'priority_status' => 'prioritas',
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        // Priority patient is called first: 0 patients ahead
        $this->assertEquals(0, $priority1->getPatientsAheadCount());
        $this->assertEquals(0, $priority1->getEstimatedWaitTimeMinutes());

        // Normal patient 1: 1 priority patient ahead of them!
        $this->assertEquals(1, $normal1->getPatientsAheadCount());
        $this->assertGreaterThan(0, $normal1->getEstimatedWaitTimeMinutes());
    }

    /**
     * Test artisan command 'queue:check-reminders' executes successfully.
     */
    public function test_artisan_check_reminders_command(): void
    {
        $this->artisan('queue:check-reminders')
            ->expectsOutputToContain('Pemeriksaan notifikasi selesai')
            ->assertSuccessful();
    }

    /**
     * Konfirmasi pendaftaran dijadwalkan dengan jenis notifikasi tersendiri
     * dan dikirim tepat satu kali.
     */
    public function test_registration_confirmation_is_scheduled_and_sent_once(): void
    {
        $doctor = User::where('role', 'dokter')->first();
        $schedule = DoctorSchedule::where('doctor_id', $doctor->id)->first();

        $queue = Queue::create([
            'queue_number' => 'A-010',
            'daily_sequence' => 10,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Rina Wulandari',
            'patient_phone' => '081299990010',
            'telegram_chat_id' => '1004132233',
            'status' => 'menunggu',
            'priority_status' => 'normal',
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        $service = app(QueueReminderService::class);
        $service->scheduleRegistrationConfirmation($queue);

        // Konfirmasi dicatat sebagai pending dengan jenis tersendiri.
        $pending = QueueReminder::where('queue_id', $queue->id)
            ->where('reminder_type', QueueReminderService::TYPE_REGISTRATION_CONFIRMATION)
            ->get();

        $this->assertGreaterThan(0, $pending->count());
        $this->assertTrue($pending->every(fn ($r) => $r->status === 'pending'));

        // Penjadwalan ulang tidak boleh membuat duplikat.
        $service->scheduleRegistrationConfirmation($queue);
        $this->assertEquals(
            $pending->count(),
            QueueReminder::where('queue_id', $queue->id)
                ->where('reminder_type', QueueReminderService::TYPE_REGISTRATION_CONFIRMATION)
                ->count()
        );

        // Pengiriman oleh scheduler: satu kali, tidak diulang pada pemeriksaan berikutnya.
        $service->processReminders();

        $sent = QueueReminder::where('queue_id', $queue->id)
            ->where('reminder_type', QueueReminderService::TYPE_REGISTRATION_CONFIRMATION)
            ->whereIn('status', ['sent', 'simulated'])
            ->count();

        $this->assertGreaterThan(0, $sent);

        $service->processReminders();

        $this->assertEquals(
            $sent,
            QueueReminder::where('queue_id', $queue->id)
                ->where('reminder_type', QueueReminderService::TYPE_REGISTRATION_CONFIRMATION)
                ->whereIn('status', ['sent', 'simulated'])
                ->count()
        );
    }

    /**
     * Konfirmasi dibatalkan bila antrean sudah dibatalkan.
     */
    public function test_registration_confirmation_cancelled_when_queue_cancelled(): void
    {
        $doctor = User::where('role', 'dokter')->first();
        $schedule = DoctorSchedule::where('doctor_id', $doctor->id)->first();

        $queue = Queue::create([
            'queue_number' => 'A-011',
            'daily_sequence' => 11,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Pasien Batal',
            'patient_phone' => '081299990011',
            'telegram_chat_id' => '1004132234',
            'status' => 'menunggu',
            'priority_status' => 'normal',
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        $service = app(QueueReminderService::class);
        $service->scheduleRegistrationConfirmation($queue);

        $queue->update(['status' => 'dibatalkan']);

        $service->processReminders();

        $this->assertEquals(0, QueueReminder::where('queue_id', $queue->id)
            ->where('reminder_type', QueueReminderService::TYPE_REGISTRATION_CONFIRMATION)
            ->where('status', 'pending')
            ->count());

        $this->assertGreaterThan(0, QueueReminder::where('queue_id', $queue->id)
            ->where('reminder_type', QueueReminderService::TYPE_REGISTRATION_CONFIRMATION)
            ->where('status', 'cancelled')
            ->count());
    }

    /**
     * Ambang reminder demo (1,2) dikirim saat estimasi mencapai ambang,
     * tidak berulang, dan tidak mengirim burst ambang yang lebih besar.
     */
    public function test_reminder_thresholds_with_demo_config_send_once_each(): void
    {
        AppSetting::set('reminder_lead_time', '1,2');

        $doctor = User::where('role', 'dokter')->first();
        $schedule = DoctorSchedule::where('doctor_id', $doctor->id)->first();

        Queue::where('doctor_id', $doctor->id)->delete();

        // 1 pasien di depan → estimasi 15 menit (fallback), belum menyentuh ambang 2.
        Queue::create([
            'queue_number' => 'A-001',
            'daily_sequence' => 1,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Pasien Depan',
            'status' => 'menunggu',
            'priority_status' => 'normal',
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        $target = Queue::create([
            'queue_number' => 'A-002',
            'daily_sequence' => 2,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Target Demo',
            'patient_phone' => '081299990020',
            'telegram_chat_id' => '1004132235',
            'status' => 'menunggu',
            'priority_status' => 'normal',
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        $service = app(QueueReminderService::class);

        // Estimasi awal 15 menit → belum ada reminder estimasi.
        $service->processReminders();
        $this->assertEquals(0, QueueReminder::where('queue_id', $target->id)
            ->whereIn('reminder_type', ['1_min', '2_min'])
            ->count());

        // Jadikan pasien di depan selesai → estimasi turun ke 0 menit.
        Queue::where('doctor_id', $doctor->id)->where('queue_number', 'A-001')
            ->update(['status' => 'selesai']);

        $service->processReminders();

        // Karena estimasi langsung menyentuh 0, hanya jenis paling mendesak (1_min
        // atau 0_min) yang dikirim — bukan burst 2_min sekaligus.
        $targetTypes = QueueReminder::where('queue_id', $target->id)
            ->whereIn('reminder_type', ['1_min', '2_min', '0_min'])
            ->pluck('reminder_type')
            ->all();

        $this->assertNotEmpty($targetTypes);
        $this->assertNotContains('2_min', $targetTypes);

        $countAfterFirst = count($targetTypes);

        // Pemeriksaan berikutnya tidak mengirim ulang jenis yang sama.
        $service->processReminders();
        $this->assertEquals(
            $countAfterFirst,
            QueueReminder::where('queue_id', $target->id)
                ->whereIn('reminder_type', ['1_min', '2_min', '0_min'])
                ->count()
        );
    }

    /**
     * Tidak ada reminder untuk antrean yang sudah selesai/dibatalkan.
     */
    public function test_no_estimate_reminder_for_finished_or_cancelled_queue(): void
    {
        $doctor = User::where('role', 'dokter')->first();
        $schedule = DoctorSchedule::where('doctor_id', $doctor->id)->first();

        Queue::where('doctor_id', $doctor->id)->delete();

        $finished = Queue::create([
            'queue_number' => 'A-030',
            'daily_sequence' => 30,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Pasien Selesai',
            'patient_phone' => '081299990030',
            'telegram_chat_id' => '1004132236',
            'status' => 'selesai',
            'priority_status' => 'normal',
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        $cancelled = Queue::create([
            'queue_number' => 'A-031',
            'daily_sequence' => 31,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Pasien Dibatalkan',
            'patient_phone' => '081299990031',
            'telegram_chat_id' => '1004132237',
            'status' => 'dibatalkan',
            'priority_status' => 'normal',
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        app(QueueReminderService::class)->processReminders();

        $this->assertEquals(0, QueueReminder::where('queue_id', $finished->id)->count());
        $this->assertEquals(0, QueueReminder::where('queue_id', $cancelled->id)->count());
    }
}
