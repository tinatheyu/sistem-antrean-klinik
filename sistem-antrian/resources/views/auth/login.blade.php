@extends('layouts.auth')

@section('title', 'Masuk ke Sistem')

@section('content')
<h2 style="font-size: 1.25rem; font-weight: 700; color: var(--navy); margin-bottom: 0.25rem; text-align: center;">
    Selamat Datang
</h2>
<p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 1.5rem; text-align: center;">
    Masuk ke portal antrian terpadu
</p>

<form method="POST" action="{{ route('login') }}" novalidate>
    @csrf

    {{-- Email or Username --}}
    <div class="form-group">
        <label for="email" class="form-label">
            Email atau No. Telepon <span class="required">*</span>
        </label>
        <input 
            type="text" 
            name="email" 
            id="email" 
            class="form-input @error('email') is-invalid @enderror" 
            value="{{ old('email') }}" 
            placeholder="nama@email.com atau 0812xxxx" 
            required 
            autofocus
        >
        @error('email')
            <span class="error-message">{{ $message }}</span>
        @enderror
    </div>

    {{-- Password --}}
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
                placeholder="Masukkan kata sandi" 
                required
            >
            <button type="button" class="password-toggle-btn" onclick="togglePassword('password', this)" title="Tampilkan/Sembunyikan Kata Sandi">
                <svg class="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg class="eye-off-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                    <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path>
                    <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>
                    <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path>
                    <line x1="2" y1="2" x2="22" y2="22"></line>
                </svg>
            </button>
        </div>
        @error('password')
            <span class="error-message">{{ $message }}</span>
        @enderror
    </div>

    {{-- Remember me & Forgot Password --}}
    <div class="form-row-between">
        <label class="form-check">
            <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
            <span>Ingat saya</span>
        </label>
        <a href="{{ route('password.request') }}" style="font-size: 0.8125rem;">
            Lupa kata sandi?
        </a>
    </div>

    {{-- Submit Button --}}
    <button type="submit" class="btn btn-primary btn-block">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
            <polyline points="10 17 15 12 10 7"></polyline>
            <line x1="15" y1="12" x2="3" y2="12"></line>
        </svg>
        Masuk
    </button>
</form>

{{-- Patient Registration Link --}}
<div style="margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid var(--border); text-align: center; font-size: 0.875rem; color: var(--text-secondary);">
    Belum punya akun pasien? <a href="{{ route('register') }}" style="font-weight: 600;">Daftar di sini.</a>
</div>

{{-- Demo accounts helper pill for evaluation --}}
<div style="margin-top: 1.5rem; background-color: #F8FAFC; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 0.75rem; font-size: 0.75rem; color: var(--text-secondary);">
    <strong style="color: var(--navy); display: block; margin-bottom: 0.35rem;">Akun Percobaan (Siap Digunakan):</strong>
    <div style="display: flex; flex-direction: column; gap: 0.25rem;">
        <div><strong>Admin:</strong> <code>admin@klinik.com</code> | sandi: <code>admin123</code></div>
        <div><strong>Dokter:</strong> <code>dr.budi@klinik.com</code> | sandi: <code>dokter123</code></div>
        <div><strong>Pasien:</strong> <code>rina@gmail.com</code> | sandi: <code>pasien123</code></div>
    </div>
</div>
@endsection
