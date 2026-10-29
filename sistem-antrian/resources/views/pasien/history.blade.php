@extends('layouts.app')

@section('title', 'Riwayat Antrian Saya')
@section('header-title', 'Riwayat Antrian Pasien')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Riwayat Pendaftaran & Kunjungan Anda</h3>
            <span class="card-subtitle">Semua nomor antrian yang pernah Anda daftarkan di klinik</span>
        </div>
        <a href="{{ route('pasien.dashboard') }}" class="btn btn-primary btn-sm">+ Ambil Antrian Baru</a>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Nomor Antrian</th>
                    <th>Dokter Pemeriksa</th>
                    <th>Poli / Ruangan</th>
                    <th>Status</th>
                    <th>Waktu Dilayani</th>
                    <th>Tiket Digital</th>
                </tr>
            </thead>
            <tbody>
                @forelse($queues as $q)
                    <tr>
                        <td>{{ $q->queue_date->format('d F Y') }}</td>
                        <td>
                            <strong style="color: var(--primary); font-size: 1.05rem;">{{ $q->queue_number }}</strong>
                        </td>
                        <td>
                            <strong>{{ $q->doctor->name ?? '-' }}</strong>
                            <small class="text-muted" style="display: block;">{{ $q->doctor->specialization ?? 'Dokter Umum' }}</small>
                        </td>
                        <td>{{ $q->schedule->room ?? 'Poli Umum' }}</td>
                        <td>
                            <span class="badge {{ $q->status_badge_class }}">
                                {{ $q->status_label }}
                            </span>
                        </td>
                        <td>
                            @if($q->exam_started_at)
                                {{ $q->exam_started_at->format('H:i') }} WIB
                            @elseif($q->called_at)
                                {{ $q->called_at->format('H:i') }} WIB
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('pasien.ticket', ['token' => $q->ticket_token]) }}" class="btn btn-secondary btn-sm">
                                Buka Tiket
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted" style="padding: 2.5rem;">
                            Belum ada riwayat antrian yang tercatat untuk akun Anda.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1.25rem;">
        {{ $queues->links() }}
    </div>
</div>
@endsection
