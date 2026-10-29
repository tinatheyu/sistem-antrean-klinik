<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Queue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DoctorController extends Controller
{
    /**
     * Doctor Dashboard
     */
    public function dashboard(): View
    {
        $doctor = Auth::user();
        $today = today();

        // Queues for this doctor today (Priority first, then FIFO)
        $todayQueues = Queue::with(['patient', 'schedule'])
            ->whereDate('queue_date', $today)
            ->where('doctor_id', $doctor->id)
            ->orderedForCalling()
            ->get();

        // Current active queue (currently being called or examined)
        $currentQueue = Queue::with(['patient', 'schedule'])
            ->whereDate('queue_date', $today)
            ->where('doctor_id', $doctor->id)
            ->whereIn('status', ['dipanggil', 'sedang_diperiksa'])
            ->latest('updated_at')
            ->first();

        // Next waiting patient (Priority first, then FIFO)
        $nextQueue = Queue::with(['patient', 'schedule'])
            ->whereDate('queue_date', $today)
            ->where('doctor_id', $doctor->id)
            ->where('status', 'menunggu')
            ->orderedForCalling()
            ->first();

        // Metrics
        $waitingCount = $todayQueues->where('status', 'menunggu')->count();
        $examiningCount = $todayQueues->where('status', 'sedang_diperiksa')->count();
        $completedCount = $todayQueues->where('status', 'selesai')->count();
        $skippedCount = $todayQueues->where('status', 'dilewati')->count();

        return view('doctor.dashboard', compact(
            'doctor',
            'todayQueues',
            'currentQueue',
            'nextQueue',
            'waitingCount',
            'examiningCount',
            'completedCount',
            'skippedCount'
        ));
    }

    /**
     * Call the next eligible patient (Priority first, then FIFO)
     */
    public function callNext(): RedirectResponse
    {
        $doctor = Auth::user();
        $today = today();

        // Atomically check and call next to prevent race conditions and double calls
        return DB::transaction(function () use ($doctor, $today) {
            // Prevent double call if another patient is already in status 'dipanggil' or 'sedang_diperiksa'
            $activeCall = Queue::whereDate('queue_date', $today)
                ->where('doctor_id', $doctor->id)
                ->whereIn('status', ['dipanggil', 'sedang_diperiksa'])
                ->lockForUpdate()
                ->first();

            if ($activeCall) {
                if ($activeCall->status === 'sedang_diperiksa') {
                    return back()->with('error', 'Harap selesaikan pemeriksaan pasien saat ini sebelum memanggil pasien berikutnya.');
                }

                return back()->with('error', "Pasien {$activeCall->queue_number} ({$activeCall->patient_name}) sedang dalam status dipanggil. Mulai pemeriksaan atau lewati pasien terlebih dahulu.");
            }

            // Retrieve next waiting patient adhering strictly to Priority FIFO rules
            $nextQueue = Queue::with(['schedule', 'patient'])
                ->whereDate('queue_date', $today)
                ->where('doctor_id', $doctor->id)
                ->where('status', 'menunggu')
                ->orderedForCalling()
                ->lockForUpdate()
                ->first();

            if (! $nextQueue) {
                return back()->with('info', 'Tidak ada pasien dalam antrian yang sedang menunggu saat ini.');
            }

            $nextQueue->status = 'dipanggil';
            $nextQueue->called_at = now();
            $nextQueue->recall_count = 1;
            $nextQueue->save();

            $room = $nextQueue->schedule?->room ?? 'Ruang Pemeriksaan Dokter';
            $ttsText = "Nomor antrean {$nextQueue->queue_number}, silakan menuju {$room}.";

            ActivityLog::record(
                $doctor,
                'QUEUE_CALLED',
                "Dokter {$doctor->name} memanggil nomor antrian {$nextQueue->queue_number} ({$nextQueue->patient_name}) [{$nextQueue->priority_label}]."
            );

            // Flash announcement payload for browser TTS execution
            session()->flash('called_announcement', [
                'queue_number' => $nextQueue->queue_number,
                'patient_name' => $nextQueue->patient_name,
                'room' => $room,
                'tts_text' => $ttsText,
                'action' => 'call',
            ]);

            return back()->with('success', "Memanggil nomor antrian {$nextQueue->queue_number}: {$nextQueue->patient_name}!");
        });
    }

    /**
     * Recall the current queue number
     */
    public function recall(Queue $queue): RedirectResponse
    {
        $doctor = Auth::user();

        // Enforce ownership
        if ($queue->doctor_id !== $doctor->id) {
            abort(403);
        }

        if (! in_array($queue->status, ['dipanggil', 'sedang_diperiksa'])) {
            return back()->with('error', 'Hanya nomor antrian yang sedang dipanggil yang dapat dipanggil ulang.');
        }

        $queue->increment('recall_count');
        $queue->called_at = now();
        $queue->save();

        $room = $queue->schedule?->room ?? 'Ruang Pemeriksaan Dokter';
        $ttsText = "Panggilan ulang. Nomor antrean {$queue->queue_number}, silakan menuju {$room}.";

        ActivityLog::record(
            $doctor,
            'QUEUE_RECALLED',
            "Dokter {$doctor->name} memanggil ulang nomor antrian {$queue->queue_number} (Panggilan ke-{$queue->recall_count})."
        );

        // Flash announcement payload for browser TTS execution
        session()->flash('called_announcement', [
            'queue_number' => $queue->queue_number,
            'patient_name' => $queue->patient_name,
            'room' => $room,
            'tts_text' => $ttsText,
            'action' => 'recall',
        ]);

        return back()->with('success', "Nomor antrian {$queue->queue_number} dipanggil ulang (Panggilan ke-{$queue->recall_count}).");
    }

    /**
     * Start an examination
     */
    public function startExam(Queue $queue): RedirectResponse
    {
        $doctor = Auth::user();

        if ($queue->doctor_id !== $doctor->id) {
            abort(403);
        }

        $queue->status = 'sedang_diperiksa';
        $queue->exam_started_at = now();
        $queue->save();

        ActivityLog::record($doctor, 'EXAM_STARTED', "Dokter {$doctor->name} memulai pemeriksaan pasien {$queue->patient_name} ({$queue->queue_number}).");

        return back()->with('success', "Pemeriksaan untuk {$queue->patient_name} telah dimulai.");
    }

    /**
     * Complete an examination
     */
    public function completeExam(Request $request, Queue $queue): RedirectResponse
    {
        $doctor = Auth::user();

        if ($queue->doctor_id !== $doctor->id) {
            abort(403);
        }

        $request->validate([
            'doctor_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $queue->status = 'selesai';
        $queue->exam_completed_at = now();
        $queue->doctor_notes = $request->doctor_notes;
        $queue->save();

        ActivityLog::record($doctor, 'EXAM_COMPLETED', "Dokter {$doctor->name} menyelesaikan pemeriksaan pasien {$queue->patient_name} ({$queue->queue_number}).");

        return back()->with('success', "Pemeriksaan untuk {$queue->patient_name} berhasil diselesaikan.");
    }

    /**
     * Skip a patient with a recorded reason
     */
    public function skipPatient(Request $request, Queue $queue): RedirectResponse
    {
        $doctor = Auth::user();

        if ($queue->doctor_id !== $doctor->id) {
            abort(403);
        }

        $request->validate([
            'skip_reason' => ['required', 'string', 'max:500'],
        ], [
            'skip_reason.required' => 'Alasan pasien dilewati wajib diisi.',
        ]);

        $queue->status = 'dilewati';
        $queue->skip_reason = $request->skip_reason;
        $queue->save();

        ActivityLog::record($doctor, 'QUEUE_SKIPPED', "Dokter {$doctor->name} melewati pasien {$queue->patient_name} ({$queue->queue_number}). Alasan: {$request->skip_reason}");

        return back()->with('warning', "Pasien {$queue->patient_name} telah dilewati. Alasan tercatat.");
    }

    /**
     * View service history
     */
    public function serviceHistory(Request $request): View
    {
        $doctor = Auth::user();
        $search = $request->query('search');
        $date = $request->query('date');

        $query = Queue::with('patient')
            ->where('doctor_id', $doctor->id)
            ->whereIn('status', ['selesai', 'dilewati'])
            ->latest('queue_date');

        if ($search) {
            $query->where('patient_name', 'like', "%{$search}%");
        }

        if ($date) {
            $query->whereDate('queue_date', $date);
        }

        $history = $query->paginate(20)->withQueryString();

        return view('doctor.history', compact('history', 'search', 'date'));
    }

    /**
     * Polling API endpoint for real-time dashboard updates
     */
    public function liveQueueApi(): JsonResponse
    {
        $doctor = Auth::user();
        $today = today();

        $queues = Queue::whereDate('queue_date', $today)
            ->where('doctor_id', $doctor->id)
            ->orderBy('daily_sequence', 'asc')
            ->get();

        $current = $queues->whereIn('status', ['dipanggil', 'sedang_diperiksa'])->first();

        return response()->json([
            'current_number' => $current?->queue_number ?? '-',
            'current_patient' => $current?->patient_name ?? '-',
            'current_status' => $current?->status ?? 'tidak_ada',
            'waiting_count' => $queues->where('status', 'menunggu')->count(),
            'completed_count' => $queues->where('status', 'selesai')->count(),
        ]);
    }
}
