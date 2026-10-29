@extends('layouts.auth')

@section('title', 'Lupa Kata Sandi')

@section('content')
<h2 style="font-size: 1.25rem; font-weight: 700; color: var(--navy); margin-bottom: 0.25rem; text-align: center;">
    Pemulihan Kata Sandi
</h2>
<p style="font-size: 0.875rem; color: var(--text-secondary); margin-bottom: 1.5rem; text-align: center;">
    Masukkan alamat email yang terdaftar untuk menerima petunjuk reset kata sandi
</p>

@if (session('status'))
    <div class="alert alert-success">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
        <span>{{ session('status') }}</span>
    </div>
@endif

<form method="POST" action="{{ route('password.email') }}" novalidate>
    @csrf

    <div class="form-group">
        <label for="email" class="form-label">
            Alamat Email Terdaftar <span class="required">*</span>
        </label>
        <input 
            type="email" 
            name="email" 
            id="email" 
            class="form-input @error('email') is-invalid @enderror" 
            value="{{ old('email') }}" 
            placeholder="nama@email.com" 
            required 
            autofocus
        >
        @error('email')
            <span class="error-message">{{ $message }}</span>
        @enderror
    </div>

    <button type="submit" class="btn btn-primary btn-block">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
        Kirim Tautan Pemulihan
    </button>
</form>

<div style="margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid var(--border); text-align: center; font-size: 0.875rem; color: var(--text-secondary);">
    Kembali ke <a href="{{ route('login') }}" style="font-weight: 600;">Halaman Masuk</a>
</div>
@endsection
