S@extends('layouts.app')

@section('title', 'Log Aktivitas')
@section('header-title', 'Audit & Log Aktivitas Sistem')

@section('content')
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Rekam Jejak Operasional</h3>
            <span class="card-subtitle">Log aktivitas terperinci dari seluruh peran (Admin, Dokter, Pasien, Tamu)</span>
        </div>

        <form method="GET" action="{{ route('admin.logs') }}" style="display: flex; gap: 0.5rem;">
            <select name="role" class="form-select" style="width: 140px; padding: 0.4rem 0.6rem; font-size: 0.8125rem;">
                <option value="">Semua Peran</option>
                <option value="admin" {{ $role == 'admin' ? 'selected' : '' }}>Admin</option>
                <option value="dokter" {{ $role == 'dokter' ? 'selected' : '' }}>Dokter</option>
                <option value="pasien" {{ $role == 'pasien' ? 'selected' : '' }}>Pasien</option>
            </select>

            <select name="action" class="form-select" style="width: 170px; padding: 0.4rem 0.6rem; font-size: 0.8125rem;">
                <option value="">Semua Tindakan</option>
                <option value="LOGIN" {{ $action == 'LOGIN' ? 'selected' : '' }}>Login</option>
                <option value="LOGOUT" {{ $action == 'LOGOUT' ? 'selected' : '' }}>Logout</option>
                <option value="REGISTER_PATIENT" {{ $action == 'REGISTER_PATIENT' ? 'selected' : '' }}>Registrasi Pasien</option>
                <option value="QUEUE_CALLED" {{ $action == 'QUEUE_CALLED' ? 'selected' : '' }}>Panggilan Antrian</option>
                <option value="EXAM_STARTED" {{ $action == 'EXAM_STARTED' ? 'selected' : '' }}>Mulai Periksa</option>
                <option value="EXAM_COMPLETED" {{ $action == 'EXAM_COMPLETED' ? 'selected' : '' }}>Selesai Periksa</option>
                <option value="QUEUE_SKIPPED" {{ $action == 'QUEUE_SKIPPED' ? 'selected' : '' }}>Pasien Dilewati</option>
            </select>

            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            @if($action || $role)
                <a href="{{ route('admin.logs') }}" class="btn btn-secondary btn-sm">Reset</a>
            @endif
        </form>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pengguna</th>
                    <th>Peran</th>
                    <th>Tindakan</th>
                    <th>Keterangan Aktivitas</th>
                    <th>Alamat IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td style="white-space: nowrap;">
                            <div>{{ $log->created_at->format('d/m/Y H:i:s') }}</div>
                            <small class="text-muted">{{ $log->created_at->diffForHumans() }}</small>
                        </td>
                        <td>
                            <strong>{{ $log->user_name ?? 'Sistem' }}</strong>
                        </td>
                        <td>
                            <span class="badge {{ $log->role === 'admin' ? 'badge-purple' : ($log->role === 'dokter' ? 'badge-blue' : ($log->role === 'pasien' ? 'badge-green' : 'badge-gray')) }}">
                                {{ ucfirst($log->role) }}
                            </span>
                        </td>
                        <td>
                            <code style="background: #F1F5F9; padding: 2px 6px; border-radius: 4px; font-weight: 600;">
                                {{ $log->action }}
                            </code>
                        </td>
                        <td>{{ $log->description }}</td>
                        <td><small class="text-muted">{{ $log->ip_address ?? '-' }}</small></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted" style="padding: 2.5rem;">
                            Belum ada catatan aktivitas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top: 1.25rem;">
        {{ $logs->links() }}
    </div>
</div>
@endsection
