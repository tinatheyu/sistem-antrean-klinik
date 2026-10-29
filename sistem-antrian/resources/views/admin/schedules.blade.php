@extends('layouts.app')

@section('title', 'Jadwal Dokter')
@section('header-title', 'Jadwal Praktik Dokter')

@section('content')
<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem;">
    {{-- Form Tambah Jadwal --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Tambah Jadwal Praktik</h3>
                <span class="card-subtitle">Atur kuota dan jam praktik per sesi</span>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.schedules.store') }}">
            @csrf

            <div class="form-group">
                <label for="doctor_id" class="form-label">Dokter <span class="required">*</span></label>
                <select name="doctor_id" id="doctor_id" class="form-select @error('doctor_id') is-invalid @enderror" required>
                    <option value="">-- Pilih Dokter --</option>
                    @foreach($doctors as $doc)
                        <option value="{{ $doc->id }}" {{ old('doctor_id') == $doc->id ? 'selected' : '' }}>
                            {{ $doc->name }} ({{ $doc->specialization ?? 'Dokter Umum' }})
                        </option>
                    @endforeach
                </select>
                @error('doctor_id')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="day_of_week" class="form-label">Hari Praktik <span class="required">*</span></label>
                <select name="day_of_week" id="day_of_week" class="form-select @error('day_of_week') is-invalid @enderror" required>
                    <option value="">-- Pilih Hari --</option>
                    @foreach(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'] as $day)
                        <option value="{{ $day }}" {{ old('day_of_week') == $day ? 'selected' : '' }}>{{ $day }}</option>
                    @endforeach
                </select>
                @error('day_of_week')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="session_name" class="form-label">Nama Sesi <span class="required">*</span></label>
                <input type="text" name="session_name" id="session_name" class="form-input @error('session_name') is-invalid @enderror" value="{{ old('session_name', 'Sesi Pagi') }}" placeholder="Contoh: Sesi Pagi, Sesi Sore" required>
                @error('session_name')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="start_time" class="form-label">Jam Mulai <span class="required">*</span></label>
                    <input type="time" name="start_time" id="start_time" class="form-input @error('start_time') is-invalid @enderror" value="{{ old('start_time', '08:00') }}" required>
                    @error('start_time')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="end_time" class="form-label">Jam Selesai <span class="required">*</span></label>
                    <input type="time" name="end_time" id="end_time" class="form-input @error('end_time') is-invalid @enderror" value="{{ old('end_time', '12:00') }}" required>
                    @error('end_time')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label for="quota" class="form-label">Kuota Pasien <span class="required">*</span></label>
                    <input type="number" name="quota" id="quota" class="form-input @error('quota') is-invalid @enderror" value="{{ old('quota', 30) }}" min="1" max="100" required>
                    @error('quota')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="room" class="form-label">Ruang / Poli</label>
                    <input type="text" name="room" id="room" class="form-input @error('room') is-invalid @enderror" value="{{ old('room', 'Ruang 101') }}" placeholder="Contoh: Poli 1">
                    @error('room')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Simpan Jadwal</button>
        </form>
    </div>

    {{-- Daftar Jadwal Praktik --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Semua Jadwal Terdaftar</h3>
                <span class="card-subtitle">Pengaturan sesi aktif yang dapat dipilih pasien</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Dokter & Poli</th>
                        <th>Hari</th>
                        <th>Sesi & Waktu</th>
                        <th>Kuota</th>
                        <th>Ruangan</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schedules as $s)
                        <tr>
                            <td>
                                <strong>{{ $s->doctor->name ?? '-' }}</strong>
                                <small class="text-muted" style="display: block;">{{ $s->doctor->specialization ?? 'Dokter Umum' }}</small>
                            </td>
                            <td><span class="badge badge-purple">{{ $s->day_of_week }}</span></td>
                            <td>
                                <div><strong>{{ $s->session_name }}</strong></div>
                                <small class="text-muted">{{ $s->formatted_time }}</small>
                            </td>
                            <td>{{ $s->quota }} Pasien</td>
                            <td>{{ $s->room ?? '-' }}</td>
                            <td>
                                @if($s->is_active)
                                    <span class="badge badge-green">Aktif</span>
                                @else
                                    <span class="badge badge-gray">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.35rem;">
                                    <form method="POST" action="{{ route('admin.schedules.toggle', $s->id) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-secondary" title="Ubah status">
                                            {{ $s->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.schedules.destroy', $s->id) }}" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Hapus jadwal ini?')">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted" style="padding: 2rem;">
                                Belum ada jadwal praktik dokter yang ditambahkan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
