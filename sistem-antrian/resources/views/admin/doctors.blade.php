@extends('layouts.app')

@section('title', 'Kelola Akun Dokter')
@section('header-title', 'Otorisasi & Akun Dokter')

@section('content')
<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem;">
    {{-- Form Tambah Akun Dokter --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Tambah Dokter Baru</h3>
                <span class="card-subtitle">Pembuatan akun dokter terotorisasi (khusus admin)</span>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.doctors.store') }}">
            @csrf

            <div class="form-group">
                <label for="name" class="form-label">Nama Lengkap & Gelar <span class="required">*</span></label>
                <input type="text" name="name" id="name" class="form-input @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="dr. Nama Dokter, Sp.X" required>
                @error('name')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="specialization" class="form-label">Spesialisasi / Poli <span class="required">*</span></label>
                <input type="text" name="specialization" id="specialization" class="form-input @error('specialization') is-invalid @enderror" value="{{ old('specialization') }}" placeholder="Contoh: Poli Umum / Spesialis Anak" required>
                @error('specialization')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Alamat Email Login <span class="required">*</span></label>
                <input type="email" name="email" id="email" class="form-input @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="dokter@klinik.com" required>
                @error('email')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="phone" class="form-label">Nomor Telepon / WhatsApp <span class="required">*</span></label>
                <input type="tel" name="phone" id="phone" class="form-input @error('phone') is-invalid @enderror" value="{{ old('phone') }}" placeholder="08xxxxxxxxxx" required>
                @error('phone')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Kata Sandi Awal <span class="required">*</span></label>
                <input type="password" name="password" id="password" class="form-input @error('password') is-invalid @enderror" placeholder="Minimal 8 karakter" required>
                @error('password')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line></svg>
                Buat Akun Dokter
            </button>
        </form>
    </div>

    {{-- Daftar Akun Dokter --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Daftar Dokter Terotorisasi</h3>
                <span class="card-subtitle">Dokter yang memiliki hak akses dashboard /doctor/dashboard</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Dokter</th>
                        <th>Email / Kontak</th>
                        <th>Spesialisasi</th>
                        <th>Jadwal Aktif</th>
                        <th>Total Pelayanan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($doctors as $d)
                        <tr>
                            <td>
                                <strong>{{ $d->name }}</strong>
                            </td>
                            <td>
                                <div>{{ $d->email }}</div>
                                <small class="text-muted">{{ $d->phone ?? '-' }}</small>
                            </td>
                            <td>
                                <span class="badge badge-purple">{{ $d->specialization ?? 'Dokter Umum' }}</span>
                            </td>
                            <td>{{ $d->doctor_schedules_count }} Sesi</td>
                            <td>{{ $d->doctor_queues_count }} Pasien</td>
                            <td>
                                @if($d->status === 'aktif')
                                    <span class="badge badge-green">Aktif</span>
                                @else
                                    <span class="badge badge-red">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('admin.doctors.toggle', $d->id) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $d->status === 'aktif' ? 'btn-danger' : 'btn-success' }}" onclick="return confirm('Ubah status akses dokter ini?')">
                                        {{ $d->status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted" style="padding: 2rem;">
                                Belum ada akun dokter terdaftar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1.25rem;">
            {{ $doctors->links() }}
        </div>
    </div>
</div>
@endsection
