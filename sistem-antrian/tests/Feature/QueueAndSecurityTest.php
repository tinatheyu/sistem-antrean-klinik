<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\DoctorSchedule;
use App\Models\Queue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QueueAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test PreventBackHistory middleware sets no-cache headers.
     */
    public function test_prevent_back_history_headers(): void
    {
        $patient = User::where('role', 'pasien')->first();

        $response = $this->actingAs($patient)->get('/pasien/dashboard');

        $response->assertStatus(200);
        $this->assertTrue($response->headers->hasCacheControlDirective('no-cache'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        $response->assertHeader('Pragma', 'no-cache');
    }

    /**
     * Test patient cannot create duplicate active queue on the same day for the same doctor.
     */
    public function test_prevent_duplicate_active_queue_for_patient(): void
    {
        $patient = User::where('role', 'pasien')->first();
        $schedule = DoctorSchedule::where('is_active', true)->first();

        // Already has an active queue seeded for this doctor
        $response = $this->actingAs($patient)->post('/pasien/register-queue', [
            'schedule_id' => $schedule->id,
        ]);

        // Should redirect to existing ticket with info notice
        $response->assertRedirect();
        $response->assertSessionHas('info');
    }

    /**
     * Test admin can create a new authorized doctor account.
     */
    public function test_admin_can_create_doctor_account(): void
    {
        $admin = User::where('role', 'admin')->first();

        $response = $this->actingAs($admin)->post('/admin/doctors', [
            'name' => 'dr. Kartika Sari, Sp.M',
            'email' => 'dr.kartika@klinik.com',
            'phone' => '081288776655',
            'specialization' => 'Spesialis Mata',
            'password' => 'KartikaPass123',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $doctor = User::where('email', 'dr.kartika@klinik.com')->first();
        $this->assertNotNull($doctor);
        $this->assertEquals('dokter', $doctor->role);
        $this->assertEquals('Spesialis Mata', $doctor->specialization);
    }

    /**
     * Test doctor can skip a patient with a recorded reason.
     */
    public function test_doctor_can_skip_patient_with_reason(): void
    {
        $doctor = User::where('role', 'dokter')->first();
        $queue = Queue::where('doctor_id', $doctor->id)->where('status', 'dipanggil')->first();

        $response = $this->actingAs($doctor)->post("/doctor/skip/{$queue->id}", [
            'skip_reason' => 'Pasien tidak merespon panggilan setelah 3 kali pemanggilan.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('warning');

        $queue->refresh();
        $this->assertEquals('dilewati', $queue->status);
        $this->assertEquals('Pasien tidak merespon panggilan setelah 3 kali pemanggilan.', $queue->skip_reason);
    }

    /**
     * Test doctor can start and complete examination.
     */
    public function test_doctor_can_start_and_complete_examination(): void
    {
        $doctor = User::where('role', 'dokter')->first();
        $queue = Queue::where('doctor_id', $doctor->id)->where('status', 'dipanggil')->first();

        // Start exam
        $responseStart = $this->actingAs($doctor)->post("/doctor/start/{$queue->id}");
        $responseStart->assertRedirect();

        $queue->refresh();
        $this->assertEquals('sedang_diperiksa', $queue->status);
        $this->assertNotNull($queue->exam_started_at);

        // Complete exam
        $responseComplete = $this->actingAs($doctor)->post("/doctor/complete/{$queue->id}", [
            'doctor_notes' => 'Pasien flu ringan, diberikan paracetamol 500mg 3x1.',
        ]);
        $responseComplete->assertRedirect();

        $queue->refresh();
        $this->assertEquals('selesai', $queue->status);
        $this->assertNotNull($queue->exam_completed_at);
        $this->assertEquals('Pasien flu ringan, diberikan paracetamol 500mg 3x1.', $queue->doctor_notes);
    }

    /**
     * Test live monitor API and waiting room display.
     */
    public function test_live_monitor_endpoints(): void
    {
        $responseMonitor = $this->get('/display');
        $responseMonitor->assertStatus(200);
        $responseMonitor->assertSee('Layar Panggilan Antrian');

        $responseApi = $this->get('/api/display');
        $responseApi->assertStatus(200);
        $responseApi->assertJsonStructure(['active_calls', 'timestamp']);
    }

    /**
     * Test public ticket verification via token.
     */
    public function test_public_ticket_verification(): void
    {
        $queue = Queue::first();

        $response = $this->get("/tiket/{$queue->ticket_token}");
        $response->assertStatus(200);
        $response->assertSeeText('Tiket Sah & Terdaftar');
        $response->assertSee($queue->queue_number);
    }
}
