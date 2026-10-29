<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Tiket Antrian - {{ $queue->queue_number }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
    </style>
</head>
<body>
    <div style="width: 100%; max-width: 480px; background: #FFFFFF; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); border: 1px solid var(--border); overflow: hidden;">
        <div style="background: linear-gradient(135deg, #1E40AF, #2563EB); color: #FFFFFF; padding: 1.5rem; text-align: center;">
            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; opacity: 0.9;">Verifikasi Tiket Resmi</div>
            <h1 style="font-size: 1.25rem; font-weight: 700; margin: 0.25rem 0;">Klinik Pratama Sehat Bersama</h1>
            <p style="font-size: 0.8125rem; opacity: 0.9;">Sistem Antrian Terintegrasi</p>
        </div>

        <div style="padding: 2rem; text-align: center;">
            <div style="display: inline-flex; align-items: center; gap: 0.35rem; background: var(--success-bg); color: var(--success-text); border: 1px solid var(--success-border); padding: 0.35rem 0.85rem; border-radius: 9999px; font-size: 0.8125rem; font-weight: 700; margin-bottom: 1rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                Tiket Sah & Terdaftar
            </div>

            <div style="font-size: 0.8125rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Nomor Antrian</div>
            <div style="font-size: 3.5rem; font-weight: 800; color: var(--navy); line-height: 1.1; margin: 0.25rem 0;">
                {{ $queue->queue_number }}
            </div>

            <div style="margin: 0.75rem 0;">
                <span class="badge {{ $queue->status_badge_class }}" style="font-size: 0.875rem; padding: 0.4rem 0.85rem;">
                    Status: {{ $queue->status_label }}
                </span>
            </div>

            <div style="margin: 1.25rem 0; padding: 0.75rem; background: #FFFFFF; display: inline-block; border: 1px solid var(--border); border-radius: var(--radius-md);">
                {!! $qrCodeSvg !!}
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">
                    Token: <code>{{ $queue->ticket_token }}</code>
                </div>
            </div>

            <div style="text-align: left; background: #F8FAFC; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1.25rem; margin-top: 1rem; font-size: 0.875rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span class="text-muted">Nama Pasien:</span>
                    <strong>{{ $queue->patient_name }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span class="text-muted">Dokter Tujuan:</span>
                    <strong>{{ $queue->doctor->name ?? '-' }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span class="text-muted">Poli / Ruangan:</span>
                    <strong>{{ $queue->schedule->room ?? 'Poli Umum' }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span class="text-muted">Tanggal Kunjungan:</span>
                    <strong>{{ $queue->queue_date->format('d F Y') }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span class="text-muted">Jenis Registrasi:</span>
                    <strong>{{ $queue->is_walkin ? 'Walk-in (Langsung)' : 'Pendaftaran Mandiri Online' }}</strong>
                </div>
            </div>

            <div style="margin-top: 1.5rem;">
                <a href="{{ route('login') }}" class="btn btn-secondary btn-block">
                    Masuk ke Portal Antrian
                </a>
            </div>
        </div>
    </div>
</body>
</html>
