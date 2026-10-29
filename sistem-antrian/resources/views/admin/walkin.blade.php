@extends('layouts.app')

@section('title', 'Pendaftaran Pasien Walk-in')
@section('header-title', 'Pendaftaran Pasien Langsung (Walk-in)')

@section('content')
<div style="max-width: 680px; margin: 0 auto;">
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Registrasi Antrian Walk-in</h3>
                <span class="card-subtitle">
                    Daftarkan pasien yang datang langsung tanpa harus membuat akun login pasien.
                </span>
            </div>
            <a href="{{ route('admin.queues') }}" class="btn btn-secondary btn-sm">Kembali ke Antrian</a>
        </div>

        <div style="background-color: #EFF6FF; border: 1px solid #BFDBFE; border-radius: var(--radius-sm); padding: 0.85rem; margin-bottom: 1.5rem; font-size: 0.875rem; color: #1E40AF;">
            <strong>Integrasi Antrian Terpusat (FIFO):</strong> Pasien walk-in akan langsung ditempatkan dalam sistem antrian yang sama dengan pasien pendaftaran online berdasarkan urutan waktu kedatangan.
        </div>

        <form method="POST" action="{{ route('admin.walkin.store') }}">
            @csrf

            <div class="form-group">
                <label for="patient_name" class="form-label">
                    Nama Pasien <span class="required">*</span>
                </label>
                <input 
                    type="text" 
                    name="patient_name" 
                    id="patient_name" 
                    class="form-input @error('patient_name') is-invalid @enderror" 
                    value="{{ old('patient_name') }}" 
                    placeholder="Masukkan nama lengkap pasien" 
                    required 
                    autofocus
                >
                @error('patient_name')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="patient_phone" class="form-label">
                    Nomor Telepon / WhatsApp
                </label>
                <input 
                    type="tel" 
                    name="patient_phone" 
                    id="patient_phone" 
                    class="form-input @error('patient_phone') is-invalid @enderror" 
                    value="{{ old('patient_phone') }}" 
                    placeholder="08xxxxxxxxxx (opsional)"
                >
                @error('patient_phone')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="doctor_id" class="form-label">
                    Dokter Tujuan & Poli <span class="required">*</span>
                </label>
                <select name="doctor_id" id="doctor_id" class="form-select @error('doctor_id') is-invalid @enderror" required>
                    <option value="">-- Pilih Dokter / Poli --</option>
                    @foreach($doctors as $doc)
                        <option value="{{ $doc->id }}" {{ old('doctor_id') == $doc->id ? 'selected' : '' }}>
                            {{ $doc->name }} - {{ $doc->specialization ?? 'Poli Umum' }} 
                            @if($doc->doctorSchedules->isNotEmpty())
                                (Jadwal Hari Ini: {{ $doc->doctorSchedules->pluck('session_name')->join(', ') }})
                            @else
                                (Tidak ada jadwal resmi hari ini)
                            @endif
                        </option>
                    @endforeach
                </select>
                @error('doctor_id')
                    <span class="error-message">{{ $message }}</span>
                @enderror
            </div>

            <div style="background: #F8FAFC; border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 1rem; margin-bottom: 1.25rem;">
                <label class="form-label" style="font-weight: 700; color: var(--navy); margin-bottom: 0.5rem;">
                    Status Prioritas Pasien Walk-in
                </label>
                <div style="display: flex; gap: 1.5rem; margin-bottom: 0.75rem;">
                    <label style="display: flex; align-items: center; gap: 0.35rem; cursor: pointer; font-size: 0.875rem;">
                        <input type="radio" name="priority_status" value="normal" checked onchange="toggleWalkInPriority(false)">
                        <span>Pasien Normal (Standar FIFO)</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.35rem; cursor: pointer; font-size: 0.875rem; color: var(--purple-text); font-weight: 600;">
                        <input type="radio" name="priority_status" value="prioritas" onchange="toggleWalkInPriority(true)">
                        <span>Pasien Prioritas (Lansia / Disabilitas / Hamil)</span>
                    </label>
                </div>

                <div id="walkInPriorityReasonGroup" style="display: none; padding-top: 0.75rem; border-top: 1px dashed var(--border);">
                    <label for="priority_reason" class="form-label" style="font-size: 0.8rem;">
                        Kategori / Keterangan Prioritas
                    </label>
                    <select name="priority_reason" id="priority_reason" class="form-select" style="font-size: 0.85rem;">
                        <option value="Lansia (Usia 60 tahun ke atas)">Lansia (Usia 60 tahun ke atas)</option>
                        <option value="Ibu Hamil / Membawa Balita">Ibu Hamil / Membawa Balita</option>
                        <option value="Penyandang Disabilitas">Penyandang Disabilitas / Kebutuhan Khusus</option>
                        <option value="Kondisi Darurat Tertentu / Membutuhkan Penanganan Segera">Kondisi Darurat Tertentu / Butuh Penanganan Segera</option>
                    </select>
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    Cetak & Terbitkan Nomor Antrian
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function toggleWalkInPriority(isPriority) {
        const group = document.getElementById('walkInPriorityReasonGroup');
        group.style.display = isPriority ? 'block' : 'none';
    }
</script>
@endpush
