@extends('layouts.app')

@section('title', 'Manajemen Antrian Terpusat')
@section('header-title', 'Antrian Terpusat (FIFO)')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Daftar Antrian Terintegrasi</h3>
            <span class="card-subtitle">Pengawasan dan kontrol antrian pendaftaran online dan pasien langsung (walk-in)</span>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="{{ route('admin.walkin') }}" class="btn btn-primary btn-sm">+ Antrian Walk-in</a>
        </div>
    </div>

    {{-- Pending Priority Verifications Alert Panel --}}
    @if(isset($pendingVerifications) && $pendingVerifications->isNotEmpty())
        <div style="background: #FFFBEB; border: 2px solid #F59E0B; border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-sm);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <div style="width: 28px; height: 28px; border-radius: 50%; background: #F59E0B; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem;">
                        !
                    </div>
                    <div>
                        <h4 style="font-size: 1rem; font-weight: 700; color: #92400E; margin: 0;">
                            Pengajuan Prioritas Memerlukan Verifikasi ({{ $pendingVerifications->count() }} Pasien)
                        </h4>
                        <span style="font-size: 0.8rem; color: #B45309;">
                            Pasien online di bawah ini mengajukan status antrean prioritas dan menunggu keputusan Admin sebelum dipanggil.
                        </span>
                    </div>
                </div>
            </div>

            <div class="table-responsive" style="background: #FFFFFF; border-radius: var(--radius-sm); border: 1px solid #FDE68A;">
                <table class="table" style="margin-bottom: 0; font-size: 0.85rem;">
                    <thead>
                        <tr style="background: #FEF3C7; color: #92400E;">
                            <th>No. Antrean</th>
                            <th>Nama Pasien</th>
                            <th>Dokter Tujuan</th>
                            <th>Asal Pendaftaran</th>
                            <th>Kategori / Alasan Prioritas</th>
                            <th style="text-align: right;">Aksi Keputusan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingVerifications as $pv)
                            <tr>
                                <td>
                                    <strong style="color: var(--primary); font-size: 1.05rem;">{{ $pv->queue_number }}</strong>
                                </td>
                                <td>
                                    <strong>{{ $pv->patient_name }}</strong>
                                    <div style="font-size: 0.75rem; color: var(--text-secondary);">Telp: {{ $pv->patient_phone ?? '-' }}</div>
                                </td>
                                <td>{{ $pv->doctor->name ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $pv->is_walkin ? 'badge-gray' : 'badge-blue' }}">
                                        {{ $pv->is_walkin ? 'Walk-in' : 'Online' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-yellow" style="font-size: 0.75rem;">
                                        {{ $pv->priority_reason ?? 'Pengajuan Prioritas' }}
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 0.35rem;">
                                        <form method="POST" action="{{ route('admin.queues.verify-priority', $pv->id) }}">
                                            @csrf
                                            <input type="hidden" name="decision" value="prioritas">
                                            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Setujui pasien {{ $pv->queue_number }} ({{ $pv->patient_name }}) sebagai Pasien Prioritas?')">
                                                ✓ Setujui Prioritas
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.queues.verify-priority', $pv->id) }}">
                                            @csrf
                                            <input type="hidden" name="decision" value="normal">
                                            <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Tetapkan pasien {{ $pv->queue_number }} sebagai Pasien Normal?')">
                                                ✕ Tetapkan Normal
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Filter Bar --}}
    <form method="GET" action="{{ route('admin.queues') }}" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)) 90px; gap: 0.75rem; margin-bottom: 1.5rem; align-items: flex-end;">
        <div>
            <label class="form-label" style="font-size: 0.75rem;">Tanggal</label>
            <input type="date" name="date" class="form-input" value="{{ $date }}">
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
            <label class="form-label" style="font-size: 0.75rem;">Status Pelayanan</label>
            <select name="status" class="form-select">
                <option value="">Semua Status</option>
                <option value="menunggu" {{ $status == 'menunggu' ? 'selected' : '' }}>Menunggu</option>
                <option value="dipanggil" {{ $status == 'dipanggil' ? 'selected' : '' }}>Dipanggil</option>
                <option value="sedang_diperiksa" {{ $status == 'sedang_diperiksa' ? 'selected' : '' }}>Sedang Diperiksa</option>
                <option value="selesai" {{ $status == 'selesai' ? 'selected' : '' }}>Selesai</option>
                <option value="dilewati" {{ $status == 'dilewati' ? 'selected' : '' }}>Dilewati</option>
                <option value="dibatalkan" {{ $status == 'dibatalkan' ? 'selected' : '' }}>Dibatalkan</option>
            </select>
        </div>
        <div>
            <label class="form-label" style="font-size: 0.75rem;">Status Prioritas</label>
            <select name="priority_status" class="form-select">
                <option value="">Semua Prioritas</option>
                <option value="prioritas" {{ ($priorityStatus ?? '') == 'prioritas' ? 'selected' : '' }}>Prioritas</option>
                <option value="perlu_verifikasi" {{ ($priorityStatus ?? '') == 'perlu_verifikasi' ? 'selected' : '' }}>Perlu Verifikasi</option>
                <option value="normal" {{ ($priorityStatus ?? '') == 'normal' ? 'selected' : '' }}>Normal</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-secondary btn-block">Filter</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Urutan Panggilan</th>
                    <th>Nomor</th>
                    <th>Pasien</th>
                    <th>Dokter</th>
                    <th>Asal</th>
                    <th>Prioritas</th>
                    <th>Status Layanan</th>
                    <th>Waktu Panggilan</th>
                    <th>Aksi Admin</th>
                </tr>
            </thead>
            <tbody>
                @forelse($queues as $q)
                    <tr>
                        <td>
                            <strong>#{{ $q->daily_sequence }}</strong>
                            @if($q->priority_status === 'prioritas')
                                <small style="display: block; color: var(--purple-text); font-weight: 700; font-size: 0.7rem;">Prioritas Atas</small>
                            @endif
                        </td>
                        <td>
                            <span style="font-size: 1.15rem; font-weight: 800; color: var(--primary);">
                                {{ $q->queue_number }}
                            </span>
                        </td>
                        <td>
                            <div style="font-weight: 600;">{{ $q->patient_name }}</div>
                            <small class="text-muted">{{ $q->patient_phone ?? '-' }}</small>
                            @if($q->telegram_chat_id)
                                <small style="display: block; color: #0284C7; font-size: 0.7rem;">Telegram: {{ $q->telegram_chat_id }}</small>
                            @endif
                        </td>
                        <td>{{ $q->doctor->name ?? '-' }}</td>
                        <td>
                            @if($q->is_walkin)
                                <span class="badge badge-gray">Walk-in</span>
                            @else
                                <span class="badge badge-blue">Online</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $q->priority_badge_class }}">
                                {{ $q->priority_label }}
                            </span>
                            @if($q->priority_reason)
                                <small style="display: block; font-size: 0.725rem; color: var(--text-secondary); margin-top: 2px;">
                                    {{ $q->priority_reason }}
                                </small>
                            @endif
                            @if($q->priority_verified_by && $q->verifier)
                                <small style="display: block; font-size: 0.675rem; color: var(--text-muted);">
                                    Oleh: {{ $q->verifier->name }} ({{ $q->priority_verified_at?->format('H:i') }})
                                </small>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $q->status_badge_class }}">
                                {{ $q->status_label }}
                            </span>
                            @if($q->recall_count > 0)
                                <small style="display: block; font-size: 0.7rem; color: var(--text-muted);">
                                    Dipanggil {{ $q->recall_count }}x
                                </small>
                            @endif
                        </td>
                        <td>
                            {{ $q->called_at ? $q->called_at->format('H:i') : '-' }}
                        </td>
                        <td>
                            {{-- Decision buttons if still perlu_verifikasi and waiting --}}
                            @if($q->priority_status === 'perlu_verifikasi' && $q->status === 'menunggu')
                                <div style="display: flex; gap: 0.25rem; margin-bottom: 0.4rem;">
                                    <form method="POST" action="{{ route('admin.queues.verify-priority', $q->id) }}">
                                        @csrf
                                        <input type="hidden" name="decision" value="prioritas">
                                        <button type="submit" class="btn btn-success btn-sm" style="padding: 0.2rem 0.45rem; font-size: 0.7rem;" title="Setujui sebagai Pasien Prioritas">
                                            ✓ Setujui
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.queues.verify-priority', $q->id) }}">
                                        @csrf
                                        <input type="hidden" name="decision" value="normal">
                                        <button type="submit" class="btn btn-secondary btn-sm" style="padding: 0.2rem 0.45rem; font-size: 0.7rem;" title="Tetapkan sebagai Pasien Normal">
                                            ✕ Normal
                                        </button>
                                    </form>
                                </div>
                            @endif

                            {{-- Status change dropdown --}}
                            <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                                <form method="POST" action="{{ route('admin.queues.status', $q->id) }}">
                                    @csrf
                                    <select name="status" class="form-select" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; width: auto;" onchange="this.form.submit()">
                                        <option value="menunggu" {{ $q->status == 'menunggu' ? 'selected' : '' }}>Menunggu</option>
                                        <option value="dipanggil" {{ $q->status == 'dipanggil' ? 'selected' : '' }}>Dipanggil</option>
                                        <option value="sedang_diperiksa" {{ $q->status == 'sedang_diperiksa' ? 'selected' : '' }}>Diperiksa</option>
                                        <option value="selesai" {{ $q->status == 'selesai' ? 'selected' : '' }}>Selesai</option>
                                        <option value="dilewati" {{ $q->status == 'dilewati' ? 'selected' : '' }}>Dilewati</option>
                                        <option value="dibatalkan" {{ $q->status == 'dibatalkan' ? 'selected' : '' }}>Batalkan</option>
                                    </select>
                                </form>
                                <a href="{{ route('ticket.verify', $q->ticket_token) }}" target="_blank" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" title="Lihat Tiket">
                                    Tiket
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted" style="padding: 2.5rem;">
                            Tidak ada antrian yang cocok dengan filter.
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
