@extends('layouts.app')

@section('title', 'Data Pasien')
@section('header-title', 'Rekam Data Pasien')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Daftar Pasien Terdaftar</h3>
            <span class="card-subtitle">Pengelolaan akun pasien, verifikasi, dan status akses</span>
        </div>
        <form method="GET" action="{{ route('admin.patients') }}" style="display: flex; gap: 0.5rem;">
            <input type="text" name="search" class="form-input" style="width: 240px; padding: 0.4rem 0.75rem;" placeholder="Cari nama, email, no telp..." value="{{ $search }}">
            <button type="submit" class="btn btn-secondary btn-sm">Cari</button>
            @if($search)
                <a href="{{ route('admin.patients') }}" class="btn btn-secondary btn-sm">Reset</a>
            @endif
        </form>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama Pasien</th>
                    <th>Email</th>
                    <th>No. Telepon</th>
                    <th>Jumlah Kunjungan</th>
                    <th>Status Akun</th>
                    <th>Terdaftar Sejak</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($patients as $p)
                    <tr>
                        <td>
                            <div style="font-weight: 600;">{{ $p->name }}</div>
                        </td>
                        <td>{{ $p->email }}</td>
                        <td>{{ $p->phone ?? '-' }}</td>
                        <td>
                            <span class="badge badge-blue">{{ $p->patient_queues_count }} kali</span>
                        </td>
                        <td>
                            @if($p->status === 'aktif')
                                <span class="badge badge-green">Aktif</span>
                            @else
                                <span class="badge badge-red">Nonaktif</span>
                            @endif
                        </td>
                        <td>{{ $p->created_at->format('d M Y') }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.patients.toggle', $p->id) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $p->status === 'aktif' ? 'btn-danger' : 'btn-success' }}" onclick="return confirm('Ubah status akun pasien ini?')">
                                    {{ $p->status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted" style="padding: 2.5rem;">
                            Tidak ditemukan data pasien.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1.25rem;">
        {{ $patients->links() }}
    </div>
</div>
@endsection
