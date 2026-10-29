@extends('layouts.app')

@section('title', 'Dashboard Dokter')
@section('header-title', 'Pemeriksaan & Pemanggilan Antrian')

@section('content')
{{-- Announcement Alert Banner (TTS Trigger) --}}
@if(session('called_announcement'))
    <div id="ttsNotice" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: #FFFFFF; padding: 1rem 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-sm);">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 38px; height: 38px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                📢
            </div>
            <div>
                <strong style="font-size: 1.05rem;">
                    {{ session('called_announcement.action') === 'recall' ? 'Panggilan Ulang' : 'Panggilan Pasien' }}: Nomor {{ session('called_announcement.queue_number') }}
                </strong>
                <div style="font-size: 0.85rem; opacity: 0.95;">
                    {{ session('called_announcement.patient_name') }} &bull; {{ session('called_announcement.tts_text') }}
                </div>
            </div>
        </div>
        <button type="button" class="btn btn-sm" onclick="speakAnnouncement('{{ session('called_announcement.tts_text') }}')" style="background: rgba(255,255,255,0.25); color: #FFFFFF; border: 1px solid rgba(255,255,255,0.4);">
            🔊 Ulangi Suara TTS
        </button>
    </div>
@endif

{{-- Doctor Info Banner --}}
<div style="background: linear-gradient(135deg, #1E40AF 0%, #2563EB 100%); border-radius: var(--radius-md); padding: 1.5rem; color: #FFFFFF; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-md);">
    <div>
        <div style="font-size: 0.8125rem; opacity: 0.9; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Ruang Praktik Dokter</div>
        <h2 style="font-size: 1.5rem; font-weight: 700; margin: 0.25rem 0;">{{ $doctor->name }}</h2>
        <div style="font-size: 0.925rem; opacity: 0.95;">
            {{ $doctor->specialization ?? 'Dokter Umum' }} &bull; Hari Ini: {{ now()->translatedFormat('l, d F Y') }}
        </div>
    </div>
    <div style="text-align: right;">
        <span class="badge badge-green" style="font-size: 0.85rem; padding: 0.4rem 0.85rem; background: rgba(255,255,255,0.2); color: #FFFFFF; border: 1px solid rgba(255,255,255,0.3);">
            Sesi Praktik Aktif
        </span>
    </div>
</div>

{{-- Counters --}}
<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-icon yellow">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        </div>
        <div>
            <div class="metric-value">{{ $waitingCount }}</div>
            <div class="metric-label">Pasien Menunggu</div>
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-icon blue">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polygon points="10 8 16 12 10 16 10 8"></polygon></svg>
        </div>
        <div>
            <div class="metric-value">{{ $currentQueue ? 1 : 0 }}</div>
            <div class="metric-label">Sedang Dilayani</div>
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

    <div class="metric-card">
        <div class="metric-icon red">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
        </div>
        <div>
            <div class="metric-value">{{ $skippedCount }}</div>
            <div class="metric-label">Pasien Dilewati</div>
        </div>
    </div>
</div>

{{-- Main Control Console --}}
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
    {{-- Active Call Box --}}
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div>
                <h3 class="card-title">Pasien Dalam Pelayanan</h3>
                <span class="card-subtitle">Nomor antrian yang sedang dipanggil atau diperiksa</span>
            </div>
            @if($currentQueue)
                <div style="display: flex; gap: 0.35rem; align-items: center;">
                    <span class="badge {{ $currentQueue->status_badge_class }}">
                        {{ $currentQueue->status_label }}
                    </span>
                    <span class="badge {{ $currentQueue->priority_badge_class }}">
                        {{ $currentQueue->priority_label }}
                    </span>
                </div>
            @endif
        </div>

        @if($currentQueue)
            <div style="text-align: center; padding: 1rem 0;">
                <div style="font-size: 0.8125rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">
                    Nomor Antrian
                </div>
                <div style="font-size: 3.75rem; font-weight: 800; color: var(--navy); line-height: 1.1; margin: 0.25rem 0;">
                    {{ $currentQueue->queue_number }}
                </div>
                <div style="font-size: 1.25rem; font-weight: 700; color: var(--text-primary);">
                    {{ $currentQueue->patient_name }}
                </div>
                <div style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.25rem;">
                    {{ $currentQueue->is_walkin ? 'Pasien Walk-in (Langsung)' : 'Pasien Pendaftaran Online' }}
                    @if($currentQueue->patient_phone)
                        &bull; {{ $currentQueue->patient_phone }}
                    @endif
                </div>

                @if($currentQueue->recall_count > 0)
                    <div style="margin-top: 0.5rem; font-size: 0.8125rem; color: var(--warning-text); font-weight: 600;">
                        Telah dipanggil sebanyak {{ $currentQueue->recall_count }} kali
                    </div>
                @endif
            </div>

            <div style="border-top: 1px solid var(--border); padding-top: 1.25rem; margin-top: 1rem;">
                {{-- Action 1: Recall --}}
                <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                    <form method="POST" action="{{ route('doctor.recall', $currentQueue->id) }}" style="flex: 1;">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-block">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 4v6h6"></path><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                            Panggil Ulang (Recall)
                        </button>
                    </form>

                    {{-- Action 2: Start Examination (if only 'dipanggil') --}}
                    @if($currentQueue->status === 'dipanggil')
                        <form method="POST" action="{{ route('doctor.start', $currentQueue->id) }}" style="flex: 1;">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-block">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polygon points="10 8 16 12 10 16 10 8"></polygon></svg>
                                Mulai Pemeriksaan
                            </button>
                        </form>
                    @endif
                </div>

                {{-- Complete Examination Form --}}
                @if($currentQueue->status === 'sedang_diperiksa')
                    <form method="POST" action="{{ route('doctor.complete', $currentQueue->id) }}" style="margin-bottom: 1rem;">
                        @csrf
                        <div class="form-group">
                            <label for="doctor_notes" class="form-label">Catatan Medis / Resep Obat / Rekomendasi</label>
                            <textarea 
                                name="doctor_notes" 
                                id="doctor_notes" 
                                rows="3" 
                                class="form-textarea" 
                                placeholder="Tuliskan catatan hasil pemeriksaan atau saran obat di sini..."
                            ></textarea>
                        </div>
                        <button type="submit" class="btn btn-success btn-block">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            Selesaikan Pemeriksaan
                        </button>
                    </form>
                @endif

                {{-- Skip Patient Button --}}
                <div style="text-align: right;">
                    <button type="button" class="btn btn-danger btn-sm" onclick="openSkipModal({{ $currentQueue->id }}, '{{ $currentQueue->queue_number }}', '{{ $currentQueue->patient_name }}')">
                        Lewati Pasien (Tidak Hadir)
                    </button>
                </div>
            </div>

        @else
            <div style="text-align: center; padding: 3rem 1rem;">
                <div style="width: 60px; height: 60px; border-radius: 50%; background: #EFF6FF; color: var(--primary); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
                </div>
                <h4 style="font-size: 1.15rem; color: var(--navy); margin-bottom: 0.35rem;">Belum Ada Pasien Yang Dipanggil</h4>
                <p style="font-size: 0.875rem; color: var(--text-secondary); max-width: 320px; margin: 0 auto 1.5rem;">
                    Klik tombol di bawah untuk memanggil pasien berikutnya sesuai urutan antrian FIFO.
                </p>

                <form method="POST" action="{{ route('doctor.call-next') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-lg" {{ $waitingCount == 0 ? 'disabled' : '' }}>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                        Panggil Pasien Berikutnya
                    </button>
                </form>
            </div>
        @endif
    </div>

    {{-- Next Eligible Patient Card & Quick Call --}}
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div>
                <h3 class="card-title">Pasien Giliran Berikutnya (Prioritas FIFO)</h3>
                <span class="card-subtitle">Ditentukan otomatis oleh sistem sesuai aturan prioritas</span>
            </div>
            @if($nextQueue)
                <div style="display: flex; gap: 0.35rem; align-items: center;">
                    <span class="badge badge-yellow">Menunggu</span>
                    <span class="badge {{ $nextQueue->priority_badge_class }}">
                        {{ $nextQueue->priority_label }}
                    </span>
                </div>
            @endif
        </div>

        @if($nextQueue)
            <div style="padding: 1rem 0;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Nomor Berikutnya</div>
                        <div style="font-size: 2.25rem; font-weight: 800; color: var(--primary);">
                            {{ $nextQueue->queue_number }}
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span class="badge {{ $nextQueue->is_walkin ? 'badge-gray' : 'badge-blue' }}">
                            {{ $nextQueue->is_walkin ? 'Walk-in' : 'Online' }}
                        </span>
                        <div style="font-size: 0.8125rem; color: var(--text-secondary); margin-top: 0.25rem;">
                            Terdaftar: {{ $nextQueue->created_at->format('H:i') }} WIB
                        </div>
                    </div>
                </div>

                <div style="background-color: #F8FAFC; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 1rem; margin-bottom: 1.5rem;">
                    <div style="font-weight: 700; font-size: 1.1rem; color: var(--text-primary);">
                        {{ $nextQueue->patient_name }}
                    </div>
                    <div style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.25rem;">
                        No. Telepon: {{ $nextQueue->patient_phone ?? '-' }}
                    </div>
                    @if($nextQueue->priority_status === 'prioritas')
                        <div style="font-size: 0.8rem; color: #6B21A8; font-weight: 600; margin-top: 0.5rem; display: flex; align-items: center; gap: 0.35rem;">
                            ★ Pasien Prioritas: {{ $nextQueue->priority_reason ?? 'Prioritas Medis' }}
                        </div>
                    @endif
                </div>

                @if(!$currentQueue)
                    <form method="POST" action="{{ route('doctor.call-next') }}" onsubmit="const btn = this.querySelector('button[type=submit]'); btn.disabled = true; btn.innerText = 'Memanggil Pasien...';">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-block btn-lg">
                            Panggil {{ $nextQueue->queue_number }} Sekarang
                        </button>
                    </form>
                @else
                    <div style="font-size: 0.8125rem; color: var(--text-muted); text-align: center; background: #FFFBEB; border: 1px solid #FDE68A; padding: 0.75rem; border-radius: var(--radius-sm);">
                        Pasien ini dapat dipanggil setelah pemeriksaan pasien saat ini ({{ $currentQueue->queue_number }}) diselesaikan atau dilewati.
                    </div>
                @endif
            </div>
        @else
            <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 0.75rem;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                <div style="font-weight: 600; font-size: 1rem;">Tidak Ada Pasien Antri</div>
                <p style="font-size: 0.8125rem; margin-top: 0.25rem;">Semua antrian hari ini telah selesai dilayani.</p>
            </div>
        @endif
    </div>
</div>

{{-- Today's Full Queue Table --}}
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Daftar Seluruh Antrian Dokter Hari Ini</h3>
            <span class="card-subtitle">Urutan lengkap pasien pendaftaran online dan walk-in</span>
        </div>
        <a href="{{ route('doctor.history') }}" class="btn btn-secondary btn-sm">Riwayat Pelayanan</a>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Urutan</th>
                    <th>No. Antrian</th>
                    <th>Nama Pasien</th>
                    <th>Tipe</th>
                    <th>Prioritas</th>
                    <th>Status</th>
                    <th>Waktu Panggilan</th>
                    <th>Waktu Diperiksa</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($todayQueues as $q)
                    <tr>
                        <td><strong>#{{ $q->daily_sequence }}</strong></td>
                        <td>
                            <strong style="color: var(--primary);">{{ $q->queue_number }}</strong>
                        </td>
                        <td>
                            <div style="font-weight: 600;">{{ $q->patient_name }}</div>
                            <small class="text-muted">{{ $q->patient_phone ?? '-' }}</small>
                        </td>
                        <td>
                            <span class="badge {{ $q->is_walkin ? 'badge-gray' : 'badge-blue' }}">
                                {{ $q->is_walkin ? 'Walk-in' : 'Online' }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $q->priority_badge_class }}">
                                {{ $q->priority_label }}
                            </span>
                            @if($q->priority_reason)
                                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">{{ Str::limit($q->priority_reason, 20) }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $q->status_badge_class }}">
                                {{ $q->status_label }}
                            </span>
                        </td>
                        <td>{{ $q->called_at ? $q->called_at->format('H:i') : '-' }}</td>
                        <td>{{ $q->exam_started_at ? $q->exam_started_at->format('H:i') : '-' }}</td>
                        <td>
                            @if($q->status === 'dilewati')
                                <small style="color: var(--danger-text); font-weight: 600;">Alasan: {{ $q->skip_reason }}</small>
                            @elseif($q->doctor_notes)
                                <small class="text-muted">{{ Str::limit($q->doctor_notes, 30) }}</small>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted" style="padding: 2.5rem;">
                            Belum ada pasien yang mengambil nomor antrian untuk sesi praktik Anda hari ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modal Lewati Pasien --}}
<div class="modal-overlay" id="skipModal">
    <div class="modal-content">
        <div class="modal-header">
            <h4 class="modal-title" id="skipModalTitle">Lewati Pasien</h4>
            <button type="button" class="modal-close" onclick="closeSkipModal()">&times;</button>
        </div>
        <form id="skipForm" method="POST" action="">
            @csrf
            <div class="modal-body">
                <p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 1rem;" id="skipModalDesc">
                    Silakan cantumkan alasan mengapa nomor antrian ini dilewati.
                </p>

                <div class="form-group">
                    <label for="skip_reason" class="form-label">
                        Alasan Dilewati <span class="required">*</span>
                    </label>
                    <select name="skip_reason" id="skip_reason_select" class="form-select" onchange="checkCustomReason(this.value)" required>
                        <option value="Pasien tidak berada di ruang tunggu setelah dipanggil 3 kali">Pasien tidak berada di ruang tunggu setelah dipanggil 3 kali</option>
                        <option value="Pasien berhalangan / membatalkan kedatangan">Pasien berhalangan / membatalkan kedatangan</option>
                        <option value="Pasien meminta perubahan jam pemeriksaan">Pasien meminta perubahan jam pemeriksaan</option>
                        <option value="Lainnya">Lainnya (Tulis manual)</option>
                    </select>
                </div>

                <div class="form-group" id="customReasonGroup" style="display: none;">
                    <label for="custom_reason" class="form-label">Alasan Spesifik Lainnya</label>
                    <textarea name="custom_reason" id="custom_reason" rows="2" class="form-textarea" placeholder="Tuliskan alasan..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeSkipModal()">Batal</button>
                <button type="submit" class="btn btn-danger">Konfirmasi Lewati</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function openSkipModal(queueId, queueNumber, patientName) {
        const modal = document.getElementById('skipModal');
        const form = document.getElementById('skipForm');
        const desc = document.getElementById('skipModalDesc');

        form.action = `/doctor/skip/${queueId}`;
        desc.innerText = `Anda akan melewati antrian ${queueNumber} (${patientName}). Alasan wajib dicatat ke dalam sistem.`;
        modal.classList.add('active');
    }

    function closeSkipModal() {
        document.getElementById('skipModal').classList.remove('active');
    }

    function checkCustomReason(val) {
        const group = document.getElementById('customReasonGroup');
        const customText = document.getElementById('custom_reason');
        if (val === 'Lainnya') {
            group.style.display = 'block';
            customText.required = true;
        } else {
            group.style.display = 'none';
            customText.required = false;
        }
    }

    document.getElementById('skipForm').addEventListener('submit', function(e) {
        const select = document.getElementById('skip_reason_select');
        const custom = document.getElementById('custom_reason');
        if (select.value === 'Lainnya' && custom.value.trim() !== '') {
            select.name = 'skip_reason_old';
            custom.name = 'skip_reason';
        }
    });

    // Text-To-Speech (TTS) Announcement using Web Speech API
    function speakAnnouncement(text) {
        if (!text) return;
        if (!('speechSynthesis' in window)) {
            console.warn('Web Speech API is not supported in this browser.');
            return;
        }

        try {
            window.speechSynthesis.cancel();
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'id-ID';
            utterance.rate = 0.9;
            utterance.pitch = 1.0;

            const voices = window.speechSynthesis.getVoices();
            const indonesianVoice = voices.find(v => v.lang && (v.lang.startsWith('id') || v.lang.includes('ID') || v.name.toLowerCase().includes('indonesia')));
            if (indonesianVoice) {
                utterance.voice = indonesianVoice;
            }

            window.speechSynthesis.speak(utterance);
        } catch (err) {
            console.error('Error triggering TTS speech synthesis:', err);
        }
    }

    @if(session('called_announcement'))
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            speakAnnouncement(@json(session('called_announcement.tts_text')));
        }, 300);
    });
    @endif
</script>
@endpush
