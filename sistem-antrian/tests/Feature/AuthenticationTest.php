<?php

namespace Tests\Feature;

use App\Models\Queue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test login page is accessible and displays required elements.
     */
    public function test_login_page_renders_cleanly(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Klinik Pratama Sehat Bersama');
        $response->assertSee('Email atau No. Telepon');
        $response->assertSee('Kata Sandi');
        $response->assertSee('Ingat saya');
        $response->assertSee('Lupa kata sandi?');
        $response->assertSeeText('Belum punya akun pasien? Daftar di sini.');
    }

    /**
     * Test root URL redirects guests to the login page.
     */
    public function test_root_redirects_guest_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    /**
     * Test root URL redirects authenticated users to the dashboard for their role.
     */
    public function test_root_redirects_authenticated_user_by_role(): void
    {
        $expected = [
            'admin' => '/admin/dashboard',
            'dokter' => '/doctor/dashboard',
            'pasien' => '/pasien/dashboard',
        ];

        foreach ($expected as $role => $dashboard) {
            $user = User::where('role', $role)->firstOrFail();

            $this->actingAs($user)->get('/')->assertRedirect($dashboard);
        }
    }

    /**
     * Test admin login redirects to /admin/dashboard.
     */
    public function test_admin_login_redirects_to_admin_dashboard(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@klinik.com',
            'password' => 'admin123',
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticated();
        $this->assertEquals('admin', Auth::user()->role);
    }

    /**
     * Test doctor login redirects to /doctor/dashboard.
     */
    public function test_doctor_login_redirects_to_doctor_dashboard(): void
    {
        $response = $this->post('/login', [
            'email' => 'dr.budi@klinik.com',
            'password' => 'dokter123',
        ]);

        $response->assertRedirect('/doctor/dashboard');
        $this->assertAuthenticated();
        $this->assertEquals('dokter', Auth::user()->role);
    }

    /**
     * Test patient login redirects to /pasien/dashboard.
     */
    public function test_patient_login_redirects_to_patient_dashboard(): void
    {
        $response = $this->post('/login', [
            'email' => 'rina@gmail.com',
            'password' => 'pasien123',
        ]);

        $response->assertRedirect('/pasien/dashboard');
        $this->assertAuthenticated();
        $this->assertEquals('pasien', Auth::user()->role);
    }

    /**
     * Test patient registration page renders and enforces required fields.
     */
    public function test_patient_registration_page_renders(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('Pendaftaran Pasien Baru');
        $response->assertSee('Sudah punya akun?');
    }

    /**
     * Test public registration strictly assigns 'pasien' role and logs in.
     */
    public function test_patient_registration_creates_patient_and_logs_in(): void
    {
        $response = $this->post('/register', [
            'name' => 'Dewi Lestari',
            'email' => 'dewi@example.com',
            'phone' => '081299998888',
            'password' => 'DewiPass123',
            'password_confirmation' => 'DewiPass123',
            'privacy_policy' => '1',
            'role' => 'admin', // Attempt privilege escalation!
        ]);

        $response->assertRedirect('/pasien/dashboard');
        $this->assertAuthenticated();

        $user = User::where('email', 'dewi@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('pasien', $user->role, 'Role must strictly remain pasien regardless of submission');
        $this->assertTrue(Hash::check('DewiPass123', $user->password));
    }

    /**
     * Test role-based protection: patient cannot access admin dashboard.
     */
    public function test_patient_cannot_access_admin_dashboard(): void
    {
        $patient = User::where('role', 'pasien')->first();

        $response = $this->actingAs($patient)->get('/admin/dashboard');

        // Middleware redirects unauthorized role back to their own dashboard
        $response->assertRedirect('/pasien/dashboard');
    }

    /**
     * Test role-based protection: doctor cannot access admin settings.
     */
    public function test_doctor_cannot_access_admin_settings(): void
    {
        $doctor = User::where('role', 'dokter')->first();

        $response = $this->actingAs($doctor)->get('/admin/settings');

        $response->assertRedirect('/doctor/dashboard');
    }

    /**
     * Test unauthenticated access redirects to login.
     */
    public function test_unauthenticated_access_redirects_to_login(): void
    {
        $response = $this->get('/admin/dashboard');
        $response->assertRedirect('/login');

        $response = $this->get('/doctor/dashboard');
        $response->assertRedirect('/login');

        $response = $this->get('/pasien/dashboard');
        $response->assertRedirect('/login');
    }

    /**
     * Test patient cannot view another patient's ticket.
     */
    public function test_patient_cannot_view_another_patients_ticket(): void
    {
        $patient1 = User::where('email', 'rina@gmail.com')->first();
        $patient2 = User::where('email', 'hendra@gmail.com')->first();

        // Find hendra's queue
        $hendraQueue = Queue::where('patient_id', $patient2->id)->first();
        $this->assertNotNull($hendraQueue);

        // Rina tries to view Hendra's ticket
        $response = $this->actingAs($patient1)->get("/pasien/ticket/{$hendraQueue->ticket_token}");

        $response->assertStatus(403);
    }

    /**
     * Test walk-in registration by admin creates a FIFO queue without patient account.
     */
    public function test_admin_can_register_walk_in_patient(): void
    {
        $admin = User::where('role', 'admin')->first();
        $doctor = User::where('role', 'dokter')->first();

        $response = $this->actingAs($admin)->post('/admin/walkin', [
            'patient_name' => 'Joko Widodo (Walk-in)',
            'patient_phone' => '081233445566',
            'doctor_id' => $doctor->id,
        ]);

        $response->assertRedirect('/admin/queues');

        $queue = Queue::where('patient_name', 'Joko Widodo (Walk-in)')->first();
        $this->assertNotNull($queue);
        $this->assertTrue($queue->is_walkin);
        $this->assertNull($queue->patient_id);
        $this->assertEquals('menunggu', $queue->status);
    }

    /**
     * Test doctor can call next patient in FIFO order.
     */
    public function test_doctor_can_call_next_patient(): void
    {
        $doctor = User::where('role', 'dokter')->first();

        // Call next
        $response = $this->actingAs($doctor)->post('/doctor/call-next');
        $response->assertRedirect();

        $called = Queue::where('doctor_id', $doctor->id)->where('status', 'dipanggil')->first();
        $this->assertNotNull($called);
    }

    /**
     * Test logout clears session and redirects to login.
     */
    public function test_logout_action(): void
    {
        $user = User::where('role', 'admin')->first();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
