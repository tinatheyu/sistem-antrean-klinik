<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Layar Monitor Antrian - Klinik Pratama Sehat Bersama</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #0F172A;
            color: #FFFFFF;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }
        .monitor-header {
            background-color: #1E293B;
            border-bottom: 2px solid #334155;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .monitor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
            flex-grow: 1;
        }
        .monitor-card {
            background-color: #1E293B;
            border: 2px solid #334155;
            border-radius: var(--radius-lg);
            padding: 1.75rem;
            text-align: center;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5);
            transition: all 0.3s ease;
        }
        .monitor-card.calling {
            border-color: #38BDF8;
            background: linear-gradient(180deg, #1E293B 0%, #0C4A6E 100%);
            animation: pulse-border 2s infinite;
        }
        @keyframes pulse-border {
            0% { box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.5); }
            70% { box-shadow: 0 0 0 15px rgba(56, 189, 248, 0); }
            100% { box-shadow: 0 0 0 0 rgba(56, 189, 248, 0); }
        }
        .monitor-card-num {
            font-size: 4.5rem;
            font-weight: 800;
            color: #38BDF8;
            line-height: 1;
            margin: 0.75rem 0;
            letter-spacing: -0.02em;
        }
        .clock {
            font-size: 1.5rem;
            font-weight: 700;
            color: #38BDF8;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <header class="monitor-header">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="width: 44px; height: 44px; background: var(--primary); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.5"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/><path d="M12 9v6m-3-3h6"/></svg>
            </div>
            <div>
                <h1 style="font-size: 1.35rem; font-weight: 800; margin: 0;">Klinik Pratama Sehat Bersama</h1>
                <div style="font-size: 0.8125rem; color: #94A3B8;">Layar Panggilan Antrian Ruang Tunggu Pasien</div>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 2rem;">
            <button id="enableSoundBtn" onclick="enableAudio()" style="background: #334155; color: #FFFFFF; border: 1px solid #475569; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; font-size: 0.8125rem; font-weight: 600;">
                Aktifkan Suara Panggilan 🔔
            </button>
            <div class="clock" id="liveClock">--:--:-- WIB</div>
        </div>
    </header>

    <main class="monitor-grid" id="monitorGrid">
        @forelse($activeCalls as $call)
            <div class="monitor-card {{ $call->status === 'dipanggil' ? 'calling' : '' }}">
                <div style="display: flex; justify-content: center; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem;">
                    <div style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: {{ $call->status === 'dipanggil' ? '#0284C7' : '#059669' }}; color: #FFFFFF;">
                        {{ $call->status === 'dipanggil' ? 'SEDANG DIPANGGIL' : 'SEDANG DIPERIKSA' }}
                    </div>
                    @if($call->priority_status === 'prioritas')
                        <div style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: #9333EA; color: #FFFFFF;">
                            ★ PRIORITAS
                        </div>
                    @endif
                </div>
                <div class="monitor-card-num">{{ $call->queue_number }}</div>
                <div style="font-size: 1.35rem; font-weight: 700; color: #FFFFFF;">{{ $call->patient_name }}</div>
                <div style="font-size: 1rem; color: #94A3B8; margin-top: 0.5rem;">
                    {{ $call->doctor->name ?? '-' }}
                </div>
                <div style="font-size: 0.875rem; color: #38BDF8; font-weight: 600; margin-top: 0.25rem;">
                    {{ $call->schedule->room ?? 'Ruang Dokter' }}
                </div>
            </div>
        @empty
            <div style="grid-column: 1 / -1; text-align: center; padding: 6rem 2rem; color: #64748B;">
                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 1rem;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                <div style="font-size: 1.5rem; font-weight: 700;">Menunggu Panggilan Dokter</div>
                <p style="font-size: 1rem; margin-top: 0.5rem;">Nomor antrian yang sedang dipanggil akan muncul di layar ini secara otomatis.</p>
            </div>
        @endforelse
    </main>

    <footer style="background-color: #0B1120; border-top: 1px solid #1E293B; padding: 0.75rem 2rem; display: flex; justify-content: space-between; font-size: 0.8125rem; color: #64748B;">
        <div>Sistem Antrian Terpusat Terpadu (Online & Walk-in Prioritas FIFO)</div>
        <div>Harap mempersiapkan kartu identitas atau tiket QR saat nomor Anda dipanggil</div>
    </footer>

    <script>
        // Digital Clock
        function updateClock() {
            const now = new Date();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            document.getElementById('liveClock').innerText = `${h}:${m}:${s} WIB`;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Audio & TTS System
        let audioCtx = null;
        let soundEnabled = false;

        function enableAudio() {
            soundEnabled = true;
            if (!audioCtx) {
                audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            playChime();
            speakAnnouncement('Sistem suara dan panggilan antrean telah diaktifkan.');
            const btn = document.getElementById('enableSoundBtn');
            btn.innerText = 'Suara & Panggilan Suara Aktif ✅';
            btn.style.background = '#059669';
        }

        function playChime() {
            if (!soundEnabled || !audioCtx) return;
            try {
                const now = audioCtx.currentTime;
                
                // Ding tone
                const osc1 = audioCtx.createOscillator();
                const gain1 = audioCtx.createGain();
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(587.33, now); // D5
                osc1.frequency.exponentialRampToValueAtTime(880, now + 0.3); // A5
                gain1.gain.setValueAtTime(0.3, now);
                gain1.gain.exponentialRampToValueAtTime(0.001, now + 1.2);
                osc1.connect(gain1);
                gain1.connect(audioCtx.destination);
                osc1.start(now);
                osc1.stop(now + 1.2);

                // Dong tone
                const osc2 = audioCtx.createOscillator();
                const gain2 = audioCtx.createGain();
                osc2.type = 'sine';
                osc2.frequency.setValueAtTime(880, now + 0.35);
                gain2.gain.setValueAtTime(0.3, now + 0.35);
                gain2.gain.exponentialRampToValueAtTime(0.001, now + 1.6);
                osc2.connect(gain2);
                gain2.connect(audioCtx.destination);
                osc2.start(now + 0.35);
                osc2.stop(now + 1.6);
            } catch (e) {
                console.warn('Audio chime playback error:', e);
            }
        }

        function speakAnnouncement(text) {
            if (!soundEnabled || !text) return;
            if (!('speechSynthesis' in window)) {
                console.warn('Web Speech API is not supported in this browser.');
                return;
            }

            try {
                window.speechSynthesis.cancel();
                const utterance = new SpeechSynthesisUtterance(text);
                utterance.lang = 'id-ID';
                utterance.rate = 0.9;
                utterance.pitch = 1.0;

                const voices = window.speechSynthesis.getVoices();
                const indonesianVoice = voices.find(v => v.lang && (v.lang.startsWith('id') || v.lang.includes('ID') || v.name.toLowerCase().includes('indonesia')));
                if (indonesianVoice) {
                    utterance.voice = indonesianVoice;
                }

                // Delay speech slightly to let chime play
                setTimeout(() => {
                    window.speechSynthesis.speak(utterance);
                }, 700);
            } catch (err) {
                console.error('Error in TTS speech synthesis:', err);
            }
        }

        let announcedCalls = new Set();
        let lastActiveHash = '';

        // Live Monitor Polling
        setInterval(() => {
            fetch("{{ route('display.api') }}")
                .then(res => res.json())
                .then(data => {
                    const hash = JSON.stringify(data.active_calls);
                    if (hash !== lastActiveHash) {
                        lastActiveHash = hash;
                        renderActiveCalls(data.active_calls);
                    }
                })
                .catch(() => {});
        }, 4000);

        function renderActiveCalls(calls) {
            const grid = document.getElementById('monitorGrid');
            if (!calls || calls.length === 0) {
                grid.innerHTML = `
                    <div style="grid-column: 1 / -1; text-align: center; padding: 6rem 2rem; color: #64748B;">
                        <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 1rem;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        <div style="font-size: 1.5rem; font-weight: 700;">Menunggu Panggilan Dokter</div>
                        <p style="font-size: 1rem; margin-top: 0.5rem;">Nomor antrian yang sedang dipanggil akan muncul di layar ini secara otomatis.</p>
                    </div>
                `;
                return;
            }

            let newlyCalled = null;
            let html = '';
            calls.forEach(c => {
                const isCalling = c.status === 'dipanggil';
                const callKey = `${c.id}-${c.status}-${c.recall_count || 0}`;

                if (isCalling && !announcedCalls.has(callKey)) {
                    announcedCalls.add(callKey);
                    newlyCalled = c;
                }

                const priorityBadge = c.priority_status === 'prioritas' 
                    ? `<div style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: #9333EA; color: #FFFFFF;">★ PRIORITAS</div>`
                    : '';

                html += `
                    <div class="monitor-card ${isCalling ? 'calling' : ''}">
                        <div style="display: flex; justify-content: center; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem;">
                            <div style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: ${isCalling ? '#0284C7' : '#059669'}; color: #FFFFFF;">
                                ${c.status_label.toUpperCase()}
                            </div>
                            ${priorityBadge}
                        </div>
                        <div class="monitor-card-num">${c.queue_number}</div>
                        <div style="font-size: 1.35rem; font-weight: 700; color: #FFFFFF;">${c.patient_name}</div>
                        <div style="font-size: 1rem; color: #94A3B8; margin-top: 0.5rem;">
                            ${c.doctor_name}
                        </div>
                        <div style="font-size: 0.875rem; color: #38BDF8; font-weight: 600; margin-top: 0.25rem;">
                            ${c.room}
                        </div>
                    </div>
                `;
            });
            grid.innerHTML = html;

            if (newlyCalled) {
                playChime();
                speakAnnouncement(newlyCalled.tts_text || `Nomor antrean ${newlyCalled.queue_number}, silakan menuju ${newlyCalled.room}.`);
            }
        }
    </script>
</body>
</html>
