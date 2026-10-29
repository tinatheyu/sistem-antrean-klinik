@extends('layouts.app')

@section('title', 'Profil Pasien')
@section('header-title', 'Pengaturan Profil Pasien')

@section('content')
<div style="max-width: 640px; margin: 0 auto;">
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Data Profil Pasien</h3>
                <span class="card-subtitle">Perbarui informasi kontak dan kata sandi akun</span>
            </div>
            <a href="{{ route('pasien.dashboard') }}" class="btn btn-secondary btn-sm">Kembali</a>
        </div>

        <form method="POST" action="{{ route('pasien.profile.update') }}">
            @csrf

            <div class="form-group">
                <label for="name" class="form-label">Nama Lengkap <span class="required">*</span></label>
                <input 
                    type="text" 
                    name="name" 
                    id="name" 
                    class="form-input @error('name') is-invalid @enderror" 
                    value="{{ old('name', $patient->name) }}" 
                    required
                >
                @error('name')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Alamat Email (Akun)</label>
                <input 
                    type="email" 
                    id="email" 
                    class="form-input" 
                    value="{{ $patient->email }}" 
                    disabled 
                    style="background-color: #F1F5F9; cursor: not-allowed;"
                >
                <small class="text-muted">Alamat email digunakan sebagai pengenal akun dan tidak dapat diubah secara mandiri.</small>
            </div>

            <div class="form-group">
                <label for="phone" class="form-label">Nomor Telepon / WhatsApp <span class="required">*</span></label>
                <input 
                    type="tel" 
                    name="phone" 
                    id="phone" 
                    class="form-input @error('phone') is-invalid @enderror" 
                    value="{{ old('phone', $patient->phone) }}" 
                    required
                >
                @error('phone')
                    <span class="error-message">{{ $message }}</span>
                @enderror
                <small class="text-muted">Digunakan untuk pengiriman pengingat antrean melalui WhatsApp.</small>
            </div>

            <h4 style="font-size: 0.925rem; color: var(--navy); margin-top: 1.75rem; margin-bottom: 1rem; border-top: 1px solid var(--border); padding-top: 1.25rem;">
                Ganti Kata Sandi (Opsional)
            </h4>

            <div class="form-group">
                <label for="current_password" class="form-label">Kata Sandi Saat Ini</label>
                <input 
                    type="password" 
                    name="current_password" 
                    id="current_password" 
                    class="form-input @error('current_password') is-invalid @enderror" 
                    placeholder="Masukkan kata sandi lama jika ingin mengganti"
                >
                @error('current_password')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="new_password" class="form-label">Kata Sandi Baru</label>
                    <input 
                        type="password" 
                        name="new_password" 
                        id="new_password" 
                        class="form-input @error('new_password') is-invalid @enderror" 
                        placeholder="Minimal 8 karakter"
                    >
                    @error('new_password')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="new_password_confirmation" class="form-label">Konfirmasi Kata Sandi Baru</label>
                    <input 
                        type="password" 
                        name="new_password_confirmation" 
                        id="new_password_confirmation" 
                        class="form-input" 
                        placeholder="Ulangi sandi baru"
                    >
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">
                    Simpan Perubahan Profil
                </button>
            </div>
        </form>

        <div style="border-top: 1px solid var(--border); margin-top: 1.75rem; padding-top: 1.25rem;">
            <h4 style="font-size: 0.925rem; color: var(--navy); margin-bottom: 0.5rem;">
                Notifikasi Telegram
            </h4>

            @if (! $telegramAvailable)
                <div class="alert alert-warning" style="margin-bottom: 0.75rem;">
                    Integrasi Telegram belum aktif pada server. Notifikasi Telegram tidak akan dikirim. Hubungi petugas klinik.
                </div>
            @elseif ($patient->hasTelegramLinked())
                <p class="text-muted" style="margin-bottom: 0.75rem;">
                    Akun Telegram kamu sudah terhubung
                    @if ($patient->telegram_username)
                        (<strong>{{ '@'.$patient->telegram_username }}</strong>)
                    @endif
                    sejak
                    {{ optional($patient->telegram_linked_at)->format('d M Y H:i') ?? '-' }}.
                    Konfirmasi pendaftaran dan pengingat antrean akan dikirim ke akun ini.
                </p>

                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                    <form method="POST" action="{{ route('pasien.telegram.refresh') }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm">
                            Perbarui Tautan Koneksi
                        </button>
                    </form>
                    <form method="POST" action="{{ route('pasien.telegram.unlink') }}"
                          onsubmit="return confirm('Putuskan koneksi Telegram? Notifikasi Telegram tidak akan dikirim lagi.');">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-sm">
                            Putuskan Koneksi
                        </button>
                    </form>
                </div>
                <small class="text-muted" style="display: block; margin-top: 0.5rem;">
                    Gunakan <em>Perbarui Tautan Koneksi</em> bila kamu ingin menautkan ulang ke akun Telegram lain.
                </small>
            @else
                <p class="text-muted" style="margin-bottom: 0.75rem;">
                    Akun Telegram kamu belum terhubung. Hubungkan untuk menerima konfirmasi pendaftaran dan pengingat antrean.
                </p>

                @if ($telegramLinkUrl)
                    <a href="{{ $telegramLinkUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm">
                        Hubungkan Telegram
                    </a>
                    <small class="text-muted" style="display: block; margin-top: 0.5rem;">
                        Tautan ini bersifat sementara dan hanya dapat digunakan sekali. Setelah membuka Telegram, tekan <strong>Start</strong> pada bot
                        <strong>{{ '@'.config('services.telegram.bot_username') }}</strong>.
                    </small>

                    <form method="POST" action="{{ route('pasien.telegram.refresh') }}" style="margin-top: 0.75rem;">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm">
                            Buat Ulang Tautan
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('pasien.telegram.refresh') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">
                            Buat Tautan Penghubung
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
