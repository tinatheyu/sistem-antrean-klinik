<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Models\DoctorSchedule;
use App\Models\Queue;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminController extends Controller
{
    /**
     * Admin Dashboard
     */
    public function dashboard(): View
    {
        $today = today();

        $totalPatients = User::where('role', 'pasien')->count();
        $totalDoctors = User::where('role', 'dokter')->where('status', 'aktif')->count();

        $queuesTodayCount = Queue::whereDate('queue_date', $today)->count();
        $waitingCount = Queue::whereDate('queue_date', $today)->where('status', 'menunggu')->count();
        $calledCount = Queue::whereDate('queue_date', $today)->where('status', 'dipanggil')->count();
        $examiningCount = Queue::whereDate('queue_date', $today)->where('status', 'sedang_diperiksa')->count();
        $completedCount = Queue::whereDate('queue_date', $today)->where('status', 'selesai')->count();
        $skippedCount = Queue::whereDate('queue_date', $today)->where('status', 'dilewati')->count();
        $pendingPriorityCount = Queue::whereDate('queue_date', $today)->where('priority_status', 'perlu_verifikasi')->where('status', 'menunggu')->count();

        $recentQueues = Queue::with(['doctor', 'patient'])
            ->whereDate('queue_date', $today)
            ->orderByRaw("CASE WHEN priority_status = 'prioritas' THEN 1 ELSE 2 END ASC")
            ->orderBy('daily_sequence', 'asc')
            ->limit(10)
            ->get();

        $activeDoctors = User::where('role', 'dokter')
            ->where('status', 'aktif')
            ->with(['doctorSchedules'])
            ->get();

        $recentLogs = ActivityLog::latest()->limit(6)->get();

        return view('admin.dashboard', compact(
            'totalPatients',
            'totalDoctors',
            'queuesTodayCount',
            'waitingCount',
            'calledCount',
            'examiningCount',
            'completedCount',
            'skippedCount',
            'pendingPriorityCount',
            'recentQueues',
            'activeDoctors',
            'recentLogs'
        ));
    }

    /**
     * Manage patient records
     */
    public function patients(Request $request): View
    {
        $search = $request->query('search');

        $query = User::where('role', 'pasien')
            ->withCount('patientQueues')
            ->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $patients = $query->paginate(15)->withQueryString();

        return view('admin.patients', compact('patients', 'search'));
    }

    /**
     * Toggle patient account status
     */
    public function togglePatientStatus(User $user): RedirectResponse
    {
        if ($user->role !== 'pasien') {
            abort(403);
        }

        $user->status = $user->status === 'aktif' ? 'nonaktif' : 'aktif';
        $user->save();

        ActivityLog::record(Auth::user(), 'UPDATE_PATIENT_STATUS', "Admin mengubah status akun pasien {$user->name} menjadi {$user->status}.");

        return back()->with('success', "Status pasien {$user->name} berhasil diubah menjadi {$user->status}.");
    }

    /**
     * Register walk-in patient
     */
    public function showWalkInForm(): View
    {
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

        $doctors = User::where('role', 'dokter')
            ->where('status', 'aktif')
            ->with(['doctorSchedules' => function ($q) use ($currentDay) {
                $q->where('day_of_week', $currentDay)->where('is_active', true);
            }])
            ->get();

        return view('admin.walkin', compact('doctors', 'currentDay'));
    }

    /**
     * Process walk-in registration (centralized FIFO queue)
     */
    public function storeWalkIn(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'patient_name' => ['required', 'string', 'max:255'],
            'patient_phone' => ['nullable', 'string', 'max:20'],
            'doctor_id' => ['required', 'exists:users,id'],
            'schedule_id' => ['nullable', 'exists:doctor_schedules,id'],
            'priority_status' => ['nullable', 'in:normal,prioritas'],
            'priority_reason' => ['nullable', 'string', 'max:255'],
        ], [
            'patient_name.required' => 'Nama pasien walk-in wajib diisi.',
            'doctor_id.required' => 'Silakan pilih dokter tujuan.',
        ]);

        $today = today();
        $prefix = AppSetting::get('queue_prefix', 'A');

        // Centralized FIFO ordering: get next daily sequence for this doctor today
        $lastQueue = Queue::whereDate('queue_date', $today)
            ->where('doctor_id', $validated['doctor_id'])
            ->orderBy('daily_sequence', 'desc')
            ->first();

        $nextSequence = $lastQueue ? ($lastQueue->daily_sequence + 1) : 1;
        $queueNumber = sprintf('%s-%03d', $prefix, $nextSequence);
        $ticketToken = 'TK-'.strtoupper(Str::random(10));

        $priorityStatus = $validated['priority_status'] ?? 'normal';
        $priorityReason = $priorityStatus === 'prioritas' ? ($validated['priority_reason'] ?? 'Prioritas Langsung') : null;

        $queue = Queue::create([
            'queue_number' => $queueNumber,
            'daily_sequence' => $nextSequence,
            'queue_date' => $today,
            'patient_id' => null, // Walk-in without account
            'doctor_id' => $validated['doctor_id'],
            'schedule_id' => $validated['schedule_id'] ?? null,
            'patient_name' => $validated['patient_name'],
            'patient_phone' => $validated['patient_phone'],
            'ticket_token' => $ticketToken,
            'status' => 'menunggu',
            'is_walkin' => true,
            'priority_status' => $priorityStatus,
            'priority_reason' => $priorityReason,
            'priority_verified_by' => $priorityStatus === 'prioritas' ? Auth::id() : null,
            'priority_verified_at' => $priorityStatus === 'prioritas' ? now() : null,
        ]);

        ActivityLog::record(Auth::user(), 'REGISTER_WALKIN', "Admin mendaftarkan pasien walk-in: {$queue->patient_name} dengan nomor {$queue->queue_number} (Status: {$queue->priority_label}).");

        return redirect()->route('admin.queues')
            ->with('success', "Pasien walk-in berhasil didaftarkan! Nomor Antrian: {$queue->queue_number}");
    }

    /**
     * Manage centralized queues
     */
    public function queues(Request $request): View
    {
        $date = $request->query('date', today()->toDateString());
        $doctorId = $request->query('doctor_id');
        $status = $request->query('status');
        $priorityStatus = $request->query('priority_status');

        $query = Queue::with(['doctor', 'patient', 'schedule', 'verifier'])
            ->whereDate('queue_date', $date)
            ->orderByRaw("CASE WHEN priority_status = 'prioritas' THEN 1 ELSE 2 END ASC")
            ->orderBy('daily_sequence', 'asc');

        if ($doctorId) {
            $query->where('doctor_id', $doctorId);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($priorityStatus) {
            $query->where('priority_status', $priorityStatus);
        }

        $queues = $query->paginate(20)->withQueryString();

        // Pending priority verifications for the selected date
        $pendingVerifications = Queue::with(['doctor', 'patient', 'schedule'])
            ->whereDate('queue_date', $date)
            ->where('priority_status', 'perlu_verifikasi')
            ->where('status', 'menunggu')
            ->orderBy('daily_sequence', 'asc')
            ->get();

        $doctors = User::where('role', 'dokter')->where('status', 'aktif')->get();

        return view('admin.queues', compact('queues', 'doctors', 'date', 'doctorId', 'status', 'priorityStatus', 'pendingVerifications'));
    }

    /**
     * Verify priority status for an online queue application
     */
    public function verifyPriority(Request $request, Queue $queue): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:prioritas,normal'],
        ], [
            'decision.required' => 'Keputusan verifikasi wajib dipilih.',
            'decision.in' => 'Pilihan keputusan verifikasi tidak valid.',
        ]);

        // Validation Rule 1: Restrict decisions to queues with status 'perlu_verifikasi'
        if ($queue->priority_status !== 'perlu_verifikasi') {
            return back()->with('error', "Antrian {$queue->queue_number} sudah berstatus {$queue->priority_label} dan tidak dalam status Perlu Verifikasi.");
        }

        // Validation Rule 2: Cannot alter calling sequence if patient is already called, in exam, or completed
        if ($queue->status !== 'menunggu') {
            return back()->with('error', "Pengajuan prioritas tidak dapat diubah karena pasien antrian {$queue->queue_number} sudah dipanggil atau selesai ({$queue->status_label}).");
        }

        $oldPriority = $queue->priority_status;
        $decision = $validated['decision'];

        $queue->priority_status = $decision;
        $queue->priority_verified_by = Auth::id();
        $queue->priority_verified_at = now();
        $queue->save();

        $decisionLabel = $decision === 'prioritas' ? 'Prioritas' : 'Normal';

        ActivityLog::record(
            Auth::user(),
            'VERIFY_PRIORITY',
            'Admin '.Auth::user()->name." memverifikasi status antrian {$queue->queue_number} ({$queue->patient_name}) dari {$oldPriority} menjadi {$decisionLabel}."
        );

        return back()->with('success', "Keputusan prioritas disimpan! Antrian {$queue->queue_number} ({$queue->patient_name}) kini berstatus {$decisionLabel}. Urutan panggilan telah disesuaikan.");
    }

    /**
     * Cancel or update queue status by admin
     */
    public function updateQueueStatus(Request $request, Queue $queue): RedirectResponse
    {
        $request->validate([
            'status' => ['required', 'in:menunggu,dipanggil,sedang_diperiksa,selesai,dilewati,dibatalkan'],
        ]);

        $oldStatus = $queue->status;
        $queue->status = $request->status;
        $queue->save();

        ActivityLog::record(Auth::user(), 'UPDATE_QUEUE', "Admin mengubah status antrian {$queue->queue_number} dari {$oldStatus} ke {$queue->status}.");

        return back()->with('success', "Status antrian {$queue->queue_number} berhasil diperbarui.");
    }

    /**
     * Manage doctor schedules
     */
    public function schedules(): View
    {
        $doctors = User::where('role', 'dokter')->where('status', 'aktif')->get();
        $schedules = DoctorSchedule::with('doctor')->orderBy('doctor_id')->get();

        return view('admin.schedules', compact('doctors', 'schedules'));
    }

    /**
     * Store new doctor schedule
     */
    public function storeSchedule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'doctor_id' => ['required', 'exists:users,id'],
            'day_of_week' => ['required', 'in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu'],
            'session_name' => ['required', 'string', 'max:100'],
            'start_time' => ['required'],
            'end_time' => ['required', 'after:start_time'],
            'quota' => ['required', 'integer', 'min:1'],
            'room' => ['nullable', 'string', 'max:100'],
        ], [
            'doctor_id.required' => 'Silakan pilih dokter.',
            'day_of_week.required' => 'Hari praktik wajib dipilih.',
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
        ]);

        $schedule = DoctorSchedule::create($validated);

        ActivityLog::record(Auth::user(), 'CREATE_SCHEDULE', "Admin membuat jadwal baru untuk dokter {$schedule->doctor->name} pada hari {$schedule->day_of_week}.");

        return back()->with('success', 'Jadwal praktik dokter berhasil ditambahkan.');
    }

    /**
     * Toggle schedule active state
     */
    public function toggleSchedule(DoctorSchedule $schedule): RedirectResponse
    {
        $schedule->is_active = ! $schedule->is_active;
        $schedule->save();

        return back()->with('success', 'Status jadwal berhasil diperbarui.');
    }

    /**
     * Delete schedule
     */
    public function deleteSchedule(DoctorSchedule $schedule): RedirectResponse
    {
        $schedule->delete();

        return back()->with('success', 'Jadwal praktik berhasil dihapus.');
    }

    /**
     * Manage authorized Doctor accounts
     */
    public function doctors(): View
    {
        $doctors = User::where('role', 'dokter')
            ->withCount(['doctorSchedules', 'doctorQueues'])
            ->latest()
            ->paginate(15);

        return view('admin.doctors', compact('doctors'));
    }

    /**
     * Create new authorized Doctor account (Admin only!)
     */
    public function storeDoctor(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'specialization' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'name.required' => 'Nama dokter wajib diisi.',
            'email.unique' => 'Email ini sudah terdaftar.',
            'specialization.required' => 'Spesialisasi / Poli wajib diisi.',
        ]);

        $doctor = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'],
            'specialization' => $validated['specialization'],
            'password' => Hash::make($validated['password']),
            'role' => 'dokter', // Assigned strictly by admin
            'status' => 'aktif',
            'email_verified_at' => now(),
        ]);

        ActivityLog::record(Auth::user(), 'CREATE_DOCTOR', "Admin mendaftarkan akun dokter baru: {$doctor->name} ({$doctor->specialization}).");

        return back()->with('success', "Akun dokter {$doctor->name} berhasil dibuat!");
    }

    /**
     * Toggle doctor status
     */
    public function toggleDoctorStatus(User $doctor): RedirectResponse
    {
        if ($doctor->role !== 'dokter') {
            abort(403);
        }

        $doctor->status = $doctor->status === 'aktif' ? 'nonaktif' : 'aktif';
        $doctor->save();

        ActivityLog::record(Auth::user(), 'UPDATE_DOCTOR_STATUS', "Admin mengubah status dokter {$doctor->name} menjadi {$doctor->status}.");

        return back()->with('success', "Status dokter {$doctor->name} diubah menjadi {$doctor->status}.");
    }

    /**
     * Manage QR Codes and barcode records
     */
    public function qrcodes(Request $request): View
    {
        $token = $request->query('token');
        $searchedQueue = null;

        if ($token) {
            $searchedQueue = Queue::with(['doctor', 'patient', 'schedule'])
                ->where('ticket_token', trim($token))
                ->orWhere('queue_number', trim($token))
                ->first();
        }

        $recentTickets = Queue::with(['doctor', 'patient'])
            ->latest()
            ->limit(12)
            ->get();

        return view('admin.qrcodes', compact('searchedQueue', 'recentTickets', 'token'));
    }

    /**
     * View reports and export data
     */
    public function reports(Request $request): View|Response
    {
        $startDate = $request->query('start_date', today()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', today()->toDateString());
        $doctorId = $request->query('doctor_id');

        $query = Queue::with(['doctor', 'patient'])
            ->whereBetween('queue_date', [$startDate, $endDate]);

        if ($doctorId) {
            $query->where('doctor_id', $doctorId);
        }

        if ($request->query('export') === 'csv') {
            $records = $query->orderBy('queue_date', 'desc')->get();
            $filename = "laporan-antrian-{$startDate}-sd-{$endDate}.csv";

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ];

            $callback = function () use ($records) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Tanggal', 'Nomor Antrian', 'Nama Pasien', 'No Telepon', 'Dokter', 'Tipe', 'Status', 'Catatan Dokter']);

                foreach ($records as $row) {
                    fputcsv($file, [
                        $row->queue_date->format('Y-m-d'),
                        $row->queue_number,
                        $row->patient_name,
                        $row->patient_phone ?? '-',
                        $row->doctor->name ?? '-',
                        $row->is_walkin ? 'Walk-in' : 'Online',
                        $row->status,
                        $row->doctor_notes ?? '-',
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        $totalRecords = (clone $query)->count();
        $completedRecords = (clone $query)->where('status', 'selesai')->count();
        $skippedRecords = (clone $query)->where('status', 'dilewati')->count();
        $walkinRecords = (clone $query)->where('is_walkin', true)->count();
        $onlineRecords = (clone $query)->where('is_walkin', false)->count();

        $queues = $query->latest('queue_date')->paginate(20)->withQueryString();
        $doctors = User::where('role', 'dokter')->get();

        return view('admin.reports', compact(
            'queues',
            'doctors',
            'startDate',
            'endDate',
            'doctorId',
            'totalRecords',
            'completedRecords',
            'skippedRecords',
            'walkinRecords',
            'onlineRecords'
        ));
    }

    /**
     * Application settings
     */
    public function settings(): View
    {
        $settings = AppSetting::all()->keyBy('key');

        return view('admin.settings', compact('settings'));
    }

    /**
     * Update settings
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->except(['_token']);

        foreach ($data as $key => $value) {
            AppSetting::set($key, $value);
        }

        ActivityLog::record(Auth::user(), 'SETTINGS_UPDATED', 'Admin memperbarui pengaturan aplikasi.');

        return back()->with('success', 'Pengaturan aplikasi berhasil disimpan.');
    }

    /**
     * View activity logs
     */
    public function logs(Request $request): View
    {
        $action = $request->query('action');
        $role = $request->query('role');

        $query = ActivityLog::latest();

        if ($action) {
            $query->where('action', $action);
        }

        if ($role) {
            $query->where('role', $role);
        }

        $logs = $query->paginate(25)->withQueryString();

        return view('admin.logs', compact('logs', 'action', 'role'));
    }
}
