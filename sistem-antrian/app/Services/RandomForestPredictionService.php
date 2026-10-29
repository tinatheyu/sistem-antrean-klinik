<?php

namespace App\Services;

use App\Models\Queue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service untuk memanggil API prediksi waktu tunggu Random Forest (FastAPI).
 *
 * Fitur model yang digunakan saat training:
 *   - Bulan, Hari_Dalam_Minggu, Akhir_Pekan, Jam_Pendaftaran  (dari waktu_daftar)
 *   - Nomor_Antrean_Angka                                      (angka nomor antrean)
 *   - Antrean_Sebelumnya = Nomor_Antrean_Angka - 1            (definisi saat training)
 *   - Cara Daftar  : ['Langsung'] saja dalam dataset; nilai lain tetap aman (OHE ignore)
 *   - Prioritas    : 'Tidak' | 'Ya'                           (dipetakan dari priority_status)
 *   - Sesi_Pendaftaran : 'Pagi' | 'Sore'                     (dipetakan dari jam pendaftaran)
 *
 * Jika API tidak tersedia atau prediksi gagal, kembalikan null agar pemanggil
 * dapat jatuh ke metode estimasi lama (fallback).
 */
class RandomForestPredictionService
{
    /**
     * Prediksi estimasi waktu tunggu (menit) untuk sebuah antrean.
     *
     * @return float|null Estimasi menit, atau null jika API tidak tersedia/gagal.
     */
    public function predict(Queue $queue): ?float
    {
        if (! $this->isEnabled()) {
            Log::info('[RandomForest] Integrasi dinonaktifkan (RANDOM_FOREST_ENABLED=false). Estimasi memakai fallback.');

            return null;
        }

        $apiUrl = config('services.random_forest.api_url');
        $connectTimeout = config('services.random_forest.connect_timeout', 1);
        $timeout = config('services.random_forest.timeout', 2);

        $payload = $this->buildPayload($queue);

        if ($payload === null) {
            Log::warning('[RandomForest] Payload tidak dapat dibangun — data antrean tidak lengkap.', [
                'queue_id' => $queue->id,
            ]);

            return null;
        }

        try {
            $response = Http::connectTimeout($connectTimeout)
                ->timeout($timeout)
                ->asJson()
                ->post($apiUrl, $payload);

            if (! $response->successful()) {
                Log::warning('[RandomForest] API merespons status HTTP non-2xx.', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();

            if (! isset($data['estimasi_menit']) || ! is_numeric($data['estimasi_menit'])) {
                Log::warning('[RandomForest] Respons JSON tidak memiliki field estimasi_menit yang valid.');

                return null;
            }

            $estimasi = (float) $data['estimasi_menit'];

            Log::debug('[RandomForest] Prediksi berhasil dari model.', [
                'queue_id' => $queue->id,
                'estimasi_menit' => $estimasi,
            ]);

            return max(0.0, $estimasi);

        } catch (ConnectionException $e) {
            Log::info('[RandomForest] API tidak dapat dihubungi (timeout/offline). Menggunakan fallback.');

            return null;
        } catch (\Throwable $e) {
            Log::error('[RandomForest] Kesalahan tidak terduga saat memanggil API.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Bangun payload request POST untuk FastAPI.
     *
     * Definisi fitur berdasarkan notebook pelatihan (Clinic Wait Time Prediction):
     * - Antrean_Sebelumnya = Nomor_Antrean_Angka - 1  (bukan jumlah pasien di depan antrean)
     * - Prioritas: 'Ya' jika priority_status = 'prioritas', selainnya 'Tidak'
     * - Cara Daftar: 'Online' jika !is_walkin, 'Langsung' jika is_walkin
     *   (dataset training hanya memiliki 'Langsung'; OHE handle_unknown='ignore' aman untuk nilai lain)
     * - Sesi_Pendaftaran: dipetakan dari jam registrasi (Pagi: 05-10, Sore: 14-20)
     *
     * @return array<string, mixed>|null
     */
    protected function buildPayload(Queue $queue): ?array
    {
        // Gunakan created_at sebagai waktu_daftar (waktu pasien mendaftar ke sistem)
        $waktuDaftar = $queue->created_at;

        if ($waktuDaftar === null) {
            return null;
        }

        // Ambil angka dari queue_number (mis. "A-003" → 3)
        preg_match('/(\d+)/', $queue->queue_number, $matches);
        $nomorAntreanAngka = isset($matches[1]) ? (int) $matches[1] : $queue->daily_sequence;

        if ($nomorAntreanAngka < 1) {
            return null;
        }

        // Antrean_Sebelumnya = nomor - 1 (sesuai definisi training)
        $antreanSebelumnya = max(0, $nomorAntreanAngka - 1);

        // Pemetaan Prioritas: model dilatih dengan 'Tidak'/'Ya'
        $prioritas = $queue->priority_status === 'prioritas' ? 'Ya' : 'Tidak';

        // Pemetaan Cara Daftar: walk-in = Langsung, online = Online
        $caraDaftar = $queue->is_walkin ? 'Langsung' : 'Online';

        // Pemetaan Sesi Pendaftaran dari jam pendaftaran (sesuai fungsi sesi_dari_jam training)
        $jamPendaftaran = (float) $waktuDaftar->hour + ($waktuDaftar->minute / 60);
        $sesiPendaftaran = $this->resolveSesiPendaftaran($jamPendaftaran);

        return [
            'waktu_daftar' => $waktuDaftar->toIso8601String(),
            'nomor_antrean_angka' => $nomorAntreanAngka,
            'antrean_sebelumnya' => $antreanSebelumnya,
            'cara_daftar' => $caraDaftar,
            'prioritas' => $prioritas,
            'sesi_pendaftaran' => $sesiPendaftaran,
        ];
    }

    /**
     * Petakan jam (float 0–24) ke sesi praktik klinik.
     *
     * Sesuai fungsi sesi_dari_jam() pada notebook pelatihan:
     *   if 5 <= jam < 10: 'Pagi'
     *   if 14 <= jam < 20: 'Sore'
     *   else: 'Di luar jadwal' (OHE ignore — tidak menghasilkan kolom; model tetap berjalan)
     */
    protected function resolveSesiPendaftaran(float $jam): string
    {
        if ($jam >= 5.0 && $jam < 10.0) {
            return 'Pagi';
        }

        if ($jam >= 14.0 && $jam < 20.0) {
            return 'Sore';
        }

        // Jam di luar jadwal rutin — OHE handle_unknown='ignore' akan mengabaikannya
        // dengan aman tanpa melempar error
        return 'Di luar jadwal';
    }

    /**
     * Cek apakah integrasi Random Forest diaktifkan via konfigurasi.
     */
    public function isEnabled(): bool
    {
        return (bool) config('services.random_forest.enabled', false);
    }
}
