<?php

namespace Tests\Feature;

use App\Models\DoctorSchedule;
use App\Models\Queue;
use App\Models\User;
use App\Services\RandomForestPredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class RandomForestPredictionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // -------------------------------------------------------------------------
    // Helper: buat objek Queue menunggu yang valid
    // -------------------------------------------------------------------------

    protected function makeWaitingQueue(array $overrides = []): Queue
    {
        $doctor = User::where('role', 'dokter')->first();
        $schedule = DoctorSchedule::where('doctor_id', $doctor->id)->first();

        return Queue::create(array_merge([
            'queue_number' => 'A-001',
            'daily_sequence' => 1,
            'queue_date' => today(),
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Pasien Uji',
            'status' => 'menunggu',
            'is_walkin' => false,
            'priority_status' => 'normal',
            'ticket_token' => 'TK-'.strtoupper(Str::random(10)),
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Test 1: Jika RF dinonaktifkan, predict() harus mengembalikan null
    // -------------------------------------------------------------------------

    public function test_predict_returns_null_when_disabled(): void
    {
        Config::set('services.random_forest.enabled', false);

        Http::fake(); // Pastikan tidak ada HTTP request sama sekali

        $service = new RandomForestPredictionService;
        $queue = $this->makeWaitingQueue();

        $result = $service->predict($queue);

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // Test 2: Jika API merespons sukses, kembalikan nilai estimasi_menit
    // -------------------------------------------------------------------------

    public function test_predict_returns_float_on_successful_api_response(): void
    {
        Config::set('services.random_forest.enabled', true);
        Config::set('services.random_forest.api_url', 'http://127.0.0.1:8000/predict');

        Http::fake([
            'http://127.0.0.1:8000/predict' => Http::response([
                'estimasi_menit' => 18.75,
                'waktu_daftar' => now()->toIso8601String(),
                'catatan' => 'Estimasi, bukan waktu panggilan yang pasti.',
            ], 200),
        ]);

        $service = new RandomForestPredictionService;
        $queue = $this->makeWaitingQueue(['queue_number' => 'A-003', 'daily_sequence' => 3]);

        $result = $service->predict($queue);

        $this->assertNotNull($result);
        $this->assertEquals(18.75, $result);
    }

    // -------------------------------------------------------------------------
    // Test 3: Jika API mengembalikan HTTP 500, predict() harus null (fallback)
    // -------------------------------------------------------------------------

    public function test_predict_returns_null_on_api_error_response(): void
    {
        Config::set('services.random_forest.enabled', true);
        Config::set('services.random_forest.api_url', 'http://127.0.0.1:8000/predict');

        Http::fake([
            'http://127.0.0.1:8000/predict' => Http::response([
                'detail' => 'Gagal melakukan prediksi',
            ], 500),
        ]);

        $service = new RandomForestPredictionService;
        $queue = $this->makeWaitingQueue();

        $result = $service->predict($queue);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // Test 4: Jika API merespons tanpa field estimasi_menit, harus null
    // -------------------------------------------------------------------------

    public function test_predict_returns_null_when_response_missing_estimasi_menit(): void
    {
        Config::set('services.random_forest.enabled', true);
        Config::set('services.random_forest.api_url', 'http://127.0.0.1:8000/predict');

        Http::fake([
            'http://127.0.0.1:8000/predict' => Http::response([
                'unexpected_field' => 'some_value',
            ], 200),
        ]);

        $service = new RandomForestPredictionService;
        $queue = $this->makeWaitingQueue();

        $result = $service->predict($queue);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // Test 5: Payload yang dikirim ke FastAPI harus memiliki field yang benar
    // -------------------------------------------------------------------------

    public function test_predict_sends_correct_payload_to_api(): void
    {
        Config::set('services.random_forest.enabled', true);
        Config::set('services.random_forest.api_url', 'http://127.0.0.1:8000/predict');

        Http::fake([
            'http://127.0.0.1:8000/predict' => Http::response([
                'estimasi_menit' => 20.0,
                'waktu_daftar' => now()->toIso8601String(),
                'catatan' => 'Estimasi.',
            ], 200),
        ]);

        $service = new RandomForestPredictionService;
        $queue = $this->makeWaitingQueue([
            'queue_number' => 'A-005',
            'daily_sequence' => 5,
            'is_walkin' => true,
            'priority_status' => 'prioritas',
        ]);

        $service->predict($queue);

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            // Semua field wajib ada
            $this->assertArrayHasKey('waktu_daftar', $body);
            $this->assertArrayHasKey('nomor_antrean_angka', $body);
            $this->assertArrayHasKey('antrean_sebelumnya', $body);
            $this->assertArrayHasKey('cara_daftar', $body);
            $this->assertArrayHasKey('prioritas', $body);
            $this->assertArrayHasKey('sesi_pendaftaran', $body);

            // Pemetaan nilai: is_walkin=true → 'Langsung', prioritas → 'Ya'
            $this->assertEquals('Langsung', $body['cara_daftar']);
            $this->assertEquals('Ya', $body['prioritas']);

            // nomor_antrean_angka dari "A-005" → 5; antrean_sebelumnya = 5-1 = 4
            $this->assertEquals(5, $body['nomor_antrean_angka']);
            $this->assertEquals(4, $body['antrean_sebelumnya']);

            return true;
        });
    }

    // -------------------------------------------------------------------------
    // Test 6: Pemetaan cara_daftar — online (bukan walkin) → 'Online'
    // -------------------------------------------------------------------------

    public function test_cara_daftar_online_when_not_walkin(): void
    {
        Config::set('services.random_forest.enabled', true);
        Config::set('services.random_forest.api_url', 'http://127.0.0.1:8000/predict');

        Http::fake([
            'http://127.0.0.1:8000/predict' => Http::response([
                'estimasi_menit' => 12.0,
                'waktu_daftar' => now()->toIso8601String(),
                'catatan' => 'Estimasi.',
            ], 200),
        ]);

        $service = new RandomForestPredictionService;
        $queue = $this->makeWaitingQueue(['is_walkin' => false]);

        $service->predict($queue);

        Http::assertSent(function (Request $request) {
            $this->assertEquals('Online', $request->data()['cara_daftar']);

            return true;
        });
    }

    // -------------------------------------------------------------------------
    // Test 7: getEstimatedWaitTimeMinutes() harus menggunakan RF jika aktif
    // -------------------------------------------------------------------------

    public function test_queue_model_uses_rf_when_enabled(): void
    {
        Config::set('services.random_forest.enabled', true);
        Config::set('services.random_forest.api_url', 'http://127.0.0.1:8000/predict');

        Http::fake([
            'http://127.0.0.1:8000/predict' => Http::response([
                'estimasi_menit' => 23.6,
                'waktu_daftar' => now()->toIso8601String(),
                'catatan' => 'Estimasi.',
            ], 200),
        ]);

        $queue = $this->makeWaitingQueue(['queue_number' => 'A-002', 'daily_sequence' => 2]);

        // Hasil harus 24 (round dari 23.6)
        $this->assertEquals(24, $queue->getEstimatedWaitTimeMinutes());
    }

    // -------------------------------------------------------------------------
    // Test 8: getEstimatedWaitTimeMinutes() harus fallback jika RF dinonaktifkan
    // -------------------------------------------------------------------------

    public function test_queue_model_fallback_when_rf_disabled(): void
    {
        Config::set('services.random_forest.enabled', false);

        Http::fake(); // Pastikan tidak ada HTTP request

        // Buat 2 pasien di depan untuk menghasilkan estimasi fallback yang bisa dicek
        $doctor = User::where('role', 'dokter')->first();
        $schedule = DoctorSchedule::where('doctor_id', $doctor->id)->first();

        Queue::create([
            'queue_number' => 'A-001',
            'daily_sequence' => 1,
            'queue_date' => today(),
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Pasien Depan 1',
            'status' => 'menunggu',
            'priority_status' => 'normal',
            'ticket_token' => 'TK-'.strtoupper(Str::random(10)),
        ]);

        $queue = Queue::create([
            'queue_number' => 'A-002',
            'daily_sequence' => 2,
            'queue_date' => today(),
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => 'Pasien Uji Fallback',
            'status' => 'menunggu',
            'priority_status' => 'normal',
            'ticket_token' => 'TK-'.strtoupper(Str::random(10)),
        ]);

        // 1 pasien di depan × 15 menit default = 15
        $this->assertEquals(15, $queue->getEstimatedWaitTimeMinutes());
        Http::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // Test 9: Estimasi harus 0 jika pasien bukan 'menunggu'
    // -------------------------------------------------------------------------

    public function test_queue_returns_zero_for_non_waiting_status(): void
    {
        Config::set('services.random_forest.enabled', true);
        Http::fake(); // Pastikan tidak ada HTTP request untuk status bukan menunggu

        $queue = $this->makeWaitingQueue(['status' => 'dipanggil']);
        $this->assertEquals(0, $queue->getEstimatedWaitTimeMinutes());
        Http::assertNothingSent();
    }
}
