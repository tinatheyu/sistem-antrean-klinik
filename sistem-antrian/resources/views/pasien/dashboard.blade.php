@extends('layouts.app')

@section('title', 'Dashboard Pasien')
@section('header-title', 'Portal Antrian Pasien')

@section('content')
{{-- Active Queue Banner (If registered today) --}}
@if($activeQueue)
    <div style="background: #FFFFFF; border: 2px solid var(--primary); border-radius: var(--radius-lg); padding: 1.75rem; margin-bottom: 1.5rem; box-shadow: var(--shadow-md);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span class="badge {{ $activeQueue->status_badge_class }}" style="font-size: 0.8125rem; padding: 0.35rem 0.75rem;">
                    Status: {{ $activeQueue->status_label }}
                </span>
                <span class="badge {{ $activeQueue->priority_badge_class }}" style="font-size: 0.8125rem; padding: 0.35rem 0.75rem; margin-left: 0.35rem;">
                    {{ $activeQueue->priority_label }}
                </span>
                <div style="font-size: 0.8125rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; margin-top: 0.75rem;">
                    Nomor Antrian Anda Hari Ini
                </div>
                <div style="font-size: 3.5rem; font-weight: 800; color: var(--navy); line-height: 1; margin: 0.25rem 0;">
                    {{ $activeQueue->queue_number }}
                </div>
                <div style="font-weight: 600; font-size: 1.1rem; color: var(--text-primary); margin-top: 0.25rem;">
                    {{ $activeQueue->doctor->name ?? 'Dokter Klinik' }}
                </div>
                <div style="font-size: 0.875rem; color: var(--text-secondary);">
                    {{ $activeQueue->doctor->specialization ?? 'Poli Umum' }} &bull; {{ $activeQueue->schedule->room ?? 'Ruang Pemeriksaan' }}
                </div>

                @if($activeQueue->priority_status === 'perlu_verifikasi')
                    <div style="margin-top: 0.75rem; background: #FFFBEB; border: 1px solid #FDE68A; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); font-size: 0.8rem; color: #92400E; display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        <span>Pengajuan prioritas: <strong>{{ $activeQueue->priority_reason }}</strong> (menunggu verifikasi petugas klinik).</span>
                    </div>
                @elseif($activeQueue->priority_status === 'prioritas')
                    <div style="margin-top: 0.75rem; background: #F3E8FF; border: 1px solid #D8B4FE; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); font-size: 0.8rem; color: #6B21A8; display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        <span>Status Prioritas Disetujui (Kategori: <strong>{{ $activeQueue->priority_reason ?? 'Prioritas Medis' }}</strong>).</span>
                    </div>
                @endif
            </div>

            <div style="display: flex; gap: 1.25rem; align-items: center; background: #F8FAFC; padding: 1rem 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border);">
                <div style="text-align: center;">
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--primary);" id="aheadCounter">
                        {{ $patientsAhead }}
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">Pasien di Depan Anda</div>
                </div>
                <div style="width: 1px; height: 40px; background: var(--border);"></div>
                <div style="text-align: center;">
                    <div style="font-size: 1.75rem; font-weight: 800; color: var(--navy);" id="waitTimeCounter">
                        &plusmn;{{ $estimatedWaitTime }}
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">Menit Estimasi</div>
                </div>
            </div>
        </div>

        <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px dashed var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
            <div style="font-size: 0.8125rem; color: var(--text-secondary);">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 4px;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                Waktu pendaftaran: {{ $activeQueue->created_at->format('H:i') }} WIB &bull; Sistem akan otomatis memperbarui status antrian Anda secara berkala.
            </div>
            <div>
                <a href="{{ route('pasien.ticket', ['token' => $activeQueue->ticket_token]) }}" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    Buka Tiket & Kode QR
                </a>
            </div>
        </div>
    </div>
@endif

{{-- Status Notifikasi Telegram --}}
@if($patient->hasTelegramLinked())
    <div style="background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: var(--radius-md); padding: 0.85rem 1rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.6rem; font-size: 0.85rem; color: #065F46;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        <span>Notifikasi Telegram <strong>aktif</strong>{{ $patient->telegram_username ? ' ('.$patient->telegram_username.')' : '' }}. Konfirmasi pendaftaran dan pengingat antrean akan dikirim ke chat Telegram Anda.</span>
    </div>
@elseif($telegramAvailable && $telegramLinkUrl)
    <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: var(--radius-md); padding: 0.85rem 1rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; font-size: 0.85rem; color: #1E40AF;">
        <span style="display: flex; align-items: center; gap: 0.5rem;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"></path><path d="M22 2 11 13"></path></svg>
            Notifikasi Telegram belum aktif. Hubungkan akun agar konfirmasi dan pengingat antrean dikirim otomatis.
        </span>
        <a href="{{ $telegramLinkUrl }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm">Hubungkan Telegram</a>
    </div>
@else
    <div style="background: #F8FAFC; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 0.85rem 1rem; margin-bottom: 1.5rem; font-size: 0.85rem; color: var(--text-secondary);">
        Notifikasi Telegram belum tersedia pada server ini. Petugas klinik akan tetap memantau antrean Anda.
    </div>
@endif

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
    {{-- Form Ambil Antrian Sesi Praktik Hari Ini --}}
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div>
                <h3 class="card-title">Daftar Antrian Praktik Dokter</h3>
                <span class="card-subtitle">Ambil nomor antrian digital untuk sesi hari {{ $currentDay }}</span>
            </div>
        </div>

        @if($availableSchedules->isNotEmpty())
            <form method="POST" action="{{ route('pasien.register-queue') }}">
                @csrf

                <div class="form-group">
                    <label for="schedule_id" class="form-label">
                        Pilih Jadwal Dokter Hari Ini ({{ $currentDay }}) <span class="required">*</span>
                    </label>
                    <select name="schedule_id" id="schedule_id" class="form-select @error('schedule_id') is-invalid @enderror" required>
                        <option value="">-- Pilih Dokter & Sesi --</option>
                        @foreach($availableSchedules as $sch)
                            <option value="{{ $sch->id }}">
                                {{ $sch->doctor->name }} &bull; {{ $sch->doctor->specialization ?? 'Poli Umum' }} ({{ $sch->session_name }} : {{ substr($sch->start_time, 0, 5) }} - {{ substr($sch->end_time, 0, 5) }} WIB)
                            </option>
                        @endforeach
                    </select>
                    @error('schedule_id')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

                {{-- Priority Request Card --}}
                <div style="background: #F8FAFC; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 0.85rem; margin-bottom: 1.25rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600; font-size: 0.875rem; color: var(--navy); margin-bottom: 0;">
                        <input type="checkbox" name="request_priority" id="requestPriorityCheck" value="1" onchange="togglePrioritySection(this.checked)">
                        <span>Ajukan Antrian Prioritas</span>
                    </label>
                    <p style="font-size: 0.75rem; color: var(--text-secondary); margin: 0.25rem 0 0 1.5rem;">
                        Pengajuan prioritas khusus pasien Lansia (≥ 60 tahun), Ibu Hamil, Balita, atau Penyandang Disabilitas. Status awal adalah <em>Perlu Verifikasi</em> dan diverifikasi petugas Admin.
                    </p>

                    <div id="priorityReasonGroup" style="display: none; margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px dashed #CBD5E1;">
                        <label for="priority_reason" class="form-label" style="font-size: 0.8rem;">
                            Kategori / Alasan Prioritas <span class="required">*</span>
                        </label>
                        <select name="priority_reason" id="priority_reason" class="form-select" style="font-size: 0.85rem;">
                            <option value="">-- Pilih Alasan Pengajuan Prioritas --</option>
                            <option value="Lansia (Usia 60 tahun ke atas)">Lansia (Usia 60 tahun ke atas)</option>
                            <option value="Ibu Hamil / Membawa Balita">Ibu Hamil / Membawa Balita</option>
                            <option value="Penyandang Disabilitas">Penyandang Disabilitas / Kebutuhan Khusus</option>
                            <option value="Kondisi Darurat Tertentu / Membutuhkan Penanganan Segera">Kondisi Darurat Tertentu / Butuh Penanganan Segera</option>
                        </select>
                    </div>
                </div>

                <div style="background-color: #F8FAFC; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 0.85rem; font-size: 0.8125rem; color: var(--text-secondary); margin-bottom: 1.25rem;">
                    <strong>Ketentuan:</strong>
                    <ul style="margin-left: 1.25rem; margin-top: 0.25rem;">
                        <li>Nomor antrian berlaku untuk hari ini.</li>
                        <li>Pastikan tiba di klinik sebelum perkiraan giliran Anda.</li>
                        <li>Satu akun pasien hanya dapat memiliki 1 antrian aktif per dokter per hari.</li>
                    </ul>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Ambil Nomor Antrian Sekarang
                </button>
            </form>
        @else
            <div style="text-align: center; padding: 2rem 1rem; color: var(--text-muted);">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 0.5rem;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <div style="font-weight: 600; font-size: 1rem; color: var(--navy);">Tidak Ada Jadwal Praktik Hari Ini</div>
                <p style="font-size: 0.8125rem; margin-top: 0.25rem;">Silakan periksa tabel jadwal mingguan di bawah untuk melihat jadwal hari lainnya.</p>
            </div>
        @endif
    </div>

    {{-- Profil Pasien Singkat --}}
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div>
                <h3 class="card-title">Profil Pasien</h3>
                <span class="card-subtitle">Data identitas Anda dalam rekam antrian</span>
            </div>
            <a href="{{ route('pasien.profile') }}" class="btn btn-secondary btn-sm">Edit Profil</a>
        </div>

        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.25rem;">
            <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--navy); color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700;">
                {{ strtoupper(substr($patient->name, 0, 1)) }}
            </div>
            <div>
                <div style="font-weight: 700; font-size: 1.15rem; color: var(--text-primary);">{{ $patient->name }}</div>
                <div style="font-size: 0.8125rem; color: var(--text-secondary);">Pasien Terverifikasi &bull; {{ $patient->email }}</div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; background: #F8FAFC; padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border);">
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">No. Telepon</div>
                <div style="font-weight: 600; font-size: 0.875rem;">{{ $patient->phone ?? '-' }}</div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Status Akun</div>
                <div style="font-weight: 600; font-size: 0.875rem; color: var(--success);">{{ ucfirst($patient->status) }}</div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Terdaftar Pada</div>
                <div style="font-weight: 600; font-size: 0.875rem;">{{ $patient->created_at->format('d M Y') }}</div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Total Kunjungan</div>
                <div style="font-weight: 600; font-size: 0.875rem;">{{ $history->count() }} Kali</div>
            </div>
        </div>
    </div>
</div>

{{-- Jadwal Praktik Mingguan --}}
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Jadwal Praktik Dokter Seluruh Hari</h3>
            <span class="card-subtitle">Informasi waktu praktik poliklinik spesialis dan umum</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Hari</th>
                    <th>Dokter</th>
                    <th>Poli / Spesialisasi</th>
                    <th>Sesi & Jam Praktik</th>
                    <th>Ruang</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($allSchedules as $sch)
                    <tr @if($sch->day_of_week === $currentDay) style="background:#EFF6FF;" @endif>
                        <td>
                            <strong @if($sch->day_of_week === $currentDay) style="color: var(--primary);" @endif>
                                {{ $sch->day_of_week }}
                                @if($sch->day_of_week === $currentDay)
                                    <span class="badge badge-blue" style="font-size: 0.65rem; margin-left: 4px;">Hari Ini</span>
                                @endif
                            </strong>
                        </td>
                        <td><strong>{{ $sch->doctor->name ?? '-' }}</strong></td>
                        <td>{{ $sch->doctor->specialization ?? 'Dokter Umum' }}</td>
                        <td>{{ $sch->session_name }} ({{ $sch->formatted_time }})</td>
                        <td>{{ $sch->room ?? '-' }}</td>
                        <td><span class="badge badge-green">Buka</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Personal Queue History Snapshot --}}
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Riwayat Antrian Terakhir Saya</h3>
            <span class="card-subtitle">Catatan nomor antrian yang pernah Anda daftarkan</span>
        </div>
        <a href="{{ route('pasien.history') }}" class="btn btn-secondary btn-sm">Lihat Semua Riwayat</a>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Nomor Antrian</th>
                    <th>Dokter</th>
                    <th>Status</th>
                    <th>Tiket Digital</th>
                </tr>
            </thead>
            <tbody>
                @forelse($history as $h)
                    <tr>
                        <td>{{ $h->queue_date->format('d/m/Y') }}</td>
                        <td><strong style="color: var(--primary);">{{ $h->queue_number }}</strong></td>
                        <td>{{ $h->doctor->name ?? '-' }}</td>
                        <td>
                            <span class="badge {{ $h->status_badge_class }}">
                                {{ $h->status_label }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('pasien.ticket', ['token' => $h->ticket_token]) }}" class="btn btn-secondary btn-sm">
                                Buka Tiket
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted" style="padding: 2rem;">
                            Anda belum pernah mendaftarkan nomor antrian.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function togglePrioritySection(isChecked) {
        const group = document.getElementById('priorityReasonGroup');
        const reasonSelect = document.getElementById('priority_reason');
        if (isChecked) {
            group.style.display = 'block';
            reasonSelect.required = true;
        } else {
            group.style.display = 'none';
            reasonSelect.required = false;
            reasonSelect.value = '';
        }
    }
</script>
@if($activeQueue)
<script>
    // Real-time polling for patient's active ticket
    setInterval(() => {
        fetch("{{ route('pasien.ticket.api', ['token' => $activeQueue->ticket_token]) }}")
            .then(res => res.json())
            .then(data => {
                if (data.patients_ahead !== undefined) {
                    const counter = document.getElementById('aheadCounter');
                    if (counter) counter.innerText = data.patients_ahead;
                }
                if (data.estimated_wait_time !== undefined) {
                    const wait = document.getElementById('waitTimeCounter');
                    if (wait) wait.innerText = '±' + data.estimated_wait_time;
                }
                if (data.status === 'dipanggil') {
                    // Alert notification
                    if (!window.hasAlertedCall) {
                        window.hasAlertedCall = true;
                        alert('PERHATIAN: Nomor antrian Anda sedang dipanggil! Silakan segera menuju ke ruang dokter.');
                        window.location.reload();
                    }
                }
            })
            .catch(() => {});
    }, 8000);
</script>
@endif
@endpush
