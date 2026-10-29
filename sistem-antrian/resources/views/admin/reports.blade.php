@extends('layouts.app')

@section('title', 'Laporan & Ekspor Data')
@section('header-title', 'Laporan Kinerja & Antrian')

@section('content')
{{-- Filter Bar --}}
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Filter Rekapitulasi Pelayanan</h3>
            <span class="card-subtitle">Pilih rentang tanggal dan dokter untuk rekap laporan antrian</span>
        </div>
        <div>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-success btn-sm">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Ekspor ke Format CSV
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.reports') }}" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 120px; gap: 0.75rem; align-items: flex-end;">
        <div>
            <label class="form-label" style="font-size: 0.75rem;">Dari Tanggal</label>
            <input type="date" name="start_date" class="form-input" value="{{ $startDate }}">
        </div>
        <div>
            <label class="form-label" style="font-size: 0.75rem;">Sampai Tanggal</label>
            <input type="date" name="end_date" class="form-input" value="{{ $endDate }}">
        </div>
        <div>
            <label class="form-label" style="font-size: 0.75rem;">Dokter</label>
            <select name="doctor_id" class="form-select">
                <option value="">Semua Dokter</option>
                @foreach($doctors as $d)
                    <option value="{{ $d->id }}" {{ $doctorId == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-secondary btn-block">Tampilkan</button>
        </div>
    </form>
</div>

{{-- Metrics Recap --}}
<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-icon blue">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
        </div>
        <div>
            <div class="metric-value">{{ $totalRecords }}</div>
            <div class="metric-label">Total Antrian Masuk</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon green">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        </div>
        <div>
            <div class="metric-value">{{ $completedRecords }}</div>
            <div class="metric-label">Pemeriksaan Selesai</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon yellow">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
        </div>
        <div>
            <div class="metric-value">{{ $skippedRecords }}</div>
            <div class="metric-label">Pasien Dilewati</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon purple">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
        </div>
        <div>
            <div class="metric-value">{{ $walkinRecords }} / {{ $onlineRecords }}</div>
            <div class="metric-label">Walk-in vs Online</div>
        </div>
    </div>
</div>

{{-- Data Table --}}
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>No. Antrian</th>
                    <th>Nama Pasien</th>
                    <th>No. Telepon</th>
                    <th>Dokter</th>
                    <th>Kategori</th>
                    <th>Status</th>
                    <th>Catatan Medis</th>
                </tr>
            </thead>
            <tbody>
                @forelse($queues as $q)
                    <tr>
                        <td>{{ $q->queue_date->format('d/m/Y') }}</td>
                        <td><strong>{{ $q->queue_number }}</strong></td>
                        <td>{{ $q->patient_name }}</td>
                        <td>{{ $q->patient_phone ?? '-' }}</td>
                        <td>{{ $q->doctor->name ?? '-' }}</td>
                        <td>
                            @if($q->is_walkin)
                                <span class="badge badge-gray">Walk-in</span>
                            @else
                                <span class="badge badge-blue">Online</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $q->status_badge_class }}">
                                {{ $q->status_label }}
                            </span>
                        </td>
                        <td>
                            <small class="text-muted">{{ Str::limit($q->doctor_notes ?? '-', 40) }}</small>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted" style="padding: 2.5rem;">
                            Tidak ada rekaman antrian pada periode ini.
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
