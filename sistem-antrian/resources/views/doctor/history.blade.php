@extends('layouts.app')

@section('title', 'Riwayat Pelayanan Pasien')
@section('header-title', 'Riwayat Pelayanan Medis')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Daftar Pasien Telah Dilayani</h3>
            <span class="card-subtitle">Rekam data pemeriksaan dan konsultasi pasien yang telah selesai atau dilewati</span>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="{{ route('doctor.dashboard') }}" class="btn btn-secondary btn-sm">Kembali ke Antrian</a>
        </div>
    </div>

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('doctor.history') }}" style="display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
        <input 
            type="text" 
            name="search" 
            class="form-input" 
            style="width: 240px;" 
            placeholder="Cari nama pasien..." 
            value="{{ $search }}"
        >
        <input 
            type="date" 
            name="date" 
            class="form-input" 
            style="width: 180px;" 
            value="{{ $date }}"
        >
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        @if($search || $date)
            <a href="{{ route('doctor.history') }}" class="btn btn-secondary btn-sm">Reset</a>
        @endif
    </form>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>No. Antrian</th>
                    <th>Nama Pasien</th>
                    <th>Tipe</th>
                    <th>Status</th>
                    <th>Mulai - Selesai</th>
                    <th>Catatan / Diagnosa / Resep</th>
                </tr>
            </thead>
            <tbody>
                @forelse($history as $h)
                    <tr>
                        <td>{{ $h->queue_date->format('d/m/Y') }}</td>
                        <td>
                            <strong style="color: var(--primary);">{{ $h->queue_number }}</strong>
                        </td>
                        <td>
                            <div style="font-weight: 600;">{{ $h->patient_name }}</div>
                            <small class="text-muted">{{ $h->patient_phone ?? '-' }}</small>
                        </td>
                        <td>
                            <span class="badge {{ $h->is_walkin ? 'badge-gray' : 'badge-blue' }}">
                                {{ $h->is_walkin ? 'Walk-in' : 'Online' }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $h->status_badge_class }}">
                                {{ $h->status_label }}
                            </span>
                        </td>
                        <td>
                            @if($h->exam_started_at && $h->exam_completed_at)
                                {{ $h->exam_started_at->format('H:i') }} - {{ $h->exam_completed_at->format('H:i') }} WIB
                            @elseif($h->called_at)
                                Dipanggil: {{ $h->called_at->format('H:i') }} WIB
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if($h->status === 'dilewati')
                                <span style="color: var(--danger-text); font-size: 0.8125rem;">
                                    Alasan: {{ $h->skip_reason }}
                                </span>
                            @elseif($h->doctor_notes)
                                <span style="font-size: 0.8125rem;">
                                    {{ $h->doctor_notes }}
                                </span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted" style="padding: 2.5rem;">
                            Belum ada riwayat pelayanan medis yang tercatat.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1.25rem;">
        {{ $history->links() }}
    </div>
</div>
@endsection
