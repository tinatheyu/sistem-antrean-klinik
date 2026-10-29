<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\DoctorSchedule;
use App\Models\Queue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Initial Administrator
        $admin = User::firstOrCreate(
            ['email' => 'admin@klinik.com'],
            [
                'name' => 'Administrator Utama',
                'phone' => '081234567890',
                'role' => 'admin',
                'status' => 'aktif',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );

        // 2. Initial Doctors
        $doc1 = User::firstOrCreate(
            ['email' => 'dr.budi@klinik.com'],
            [
                'name' => 'dr. Budi Santoso, Sp.A',
                'phone' => '081298765431',
                'role' => 'dokter',
                'specialization' => 'Spesialis Anak',
                'status' => 'aktif',
                'password' => Hash::make('dokter123'),
                'email_verified_at' => now(),
            ]
        );

        $doc2 = User::firstOrCreate(
            ['email' => 'dr.siti@klinik.com'],
            [
                'name' => 'dr. Siti Rahmawati, Sp.PD',
                'phone' => '081298765432',
                'role' => 'dokter',
                'specialization' => 'Penyakit Dalam',
                'status' => 'aktif',
                'password' => Hash::make('dokter123'),
                'email_verified_at' => now(),
            ]
        );

        $doc3 = User::firstOrCreate(
            ['email' => 'dr.ahmad@klinik.com'],
            [
                'name' => 'drg. Ahmad Fauzi',
                'phone' => '081298765433',
                'role' => 'dokter',
                'specialization' => 'Poli Gigi & Mulut',
                'status' => 'aktif',
                'password' => Hash::make('dokter123'),
                'email_verified_at' => now(),
            ]
        );

        // 3. Initial Patients
        $patient1 = User::firstOrCreate(
            ['email' => 'rina@gmail.com'],
            [
                'name' => 'Rina Wulandari',
                'phone' => '081311223344',
                'role' => 'pasien',
                'status' => 'aktif',
                'password' => Hash::make('pasien123'),
                'email_verified_at' => now(),
            ]
        );

        $patient2 = User::firstOrCreate(
            ['email' => 'hendra@gmail.com'],
            [
                'name' => 'Hendra Pratama',
                'phone' => '081322334455',
                'role' => 'pasien',
                'status' => 'aktif',
                'password' => Hash::make('pasien123'),
                'email_verified_at' => now(),
            ]
        );

        // Map today's day of week in Indonesian
        $daysIndonesian = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];
        $currentDayName = $daysIndonesian[now()->format('l')];

        // 4. Create Doctor Schedules (including today so queue testing is immediately ready)
        $schedule1 = DoctorSchedule::firstOrCreate(
            ['doctor_id' => $doc1->id, 'day_of_week' => $currentDayName, 'session_name' => 'Sesi Pagi'],
            [
                'start_time' => '08:00:00',
                'end_time' => '12:00:00',
                'quota' => 30,
                'room' => 'Poli Anak - Ruang 101',
                'is_active' => true,
            ]
        );

        $schedule2 = DoctorSchedule::firstOrCreate(
            ['doctor_id' => $doc2->id, 'day_of_week' => $currentDayName, 'session_name' => 'Sesi Siang'],
            [
                'start_time' => '13:00:00',
                'end_time' => '17:00:00',
                'quota' => 25,
                'room' => 'Poli Penyakit Dalam - Ruang 102',
                'is_active' => true,
            ]
        );

        $schedule3 = DoctorSchedule::firstOrCreate(
            ['doctor_id' => $doc3->id, 'day_of_week' => $currentDayName, 'session_name' => 'Sesi Pagi'],
            [
                'start_time' => '09:00:00',
                'end_time' => '13:00:00',
                'quota' => 20,
                'room' => 'Poli Gigi - Ruang 103',
                'is_active' => true,
            ]
        );

        // Also add schedules for all other days for completeness
        $allDays = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        foreach ($allDays as $day) {
            if ($day !== $currentDayName) {
                DoctorSchedule::firstOrCreate(
                    ['doctor_id' => $doc1->id, 'day_of_week' => $day, 'session_name' => 'Sesi Pagi'],
                    [
                        'start_time' => '08:00:00',
                        'end_time' => '12:00:00',
                        'quota' => 30,
                        'room' => 'Poli Anak - Ruang 101',
                        'is_active' => true,
                    ]
                );

                DoctorSchedule::firstOrCreate(
                    ['doctor_id' => $doc2->id, 'day_of_week' => $day, 'session_name' => 'Sesi Siang'],
                    [
                        'start_time' => '13:00:00',
                        'end_time' => '17:00:00',
                        'quota' => 25,
                        'room' => 'Poli Penyakit Dalam - Ruang 102',
                        'is_active' => true,
                    ]
                );
            }
        }

        // 5. App Settings
        AppSetting::set('clinic_name', 'Klinik Pratama Sehat Bersama', 'Nama Klinik / Faskes', 'general');
        AppSetting::set('clinic_address', 'Jl. Merdeka Sehat No. 88, Jakarta Pusat', 'Alamat Klinik', 'general');
        AppSetting::set('clinic_phone', '(021) 7890-1234', 'Nomor Telepon Klinik', 'general');
        AppSetting::set('avg_consultation_time', '15', 'Estimasi Waktu Konsultasi (Menit)', 'queue');
        AppSetting::set('queue_prefix', 'A', 'Prefix Nomor Antrian', 'queue');
        AppSetting::set('max_daily_quota', '50', 'Batas Kuota Harian per Dokter', 'queue');

        // 6. Seed initial queues for today to demo centralized FIFO queue
        // Walk-in patient 1
        Queue::firstOrCreate(
            ['ticket_token' => 'TICKET-WALKIN-001'],
            [
                'queue_number' => 'A-001',
                'daily_sequence' => 1,
                'queue_date' => today(),
                'patient_id' => null, // Walk-in patient without an account!
                'doctor_id' => $doc1->id,
                'schedule_id' => $schedule1->id,
                'patient_name' => 'Bambang Supriyadi (Walk-in)',
                'patient_phone' => '081299887766',
                'status' => 'selesai',
                'is_walkin' => true,
                'called_at' => now()->subMinutes(50),
                'exam_started_at' => now()->subMinutes(48),
                'exam_completed_at' => now()->subMinutes(30),
                'doctor_notes' => 'Pasien flu ringan, diberikan paracetamol dan vitamin C.',
            ]
        );

        // Online patient Rina
        Queue::firstOrCreate(
            ['ticket_token' => 'TICKET-ONLINE-002'],
            [
                'queue_number' => 'A-002',
                'daily_sequence' => 2,
                'queue_date' => today(),
                'patient_id' => $patient1->id,
                'doctor_id' => $doc1->id,
                'schedule_id' => $schedule1->id,
                'patient_name' => $patient1->name,
                'patient_phone' => $patient1->phone,
                'status' => 'dipanggil',
                'is_walkin' => false,
                'called_at' => now()->subMinutes(5),
                'recall_count' => 1,
            ]
        );

        // Online patient Hendra (waiting)
        Queue::firstOrCreate(
            ['ticket_token' => 'TICKET-ONLINE-003'],
            [
                'queue_number' => 'A-003',
                'daily_sequence' => 3,
                'queue_date' => today(),
                'patient_id' => $patient2->id,
                'doctor_id' => $doc1->id,
                'schedule_id' => $schedule1->id,
                'patient_name' => $patient2->name,
                'patient_phone' => $patient2->phone,
                'status' => 'menunggu',
                'is_walkin' => false,
            ]
        );
    }
}
