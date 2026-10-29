@extends('layouts.app')

@section('title', 'Admin Dashboard')
@section('header-title', 'Dashboard Administrator')

@section('content')
@if(isset($pendingPriorityCount) && $pendingPriorityCount > 0)
    <div style="background: #FFFBEB; border: 1px solid #F59E0B; border-radius: var(--radius-md); padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-sm);">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 32px; height: 32px; border-radius: 50%; background: #F59E0B; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                !
            </div>
            <div>
                <strong style="color: #92400E; font-size: 0.95rem;">Perhatian: Terdapat {{ $pendingPriorityCount }} pengajuan prioritas pasien yang menunggu verifikasi!</strong>
                <div style="font-size: 0.8125rem; color: #B45309;">Segera lakukan verifikasi agar urutan panggilan antrian dapat diperbarui.</div>
            </div>
        </div>
        <a href="{{ route('admin.queues') }}?priority_status=perlu_verifikasi" class="btn btn-warning btn-sm">
            Verifikasi Sekarang &rarr;
        </a>
    </div>
@endif

{{-- Metrics Summary --}}
<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-icon blue">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        </div>
        <div>
            <div class="metric-value">{{ $totalPatients }}</div>
            <div class="metric-label">Total Pasien Terdaftar</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon purple">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
        </div>
        <div>
            <div class="metric-value">{{ $queuesTodayCount }}</div>
            <div class="metric-label">Total Antrian Hari Ini</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon yellow">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        </div>
        <div>
            <div class="metric-value">{{ $waitingCount }}</div>
            <div class="metric-label">Menunggu Giliran</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon green">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        </div>
        <div>
            <div class="metric-value">{{ $completedCount }}</div>
            <div class="metric-label">Pemeriksaan Selesai</div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
    {{-- Centralized Queue Snapshot --}}
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div>
                <h3 class="card-title">Antrian Terpusat Terkini (Hari Ini)</h3>
                <span class="card-subtitle">Urutan FIFO antrian online & walk-in terintegrasi</span>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <a href="{{ route('admin.walkin') }}" class="btn btn-primary btn-sm">
                    + Pasien Walk-in
                </a>
                <a href="{{ route('admin.queues') }}" class="btn btn-secondary btn-sm">
                    Lihat Semua
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>No. Antrian</th>
                        <th>Pasien</th>
                        <th>Tipe</th>
                        <th>Dokter Tujuan</th>
                        <th>Status</th>
                        <th>Waktu Daftar</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentQueues as $q)
                        <tr>
                            <td>
                                <strong style="color: var(--primary); font-size: 1rem;">{{ $q->queue_number }}</strong>
                            </td>
                            <td>
                                <div><strong>{{ $q->patient_name }}</strong></div>
                                <small class="text-muted">{{ $q->patient_phone ?? '-' }}</small>
                            </td>
                            <td>
                                @if($q->is_walkin)
                                    <span class="badge badge-gray">Walk-in</span>
                                @else
                                    <span class="badge badge-blue">Online</span>
                                @endif
                            </td>
                            <td>{{ $q->doctor->name ?? '-' }}</td>
                            <td>
                                <span class="badge {{ $q->status_badge_class }}">
                                    {{ $q->status_label }}
                                </span>
                            </td>
                            <td>{{ $q->created_at->format('H:i') }} WIB</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted" style="padding: 2rem;">
                                Belum ada antrian terdaftar untuk hari ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Doctor Schedule Status & Quick Actions --}}
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <h3 class="card-title">Dokter Aktif Hari Ini</h3>
                <a href="{{ route('admin.doctors') }}" class="btn btn-secondary btn-sm">Kelola</a>
            </div>
            <div>
                @forelse($activeDoctors as $doc)
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border);">
                        <div>
                            <div style="font-weight: 600; font-size: 0.875rem;">{{ $doc->name }}</div>
                            <div style="font-size: 0.75rem; color: var(--text-secondary);">{{ $doc->specialization ?? 'Dokter Umum' }}</div>
                        </div>
                        <span class="badge badge-green">Aktif</span>
                    </div>
                @empty
                    <div class="text-center text-muted" style="padding: 1rem;">
                        Belum ada dokter aktif.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <h3 class="card-title">Aktivitas Terkini</h3>
                <a href="{{ route('admin.logs') }}" class="btn btn-secondary btn-sm">Semua Log</a>
            </div>
            <div style="font-size: 0.8125rem;">
                @forelse($recentLogs as $log)
                    <div style="padding: 0.5rem 0; border-bottom: 1px solid var(--border);">
                        <div style="font-weight: 600; color: var(--navy);">{{ $log->action }}</div>
                        <div style="color: var(--text-secondary); margin: 2px 0;">{{ $log->description }}</div>
                        <div style="font-size: 0.7rem; color: var(--text-muted);">{{ $log->created_at->diffForHumans() }}</div>
                    </div>
                @empty
                    <div class="text-center text-muted" style="padding: 1rem;">Belum ada riwayat aktivitas.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
