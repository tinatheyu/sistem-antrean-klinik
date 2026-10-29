<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Models\DoctorSchedule;
use App\Models\Queue;
use App\Services\TelegramLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PatientController extends Controller
{
    /**
     * Patient Dashboard
     */
    public function dashboard(): View
    {
        /** @var \App\Models\User $patient */
        $patient = Auth::user();
        $today = today();

        // Get active queue for today (if any)
        $activeQueue = Queue::with(['doctor', 'schedule'])
            ->where('patient_id', $patient->id)
            ->whereDate('queue_date', $today)
            ->whereIn('status', ['menunggu', 'dipanggil', 'sedang_diperiksa'])
            ->latest()
            ->first();

        $patientsAhead = 0;
        $estimatedWaitTime = 0;

        if ($activeQueue) {
            $patientsAhead = $activeQueue->getPatientsAheadCount();
            $estimatedWaitTime = $activeQueue->getEstimatedWaitTimeMinutes();
        }

        // Today's day name in Indonesian
        $daysIndonesian = [
            'Sunday' => 'Minggu',
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];
        $currentDay = $daysIndonesian[now()->format('l')];

        // Available practice schedules for today
        $availableSchedules = DoctorSchedule::with('doctor')
            ->where('is_active', true)
            ->where('day_of_week', $currentDay)
            ->get();

        // Weekly schedule preview (compatible with SQLite, MySQL, and PostgreSQL)
        $allSchedules = DoctorSchedule::with('doctor')
            ->where('is_active', true)
            ->orderByRaw("CASE day_of_week 
                WHEN 'Senin' THEN 1 
                WHEN 'Selasa' THEN 2 
                WHEN 'Rabu' THEN 3 
                WHEN 'Kamis' THEN 4 
                WHEN 'Jumat' THEN 5 
                WHEN 'Sabtu' THEN 6 
                WHEN 'Minggu' THEN 7 
                ELSE 8 END")
            ->get();

        // Personal queue history (strictly patient's own history)
        $history = Queue::with('doctor')
            ->where('patient_id', $patient->id)
            ->latest('queue_date')
            ->limit(10)
            ->get();

        // Status penghubungan Telegram untuk banner notifikasi
        $telegramLinker = app(TelegramLinkService::class);
        $telegramAvailable = $telegramLinker->isAvailable();
        $telegramLinkUrl = $patient->hasTelegramLinked() ? null : $telegramLinker->startLinkUrl($patient);

        return view('pasien.dashboard', compact(
            'patient',
            'activeQueue',
            'patientsAhead',
            'estimatedWaitTime',
            'availableSchedules',
            'allSchedules',
            'history',
            'currentDay',
            'telegramAvailable',
            'telegramLinkUrl'
        ));
    }

    /**
     * Register for a doctor's practice session (Queue Registration)
     */
    public function registerQueue(Request $request): RedirectResponse
    {
        $patient = Auth::user();

        $validated = $request->validate([
            'schedule_id' => ['required', 'exists:doctor_schedules,id'],
            'request_priority' => ['nullable', 'boolean'],
            'priority_reason' => ['nullable', 'required_if:request_priority,1', 'string', 'max:255'],
        ], [
            'schedule_id.required' => 'Silakan pilih jadwal dokter yang tersedia.',
            'priority_reason.required_if' => 'Silakan pilih atau isi alasan pengajuan prioritas.',
        ]);

        $schedule = DoctorSchedule::with('doctor')->findOrFail($validated['schedule_id']);
        $doctor = $schedule->doctor;

        // Verify schedule is active and doctor is active
        if (! $schedule->is_active || $doctor->status !== 'aktif') {
            return back()->with('error', 'Maaf, jadwal praktik dokter ini sedang tidak aktif.');
        }

        $today = today();

        // Business Rule: Check for duplicate active registration today
        $existingQueue = Queue::where('patient_id', $patient->id)
            ->whereDate('queue_date', $today)
            ->where('doctor_id', $doctor->id)
            ->whereIn('status', ['menunggu', 'dipanggil', 'sedang_diperiksa'])
            ->first();

        if ($existingQueue) {
            return redirect()->route('pasien.ticket', ['token' => $existingQueue->ticket_token])
                ->with('info', "Anda sudah memiliki antrian aktif ({$existingQueue->queue_number}) untuk dokter {$doctor->name} hari ini.");
        }

        // Quota check
        $totalRegisteredToday = Queue::whereDate('queue_date', $today)
            ->where('schedule_id', $schedule->id)
            ->count();

        if ($totalRegisteredToday >= $schedule->quota) {
            return back()->with('error', 'Mohon maaf, kuota antrian untuk sesi praktik ini telah penuh.');
        }

        $prefix = AppSetting::get('queue_prefix', 'A');

        // Centralized FIFO ordering: get last daily sequence for this doctor today
        $lastQueue = Queue::whereDate('queue_date', $today)
            ->where('doctor_id', $doctor->id)
            ->orderBy('daily_sequence', 'desc')
            ->first();

        $nextSequence = $lastQueue ? ($lastQueue->daily_sequence + 1) : 1;
        $queueNumber = sprintf('%s-%03d', $prefix, $nextSequence);
        $ticketToken = 'TK-'.strtoupper(Str::random(10));

        // Priority determination: online request requires verification, normal otherwise
        $hasPriorityRequest = ! empty($validated['request_priority']);
        $priorityStatus = $hasPriorityRequest ? 'perlu_verifikasi' : 'normal';
        $priorityReason = $hasPriorityRequest ? ($validated['priority_reason'] ?? null) : null;

        // Chat ID Telegram diambil hanya dari akun pasien yang sudah terhubung.
        // Pasien tidak lagi mengisi chat_id secara manual pada formulir.
        $telegramChatId = $patient->telegram_chat_id;

        // Create the queue record
        $queue = Queue::create([
            'queue_number' => $queueNumber,
            'daily_sequence' => $nextSequence,
            'queue_date' => $today,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'schedule_id' => $schedule->id,
            'patient_name' => $patient->name,
            'patient_phone' => $patient->phone,
            'ticket_token' => $ticketToken,
            'status' => 'menunggu',
            'is_walkin' => false,
            'priority_status' => $priorityStatus,
            'priority_reason' => $priorityReason,

            'telegram_chat_id' => $telegramChatId,
        ]);

        // Jadwalkan konfirmasi pendaftaran (dikirim scheduler bila kanal notifikasi tersedia).
        // Registrasi tetap berhasil meskipun Telegram belum terhubung.
        app(\App\Services\QueueReminderService::class)
            ->scheduleRegistrationConfirmation($queue);

        $logMsg = $hasPriorityRequest

            ? "Pasien {$patient->name} mendaftar antrian {$queue->queue_number} untuk {$doctor->name} dengan pengajuan prioritas ({$priorityReason}). Menunggu verifikasi admin."
            : "Pasien {$patient->name} mendaftar antrian {$queue->queue_number} untuk {$doctor->name}.";

        ActivityLog::record($patient, 'PATIENT_QUEUE_REGISTER', $logMsg);

        $successMsg = $hasPriorityRequest
            ? "Pendaftaran antrian berhasil! Nomor antrian Anda: {$queue->queue_number}. Pengajuan prioritas Anda sedang menunggu verifikasi petugas."
            : "Pendaftaran antrian berhasil! Nomor antrian Anda: {$queue->queue_number}";

        return redirect()->route('pasien.ticket', ['token' => $queue->ticket_token])
            ->with('success', $successMsg);
    }

    /**
     * View digital queue ticket
     */
    public function showTicket(string $token): View
    {
        $patient = Auth::user();

        $queue = Queue::with(['doctor', 'schedule'])
            ->where('ticket_token', $token)
            ->firstOrFail();

        // Strict authorization: Patient must only access their own ticket!
        if ($queue->patient_id !== $patient->id) {
            abort(403, 'Akses Ditolak: Anda tidak memiliki izin untuk melihat tiket antrian pasien lain.');
        }

        // Calculate queue status in real time using priority-aware model logic
        $patientsAhead = $queue->getPatientsAheadCount();
        $estimatedWaitTime = $queue->getEstimatedWaitTimeMinutes();

        // Generate QR Code SVG string for digital ticket
        $verificationUrl = route('ticket.verify', ['token' => $queue->ticket_token]);
        $qrCodeSvg = QrCode::size(160)
            ->color(30, 64, 175)
            ->generate($verificationUrl);

        return view('pasien.ticket', compact(
            'queue',
            'patientsAhead',
            'estimatedWaitTime',
            'qrCodeSvg',
            'verificationUrl'
        ));
    }

    /**
     * API for real-time ticket queue status monitoring
     */
    public function ticketStatusApi(string $token): JsonResponse
    {
        $patient = Auth::user();

        $queue = Queue::with('doctor')
            ->where('ticket_token', $token)
            ->firstOrFail();

        if ($queue->patient_id !== $patient->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $patientsAhead = $queue->getPatientsAheadCount();
        $estimatedWaitTime = $queue->getEstimatedWaitTimeMinutes();

        // Current called queue
        $currentCalled = Queue::whereDate('queue_date', $queue->queue_date)
            ->where('doctor_id', $queue->doctor_id)
            ->whereIn('status', ['dipanggil', 'sedang_diperiksa'])
            ->first();

        return response()->json([
            'status' => $queue->status,
            'status_label' => $queue->status_label,
            'priority_status' => $queue->priority_status,
            'priority_label' => $queue->priority_label,
            'priority_badge_class' => $queue->priority_badge_class,
            'patients_ahead' => $patientsAhead,
            'estimated_wait_time' => $estimatedWaitTime,
            'currently_called' => $currentCalled?->queue_number ?? '-',
        ]);
    }

    /**
     * View personal queue history
     */
    public function history(): View
    {
        $patient = Auth::user();

        $queues = Queue::with(['doctor', 'schedule'])
            ->where('patient_id', $patient->id)
            ->latest('queue_date')
            ->paginate(15);

        return view('pasien.history', compact('queues'));
    }

    /**
     * View profile page
     */
    public function profile(): View
    {
        /** @var \App\Models\User $patient */
        $patient = Auth::user();

        $telegramLinker = app(TelegramLinkService::class);
        $telegramAvailable = $telegramLinker->isAvailable();
        $telegramLinkUrl = $patient->hasTelegramLinked() ? null : $telegramLinker->startLinkUrl($patient);

        return view('pasien.profile', compact('patient', 'telegramAvailable', 'telegramLinkUrl'));
    }

    /**
     * Perbarui token penghubung Telegram (untuk memperbarui koneksi).
     */
    public function refreshTelegramLink(): RedirectResponse
    {
        /** @var \App\Models\User $patient */
        $patient = Auth::user();

        $linker = app(TelegramLinkService::class);

        if (! $linker->isAvailable()) {
            return back()->with('error', 'Integrasi Telegram belum aktif pada server. Hubungi petugas klinik.');
        }

        $linker->issueToken($patient);

        return back()->with('success', 'Tautan penghubung Telegram baru telah dibuat. Tautan berlaku sementara dan hanya dapat digunakan sekali.');
    }

    /**
     * Putuskan koneksi akun Telegram.
     */
    public function unlinkTelegram(): RedirectResponse
    {
        /** @var \App\Models\User $patient */
        $patient = Auth::user();

        app(TelegramLinkService::class)->unlink($patient);

        ActivityLog::record($patient, 'TELEGRAM_UNLINKED', "Pasien {$patient->name} memutuskan koneksi Telegram.");

        return back()->with('success', 'Koneksi Telegram berhasil diputus. Notifikasi Telegram tidak akan dikirim lagi.');
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $patient */
        $patient = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'current_password' => ['nullable', 'required_with:new_password'],
            'new_password' => ['nullable', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'phone.required' => 'Nomor telepon wajib diisi.',
            'new_password.min' => 'Kata sandi baru minimal 8 karakter.',
            'new_password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
        ]);

        $patient->name = $validated['name'];
        $patient->phone = $validated['phone'];

        if (! empty($validated['new_password'])) {
            if (! Hash::check($validated['current_password'], $patient->password)) {
                return back()->withErrors(['current_password' => 'Kata sandi saat ini tidak cocok.']);
            }
            $patient->password = Hash::make($validated['new_password']);
        }

        $patient->save();

        ActivityLog::record($patient, 'PROFILE_UPDATED', "Pasien {$patient->name} memperbarui profil akun.");

        return back()->with('success', 'Profil Anda berhasil diperbarui.');
    }
}
