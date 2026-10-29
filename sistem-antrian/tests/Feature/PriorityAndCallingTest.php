<?php

namespace Tests\Feature;

use App\Models\DoctorSchedule;
use App\Models\Queue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PriorityAndCallingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test admin can approve a pending priority request.
     */
    public function test_admin_can_approve_priority_request(): void
    {
        $admin = User::where('role', 'admin')->first();
        $doctor = User::where('role', 'dokter')->first();
        $schedule = DoctorSchedule::where('doctor_id', $doctor->id)->first();

        $queue = Queue::create([
            'queue_number' => 'A-099',
            'daily_sequence' => 99,
            'user_id' => null,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Lansia Pasien',
            'patient_phone' => '081234567890',
            'is_walkin' => false,
            'status' => 'menunggu',
            'priority_status' => 'perlu_verifikasi',
            'priority_reason' => 'Lanjut Usia (> 65 tahun)',
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        $originalTicketNumber = $queue->queue_number;

        $response = $this->actingAs($admin)->post("/admin/queues/{$queue->id}/verify-priority", [
            'decision' => 'prioritas',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $queue->refresh();
        $this->assertEquals('prioritas', $queue->priority_status);
        $this->assertEquals($admin->id, $queue->priority_verified_by);
        $this->assertNotNull($queue->priority_verified_at);
        // Ticket number MUST remain unchanged
        $this->assertEquals($originalTicketNumber, $queue->queue_number);
    }

    /**
     * Test admin can set pending priority request to normal.
     */
    public function test_admin_can_set_priority_to_normal(): void
    {
        $admin = User::where('role', 'admin')->first();
        $doctor = User::where('role', 'dokter')->first();
        $schedule = DoctorSchedule::where('doctor_id', $doctor->id)->first();

        $queue = Queue::create([
            'queue_number' => 'A-098',
            'daily_sequence' => 98,
            'user_id' => null,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Pasien Biasa',
            'patient_phone' => '081234567891',
            'is_walkin' => false,
            'status' => 'menunggu',
            'priority_status' => 'perlu_verifikasi',
            'priority_reason' => 'Permintaan Pribadi',
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        $response = $this->actingAs($admin)->post("/admin/queues/{$queue->id}/verify-priority", [
            'decision' => 'normal',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $queue->refresh();
        $this->assertEquals('normal', $queue->priority_status);
        $this->assertEquals($admin->id, $queue->priority_verified_by);
        $this->assertNotNull($queue->priority_verified_at);
        $this->assertEquals('A-098', $queue->queue_number);
    }

    /**
     * Test admin cannot modify priority if patient is already called or completed.
     */
    public function test_admin_cannot_modify_priority_if_patient_not_waiting(): void
    {
        $admin = User::where('role', 'admin')->first();
        $doctor = User::where('role', 'dokter')->first();
        $schedule = DoctorSchedule::where('doctor_id', $doctor->id)->first();

        $queue = Queue::create([
            'queue_number' => 'A-097',
            'daily_sequence' => 97,
            'user_id' => null,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Pasien Sedang Diperiksa',
            'patient_phone' => '081234567892',
            'is_walkin' => false,
            'status' => 'sedang_diperiksa',
            'priority_status' => 'perlu_verifikasi',
            'priority_reason' => 'Ibu Hamil',
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        $response = $this->actingAs($admin)->post("/admin/queues/{$queue->id}/verify-priority", [
            'decision' => 'prioritas',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $queue->refresh();
        $this->assertEquals('perlu_verifikasi', $queue->priority_status);
    }

    /**
     * Test calling order: Priority FIFO comes first, then Normal FIFO.
     * Example: A-001 (Normal), A-002 (Prioritas), A-003 (Normal)
     * Calling order MUST be: A-002, A-001, A-003.
     */
    public function test_priority_fifo_ordering(): void
    {
        $doctor = User::where('role', 'dokter')->first();
        $schedule = DoctorSchedule::where('doctor_id', $doctor->id)->first();

        // Clear existing queues for clean assertion
        Queue::where('doctor_id', $doctor->id)->delete();

        // Create A-001 (Normal, seq 1)
        $q1 = Queue::create([
            'queue_number' => 'A-001',
            'daily_sequence' => 1,
            'user_id' => null,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Pasien Satu',
            'is_walkin' => false,
            'status' => 'menunggu',
            'priority_status' => 'normal',
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        // Create A-002 (Prioritas, seq 2)
        $q2 = Queue::create([
            'queue_number' => 'A-002',
            'daily_sequence' => 2,
            'user_id' => null,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Pasien Dua (Prioritas)',
            'is_walkin' => false,
            'status' => 'menunggu',
            'priority_status' => 'prioritas',
            'priority_reason' => 'Disabilitas Fisik',
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        // Create A-003 (Normal, seq 3)
        $q3 = Queue::create([
            'queue_number' => 'A-003',
            'daily_sequence' => 3,
            'user_id' => null,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Pasien Tiga',
            'is_walkin' => false,
            'status' => 'menunggu',
            'priority_status' => 'normal',
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        // Verify ordering query
        $ordered = Queue::where('doctor_id', $doctor->id)
            ->where('status', 'menunggu')
            ->orderedForCalling()
            ->pluck('queue_number')
            ->toArray();

        // Must be A-002 (Priority), then A-001 (Normal FIFO 1st), then A-003 (Normal FIFO 2nd)
        $this->assertEquals(['A-002', 'A-001', 'A-003'], $ordered);

        // Ticket numbers must still remain unchanged
        $this->assertEquals('A-001', $q1->refresh()->queue_number);
        $this->assertEquals('A-002', $q2->refresh()->queue_number);
        $this->assertEquals('A-003', $q3->refresh()->queue_number);
    }

    /**
     * Test doctor calling next patient calls the exact priority patient and prevents duplicate call.
     */
    public function test_doctor_calls_next_and_prevents_duplicate_call(): void
    {
        $doctor = User::where('role', 'dokter')->first();
        $schedule = DoctorSchedule::where('doctor_id', $doctor->id)->first();

        // Clear existing queues for clean state
        Queue::where('doctor_id', $doctor->id)->delete();

        // Create A-001 (Normal) and A-002 (Prioritas)
        Queue::create([
            'queue_number' => 'A-001',
            'daily_sequence' => 1,
            'user_id' => null,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Pasien Normal',
            'status' => 'menunggu',
            'priority_status' => 'normal',
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        $p2 = Queue::create([
            'queue_number' => 'A-002',
            'daily_sequence' => 2,
            'user_id' => null,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Pasien Prioritas',
            'status' => 'menunggu',
            'priority_status' => 'prioritas',
            'priority_reason' => 'Balita Sakit Berat',
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        // Doctor calls next
        $response = $this->actingAs($doctor)->post('/doctor/call-next');
        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('called_announcement');

        // Check announcement session has the exact ticket number and Indonesian TTS text
        $announcement = session('called_announcement');
        $this->assertEquals('A-002', $announcement['queue_number']);
        $this->assertStringContainsString('Nomor antrean A-002', $announcement['tts_text']);

        // Check DB status: A-002 should now be 'dipanggil'
        $p2->refresh();
        $this->assertEquals('dipanggil', $p2->status);

        // Attempt second immediate call while A-002 is still active (calling concurrency protection)
        $secondResponse = $this->actingAs($doctor)->post('/doctor/call-next');
        $secondResponse->assertRedirect();
        $secondResponse->assertSessionHas('error'); // Blocked!
    }

    /**
     * Test monitor API outputs the exact called patient with matching TTS format.
     */
    public function test_monitor_api_matches_called_patient(): void
    {
        $doctor = User::where('role', 'dokter')->first();
        $schedule = DoctorSchedule::where('doctor_id', $doctor->id)->first();

        // Clear existing queues
        Queue::where('doctor_id', $doctor->id)->delete();

        $queue = Queue::create([
            'queue_number' => 'A-010',
            'daily_sequence' => 10,
            'user_id' => null,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Budi Santoso',
            'status' => 'dipanggil',
            'priority_status' => 'prioritas',
            'called_at' => now(),
            'queue_date' => today(),
            'ticket_token' => Str::random(32),
        ]);

        $response = $this->getJson('/api/display');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'active_calls' => [
                '*' => [
                    'id',
                    'queue_number',
                    'patient_name',
                    'doctor_name',
                    'room',
                    'status',
                    'priority_status',
                    'tts_text',
                ],
            ],
        ]);

        $activeCalls = $response->json('active_calls');
        $this->assertNotEmpty($activeCalls);
        $firstCall = collect($activeCalls)->firstWhere('queue_number', 'A-010');
        $this->assertNotNull($firstCall);
        $this->assertEquals('prioritas', $firstCall['priority_status']);
        $this->assertStringContainsString('Nomor antrean A-010', $firstCall['tts_text']);
    }
}
