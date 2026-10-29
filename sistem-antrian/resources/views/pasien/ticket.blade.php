@extends('layouts.app')

@section('title', 'Tiket Antrian Digital - ' . $queue->queue_number)
@section('header-title', 'Tiket Antrian Digital')

@section('content')
<div style="max-width: 540px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <a href="{{ route('pasien.dashboard') }}" class="btn btn-secondary btn-sm">
            &larr; Kembali ke Dashboard
        </a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Cetak Tiket
        </button>
    </div>

    {{-- Digital Ticket Container --}}
    <div class="ticket-container" id="printableTicket">
        <div style="font-size: 0.8125rem; font-weight: 700; color: var(--navy); text-transform: uppercase; letter-spacing: 0.05em;">
            Klinik Pratama Sehat Bersama
        </div>
        <div style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.75rem;">
            Tiket Antrian Pelayanan Kesehatan Pasien
        </div>

        <div style="display: flex; justify-content: center; gap: 0.5rem; flex-wrap: wrap;">
            <span class="badge {{ $queue->status_badge_class }}" id="statusBadge" style="font-size: 0.8125rem; padding: 0.35rem 0.75rem;">
                {{ $queue->status_label }}
            </span>
            <span class="badge {{ $queue->priority_badge_class }}" id="priorityBadge" style="font-size: 0.8125rem; padding: 0.35rem 0.75rem;">
                {{ $queue->priority_label }}
            </span>
        </div>

        @if($queue->priority_status === 'perlu_verifikasi')
            <div style="margin: 0.75rem auto 0; max-width: 420px; background: #FFFBEB; border: 1px solid #FDE68A; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); font-size: 0.75rem; color: #92400E;">
                Pengajuan prioritas: <strong>{{ $queue->priority_reason }}</strong> sedang dalam proses verifikasi oleh petugas.
            </div>
        @elseif($queue->priority_status === 'prioritas')
            <div style="margin: 0.75rem auto 0; max-width: 420px; background: #F3E8FF; border: 1px solid #D8B4FE; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); font-size: 0.75rem; color: #6B21A8;">
                Status Prioritas Diverifikasi ({{ $queue->priority_reason ?? 'Prioritas Medis' }}).
            </div>
        @endif

        {{-- Big Queue Number --}}
        <div class="ticket-number">
            {{ $queue->queue_number }}
        </div>

        {{-- Live Status Counters --}}
        <div style="display: flex; justify-content: center; gap: 1.5rem; background: #F8FAFC; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 0.75rem 1rem; margin: 1rem 0;">
            <div>
                <div style="font-size: 1.5rem; font-weight: 800; color: var(--primary);" id="liveAhead">
                    {{ $patientsAhead }}
                </div>
                <div style="font-size: 0.7rem; color: var(--text-secondary); text-transform: uppercase;">Pasien di Depan</div>
            </div>
            <div style="width: 1px; background: var(--border);"></div>
            <div>
                <div style="font-size: 1.5rem; font-weight: 800; color: var(--navy);" id="liveWaitTime">
                    &plusmn;{{ $estimatedWaitTime }}
                </div>
                <div style="font-size: 0.7rem; color: var(--text-secondary); text-transform: uppercase;">Menit Estimasi</div>
            </div>
        </div>

        {{-- Ticket QR Code --}}
        <div class="ticket-qr">
            {!! $qrCodeSvg !!}
            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.35rem;">
                Token: <strong>{{ $queue->ticket_token }}</strong>
            </div>
        </div>

        <div style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 1rem;">
            Pindai kode QR di atas pada scanner pintu masuk klinik untuk konfirmasi kehadiran Anda.
        </div>

        {{-- Ticket Info Grid --}}
        <div class="ticket-info-grid">
            <div class="ticket-info-item">
                <label>Nama Pasien</label>
                <span>{{ $queue->patient_name }}</span>
            </div>
            <div class="ticket-info-item">
                <label>Tanggal Praktik</label>
                <span>{{ $queue->queue_date->format('d F Y') }}</span>
            </div>
            <div class="ticket-info-item">
                <label>Dokter Pemeriksa</label>
                <span>{{ $queue->doctor->name ?? '-' }}</span>
            </div>
            <div class="ticket-info-item">
                <label>Poli / Ruangan</label>
                <span>{{ $queue->schedule->room ?? 'Poli Umum' }}</span>
            </div>
        </div>

        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px dashed var(--border); font-size: 0.75rem; color: var(--text-muted);">
            Terima kasih atas kunjungan Anda. Harap mematuhi tata tertib dan protokol kesehatan klinik.
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
@media print {
    body * {
        visibility: hidden;
    }
    #printableTicket, #printableTicket * {
        visibility: visible;
    }
    #printableTicket {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        border: none;
        box-shadow: none;
    }
}
</style>
@endpush

@push('scripts')
<script>
    // Live monitoring ticket status
    setInterval(() => {
        fetch("{{ route('pasien.ticket.api', ['token' => $queue->ticket_token]) }}")
            .then(res => res.json())
            .then(data => {
                if (data.patients_ahead !== undefined) {
                    document.getElementById('liveAhead').innerText = data.patients_ahead;
                }
                if (data.estimated_wait_time !== undefined) {
                    document.getElementById('liveWaitTime').innerText = '±' + data.estimated_wait_time;
                }
                if (data.status_label) {
                    const badge = document.getElementById('statusBadge');
                    if (badge) badge.innerText = data.status_label;
                }
                if (data.priority_label) {
                    const pBadge = document.getElementById('priorityBadge');
                    if (pBadge) {
                        pBadge.innerText = data.priority_label;
                        if (data.priority_badge_class) {
                            pBadge.className = 'badge ' + data.priority_badge_class;
                        }
                    }
                }
                if (data.status === 'dipanggil') {
                    if (!window.ticketCallAnnounced) {
                        window.ticketCallAnnounced = true;
                        alert('PANGGILAN: Nomor antrian Anda {{ $queue->queue_number }} sedang dipanggil! Silakan segera memasuki ruang dokter.');
                    }
                }
            })
            .catch(() => {});
    }, 7000);
</script>
@endpush
