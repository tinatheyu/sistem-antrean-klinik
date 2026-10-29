@extends('layouts.app')

@section('title', 'Pengaturan Aplikasi')
@section('header-title', 'Pengaturan Aplikasi & Faskes')

@section('content')
<div style="max-width: 720px; margin: 0 auto;">
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Konfigurasi Klinik & Sistem Antrian</h3>
                <span class="card-subtitle">Pengaturan parameter operasional dan identitas klinik</span>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf

            <h4 style="font-size: 0.925rem; color: var(--navy); margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
                Identitas Fasilitas Kesehatan
            </h4>

            <div class="form-group">
                <label for="clinic_name" class="form-label">Nama Klinik / Faskes <span class="required">*</span></label>
                <input 
                    type="text" 
                    name="clinic_name" 
                    id="clinic_name" 
                    class="form-input" 
                    value="{{ $settings['clinic_name']->value ?? 'Klinik Pratama Sehat Bersama' }}" 
                    required
                >
            </div>

            <div class="form-group">
                <label for="clinic_address" class="form-label">Alamat Lengkap</label>
                <input 
                    type="text" 
                    name="clinic_address" 
                    id="clinic_address" 
                    class="form-input" 
                    value="{{ $settings['clinic_address']->value ?? 'Jl. Merdeka Sehat No. 88, Jakarta Pusat' }}"
                >
            </div>

            <div class="form-group">
                <label for="clinic_phone" class="form-label">Nomor Telepon Hotline</label>
                <input 
                    type="text" 
                    name="clinic_phone" 
                    id="clinic_phone" 
                    class="form-input" 
                    value="{{ $settings['clinic_phone']->value ?? '(021) 7890-1234' }}"
                >
            </div>

            <h4 style="font-size: 0.925rem; color: var(--navy); margin-top: 2rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
                Parameter Antrian
            </h4>

            <div class="grid-2">
                <div class="form-group">
                    <label for="queue_prefix" class="form-label">Prefix Nomor Antrian <span class="required">*</span></label>
                    <input 
                        type="text" 
                        name="queue_prefix" 
                        id="queue_prefix" 
                        class="form-input" 
                        value="{{ $settings['queue_prefix']->value ?? 'A' }}" 
                        required
                    >
                    <small class="text-muted">Contoh: 'A' menghasilkan format A-001</small>
                </div>

                <div class="form-group">
                    <label for="avg_consultation_time" class="form-label">Estimasi Waktu Per Pasien (Menit) <span class="required">*</span></label>
                    <input 
                        type="number" 
                        name="avg_consultation_time" 
                        id="avg_consultation_time" 
                        class="form-input" 
                        value="{{ $settings['avg_consultation_time']->value ?? '15' }}" 
                        min="1" 
                        max="60" 
                        required
                    >
                    <small class="text-muted">Digunakan untuk menghitung estimasi waktu tunggu pasien</small>
                </div>
            </div>

            <div class="form-group">
                <label for="max_daily_quota" class="form-label">Batas Kuota Harian Default</label>
                <input 
                    type="number" 
                    name="max_daily_quota" 
                    id="max_daily_quota" 
                    class="form-input" 
                    value="{{ $settings['max_daily_quota']->value ?? '50' }}" 
                    min="5" 
                    max="500"
                >
            </div>

            <h4 style="font-size: 0.925rem; color: var(--navy); margin-top: 2rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
                Pengingat Antrean Pasien (WhatsApp & Telegram)
            </h4>

            <div class="form-group">
                <label for="reminder_lead_time" class="form-label">Waktu Pengiriman Reminder Sebelum Giliran <span class="required">*</span></label>
                @php
                    $currentLead = $settings['reminder_lead_time']->value ?? '30,60';
                @endphp
                <select name="reminder_lead_time" id="reminder_lead_time" class="form-select" required>
                    <option value="30" {{ $currentLead === '30' ? 'selected' : '' }}>Sekitar 30 menit sebelum perkiraan giliran</option>
                    <option value="60" {{ $currentLead === '60' ? 'selected' : '' }}>Sekitar 1 jam (60 menit) sebelum perkiraan giliran</option>
                    <option value="30,60" {{ $currentLead === '30,60' ? 'selected' : '' }}>Bertahap: 1 jam dan 30 menit sebelum perkiraan giliran</option>
                </select>
                <small class="text-muted">
                    Sistem akan menghitung estimasi sisa waktu tunggu berdasarkan antrean yang sedang berjalan dan aturan prioritas, lalu mengirimkan pengingat jika pasien belum dipanggil.
                </small>
            </div>

            <div style="background: #F8FAFC; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 1rem; margin-bottom: 1.5rem; font-size: 0.825rem; color: var(--text-secondary);">
                <strong style="color: var(--navy); display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.35rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    Informasi Keamanan Kredensial Gateway (.env)
                </strong>
                Kredensial API eksternal (seperti Token WhatsApp Gateway dan Token Bot Telegram) disimpan secara aman pada konfigurasi lingkungan <code>.env</code> server klinik untuk mencegah kebocoran kunci rahasia.
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    Simpan Perubahan Pengaturan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
