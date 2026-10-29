@extends('layouts.auth')

@section('title', 'Pendaftaran Akun Pasien Baru')

@section('card-class', 'wide')

@section('content')
<h2 style="font-size: 1.25rem; font-weight: 700; color: var(--navy); margin-bottom: 0.25rem; text-align: center;">
    Pendaftaran Pasien Baru
</h2>
<p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 1.5rem; text-align: center;">
    Daftarkan akun pasien Anda untuk mengambil nomor antrian secara online
</p>

<form method="POST" action="{{ route('register') }}" novalidate>
    @csrf

    {{-- Full Name --}}
    <div class="form-group">
        <label for="name" class="form-label">
            Nama Lengkap <span class="required">*</span>
        </label>
        <input 
            type="text" 
            name="name" 
            id="name" 
            class="form-input @error('name') is-invalid @enderror" 
            value="{{ old('name') }}" 
            placeholder="Contoh: Ahmad Subardjo" 
            required 
            autofocus
        >
        @error('name')
            <span class="error-message">{{ $message }}</span>
        @enderror
    </div>

    <div class="grid-2">
        {{-- Email Address --}}
        <div class="form-group">
            <label for="email" class="form-label">
                Alamat Email <span class="required">*</span>
            </label>
            <input 
                type="email" 
                name="email" 
                id="email" 
                class="form-input @error('email') is-invalid @enderror" 
                value="{{ old('email') }}" 
                placeholder="nama@email.com" 
                required
            >
            @error('email')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        {{-- Phone Number --}}
        <div class="form-group">
            <label for="phone" class="form-label">
                Nomor Telepon / WhatsApp <span class="required">*</span>
            </label>
            <input 
                type="tel" 
                name="phone" 
                id="phone" 
                class="form-input @error('phone') is-invalid @enderror" 
                value="{{ old('phone') }}" 
                placeholder="081234567890" 
                required
            >
            @error('phone')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>
    </div>

    {{-- Telegram Username (Optional) --}}
    <div class="form-group">
        <label for="telegram_username" class="form-label">
            Username Telegram <span style="font-size: 0.75rem; color: var(--text-secondary);">(Opsional)</span>
        </label>
        <input 
            type="text" 
            name="telegram_username" 
            id="telegram_username" 
            class="form-input @error('telegram_username') is-invalid @enderror" 
            value="{{ old('telegram_username') }}" 
            placeholder="Contoh: @nama_pengguna"
        >
        @error('telegram_username')
            <span class="error-message">{{ $message }}</span>
        @enderror
        <small class="text-muted">
            Hanya untuk mempermudah. Penghubungan notifikasi tetap dilakukan melalui tombol di halaman Profil setelah pendaftaran. Sistem tidak pernah meminta token bot kepada pasien.
        </small>
    </div>

    <div class="grid-2">
        <div class="form-group">
            <label for="password" class="form-label">
                Kata Sandi <span class="required">*</span>
            </label>
            <div class="password-input-group">
                <input 
                    type="password" 
                    name="password" 
                    id="password" 
                    class="form-input @error('password') is-invalid @enderror" 
                    placeholder="Minimal 8 karakter" 
                    required
                >
                <button type="button" class="password-toggle-btn" onclick="togglePassword('password', this)" title="Lihat Sandi">
                    <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    <svg class="eye-off-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path><line x1="2" y1="2" x2="22" y2="22"></line></svg>
                </button>
            </div>
            @error('password')
                <span class="error-message">{{ $message }}</span>
            @enderror
        </div>

        {{-- Password Confirmation --}}
        <div class="form-group">
            <label for="password_confirmation" class="form-label">
                Konfirmasi Kata Sandi <span class="required">*</span>
            </label>
            <div class="password-input-group">
                <input 
                    type="password" 
                    name="password_confirmation" 
                    id="password_confirmation" 
                    class="form-input" 
                    placeholder="Ulangi kata sandi" 
                    required
                >
                <button type="button" class="password-toggle-btn" onclick="togglePassword('password_confirmation', this)" title="Lihat Sandi">
                    <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    <svg class="eye-off-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path><line x1="2" y1="2" x2="22" y2="22"></line></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Acceptance of Privacy Policy --}}
    <div class="form-group" style="margin-top: 0.5rem;">
        <label class="form-check">
            <input type="checkbox" name="privacy_policy" id="privacy_policy" value="1" {{ old('privacy_policy') ? 'checked' : '' }} required>
            <span>
                Saya menyetujui ketentuan layanan dan <a href="#" onclick="alert('Kebijakan Privasi: Data pendaftaran hanya digunakan untuk keperluan rekam antrian dan pelayanan medis di Klinik Pratama Sehat Bersama.'); return false;">kebijakan privasi</a> klinik. <span class="required">*</span>
            </span>
        </label>
        @error('privacy_policy')
            <span class="error-message">{{ $message }}</span>
        @enderror
    </div>

    {{-- Notice: Strictly Patient Role --}}
    <div style="background-color: #F1F5F9; border-radius: var(--radius-sm); padding: 0.65rem 0.85rem; font-size: 0.8125rem; color: var(--text-secondary); margin-bottom: 1.25rem;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 4px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
        Formulir ini dikhususkan bagi pasien. Akun Tenaga Medis/Dokter dan Admin dibuat secara terotorisasi oleh administrator sistem.
    </div>

    {{-- Submit Button --}}
    <button type="submit" class="btn btn-primary btn-block">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <line x1="19" y1="8" x2="19" y2="14"></line>
            <line x1="22" y1="11" x2="16" y2="11"></line>
        </svg>
        Daftar Akun Pasien
    </button>
</form>

{{-- Link for Existing Users --}}
<div style="margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid var(--border); text-align: center; font-size: 0.875rem; color: var(--text-secondary);">
    Sudah punya akun? <a href="{{ route('login') }}" style="font-weight: 600;">Masuk di sini.</a>
</div>
@endsection
