@extends('layouts.app')

@section('title', 'Kelola QR Code & Barcode')
@section('header-title', 'QR Code & Verifikasi Tiket Digital')

@section('content')
<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem;">
    {{-- Pencarian & Verifikasi Token --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Cek & Validasi QR Code</h3>
                <span class="card-subtitle">Pindai atau masukkan token tiket pasien</span>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.qrcodes') }}">
            <div class="form-group">
                <label for="token" class="form-label">Kode Token Tiket / Nomor Antrian</label>
                <input 
                    type="text" 
                    name="token" 
                    id="token" 
                    class="form-input" 
                    placeholder="Contoh: TK-XXXXXX atau A-002" 
                    value="{{ $token }}" 
                    required 
                    autofocus
                >
            </div>
            <button type="submit" class="btn btn-primary btn-block">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                Periksa Keabsahan Tiket
            </button>
        </form>

        @if($token && !$searchedQueue)
            <div class="alert alert-danger" style="margin-top: 1.25rem;">
                Tiket dengan kode token atau nomor "{{ $token }}" tidak ditemukan dalam sistem.
            </div>
        @endif

        @if($searchedQueue)
            <div style="margin-top: 1.5rem; padding: 1.25rem; border: 1px solid #BFDBFE; background-color: #EFF6FF; border-radius: var(--radius-md); text-align: center;">
                <span class="badge {{ $searchedQueue->status_badge_class }}">
                    {{ $searchedQueue->status_label }}
                </span>
                <div style="font-size: 2rem; font-weight: 800; color: var(--navy); margin: 0.5rem 0;">
                    {{ $searchedQueue->queue_number }}
                </div>
                <div style="font-weight: 600; font-size: 1rem;">{{ $searchedQueue->patient_name }}</div>
                <div style="font-size: 0.8125rem; color: var(--text-secondary); margin-bottom: 0.75rem;">
                    Dokter: {{ $searchedQueue->doctor->name ?? '-' }} ({{ $searchedQueue->doctor->specialization ?? '-' }})
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">
                    Token: <code>{{ $searchedQueue->ticket_token }}</code>
                </div>

                <div style="margin-top: 1rem;">
                    <a href="{{ route('ticket.verify', $searchedQueue->ticket_token) }}" target="_blank" class="btn btn-primary btn-sm">
                        Buka Halaman Tiket Lengkap
                    </a>
                </div>
            </div>
        @endif
    </div>

    {{-- Daftar QR Code / Tiket Terkini --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Daftar Tiket & Token QR Terkini</h3>
                <span class="card-subtitle">Semua tiket digital dengan enkripsi QR unik</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nomor</th>
                        <th>Token Tiket</th>
                        <th>Pasien</th>
                        <th>Dokter</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentTickets as $t)
                        <tr>
                            <td>
                                <strong style="color: var(--primary);">{{ $t->queue_number }}</strong>
                            </td>
                            <td>
                                <code style="font-size: 0.75rem; background: #F1F5F9; padding: 2px 6px; border-radius: 4px;">{{ $t->ticket_token }}</code>
                            </td>
                            <td>{{ $t->patient_name }}</td>
                            <td>{{ $t->doctor->name ?? '-' }}</td>
                            <td>
                                <span class="badge {{ $t->status_badge_class }}">
                                    {{ $t->status_label }}
                                </span>
                            </td>
                            <td>{{ $t->queue_date->format('d/m/Y') }}</td>
                            <td>
                                <a href="{{ route('ticket.verify', $t->ticket_token) }}" target="_blank" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">
                                    Buka QR
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted" style="padding: 2rem;">
                                Belum ada tiket tersimpan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
