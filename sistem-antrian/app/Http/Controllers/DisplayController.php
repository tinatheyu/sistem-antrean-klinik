<?php

namespace App\Http\Controllers;

use App\Models\Queue;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DisplayController extends Controller
{
    /**
     * Public ticket verification page (when QR Code is scanned)
     */
    public function verifyTicket(string $token): View
    {
        $queue = Queue::with(['doctor', 'schedule'])
            ->where('ticket_token', $token)
            ->firstOrFail();

        $qrCodeSvg = QrCode::size(140)
            ->color(30, 64, 175)
            ->generate(route('ticket.verify', ['token' => $queue->ticket_token]));

        return view('public.ticket_verify', compact('queue', 'qrCodeSvg'));
    }

    /**
     * Centralized clinic queue display monitor (e.g. for waiting room TV)
     */
    public function monitor(): View
    {
        $today = today();

        $doctors = User::where('role', 'dokter')
            ->where('status', 'aktif')
            ->get();

        $activeCalls = Queue::with(['doctor', 'schedule'])
            ->whereDate('queue_date', $today)
            ->whereIn('status', ['dipanggil', 'sedang_diperiksa'])
            ->latest('updated_at')
            ->get();

        return view('public.monitor', compact('doctors', 'activeCalls'));
    }

    /**
     * API for live queue monitor updates
     */
    public function monitorApi(): JsonResponse
    {
        $today = today();

        $activeCalls = Queue::with(['doctor', 'schedule'])
            ->whereDate('queue_date', $today)
            ->whereIn('status', ['dipanggil', 'sedang_diperiksa'])
            ->latest('updated_at')
            ->get()
            ->map(function ($q) {
                $room = $q->schedule->room ?? 'Ruang Dokter';

                return [
                    'id' => $q->id,
                    'queue_number' => $q->queue_number,
                    'patient_name' => $q->patient_name,
                    'doctor_name' => $q->doctor->name ?? '-',
                    'room' => $room,
                    'status' => $q->status,
                    'status_label' => $q->status_label,
                    'priority_status' => $q->priority_status,
                    'priority_label' => $q->priority_label,
                    'recall_count' => $q->recall_count,
                    'tts_text' => "Nomor antrean {$q->queue_number}, silakan menuju {$room}.",
                    'called_at_timestamp' => $q->called_at ? $q->called_at->timestamp : $q->updated_at->timestamp,
                    'updated_at' => $q->updated_at->timestamp,
                ];
            });

        return response()->json([
            'active_calls' => $activeCalls,
            'timestamp' => now()->timestamp,
        ]);
    }
}
