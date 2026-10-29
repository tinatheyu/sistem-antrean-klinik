<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TelegramLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pengujian penghubungan akun pasien dengan Telegram melalui token
 * unik, sementara, dan sekali pakai (bagian 15 prompt).
 */
class TelegramLinkingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.telegram.enabled', true);
        Config::set('services.telegram.bot_token', 'test-token');
        Config::set('services.telegram.bot_username', 'KlinikQueueBot');
        Config::set('services.telegram.webhook_secret', 'secret-test');
        Config::set('services.telegram.link_token_ttl', 15);

        Http::fake();
    }

    protected function makePatient(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Rina Wulandari',
            'email' => 'rina-'.uniqid().'@example.test',
            'phone' => '081311223344',
            'role' => 'pasien',
            'status' => 'aktif',
            'password' => 'password',
        ], $overrides));
    }

    public function test_link_token_can_be_consumed_once_and_stores_chat_id(): void
    {
        $patient = $this->makePatient();
        $linker = app(TelegramLinkService::class);

        $token = $linker->issueToken($patient);

        $result = $linker->consume($token, '555000111', '@rina_wulandari');

        $this->assertTrue($result['success']);

        $patient->refresh();
        $this->assertEquals('555000111', $patient->telegram_chat_id);
        $this->assertEquals('rina_wulandari', $patient->telegram_username);
        $this->assertNull($patient->telegram_link_token);
        $this->assertTrue($patient->hasTelegramLinked());

        // Token sekali pakai: pemakaian kedua harus ditolak.
        $second = $linker->consume($token, '999888777', '@orang_lain');
        $this->assertFalse($second['success']);
        $this->assertEquals('invalid', $second['reason']);

        // chat_id pasien tidak boleh berubah karena token tidak valid.
        $this->assertEquals('555000111', $patient->fresh()->telegram_chat_id);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $linker = app(TelegramLinkService::class);

        $result = $linker->consume('token-tidak-ada', '555000222', null);

        $this->assertFalse($result['success']);
        $this->assertEquals('invalid', $result['reason']);
    }

    public function test_expired_token_is_rejected(): void
    {
        $patient = $this->makePatient();
        $linker = app(TelegramLinkService::class);

        $token = $linker->issueToken($patient);

        // Paksa token kedaluwarsa.
        $patient->forceFill(['telegram_link_token_expires_at' => now()->subMinute()])->save();

        $result = $linker->consume($token, '555000333', null);

        $this->assertFalse($result['success']);
        $this->assertEquals('expired', $result['reason']);
        $this->assertNull($patient->fresh()->telegram_chat_id);
    }

    public function test_token_of_other_patient_cannot_be_used_to_take_over_account(): void
    {
        $victim = $this->makePatient(['email' => 'victim@example.test']);
        $attacker = $this->makePatient(['email' => 'attacker@example.test']);

        $linker = app(TelegramLinkService::class);
        $victimToken = $linker->issueToken($victim);

        // Penyerang memakai token milik korban → yang terhubung tetap korban.
        $result = $linker->consume($victimToken, '111222333', '@attacker');

        $this->assertTrue($result['success']);
        $this->assertEquals('111222333', $victim->fresh()->telegram_chat_id);
        $this->assertNull($attacker->fresh()->telegram_chat_id);
    }

    public function test_unlink_removes_telegram_connection(): void
    {
        $patient = $this->makePatient();
        $linker = app(TelegramLinkService::class);

        $token = $linker->issueToken($patient);
        $linker->consume($token, '555000444', '@rina');

        $linker->unlink($patient->fresh());

        $patient->refresh();
        $this->assertNull($patient->telegram_chat_id);
        $this->assertNull($patient->telegram_username);
        $this->assertFalse($patient->hasTelegramLinked());
    }

    public function test_webhook_rejects_request_without_valid_secret(): void
    {
        $response = $this->postJson('/telegram/webhook', [
            'message' => ['chat' => ['id' => 123], 'text' => '/start'],
        ]);

        $response->assertStatus(403);
    }

    public function test_webhook_start_with_valid_token_links_account(): void
    {
        $patient = $this->makePatient();
        $linker = app(TelegramLinkService::class);
        $token = $linker->issueToken($patient);

        $response = $this->postJson('/telegram/webhook', [
            'message' => [
                'chat' => ['id' => 777888999],
                'from' => ['username' => 'rina_w'],
                'text' => '/start '.$token,
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'secret-test']);

        $response->assertNoContent();

        $patient->refresh();
        $this->assertEquals('777888999', $patient->telegram_chat_id);
        $this->assertEquals('rina_w', $patient->telegram_username);
    }

    public function test_webhook_start_without_token_does_not_link_any_account(): void
    {
        $response = $this->postJson('/telegram/webhook', [
            'message' => [
                'chat' => ['id' => 121212121],
                'from' => ['username' => 'sembarang'],
                'text' => '/start',
            ],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'secret-test']);

        $response->assertNoContent();

        $this->assertEquals(0, User::where('telegram_chat_id', '121212121')->count());
    }
}
