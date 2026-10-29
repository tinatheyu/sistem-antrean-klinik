<?php

namespace App\Models;

use App\Services\RandomForestPredictionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class Queue extends Model
{
    use HasFactory;

    protected $fillable = [
        'queue_number',
        'daily_sequence',
        'queue_date',
        'patient_id',
        'doctor_id',
        'schedule_id',
        'patient_name',
        'patient_phone',
        'ticket_token',
        'status',
        'is_walkin',
        'called_at',
        'exam_started_at',
        'exam_completed_at',
        'skip_reason',
        'doctor_notes',
        'recall_count',
        'priority_status',
        'priority_reason',
        'priority_verified_by',
        'priority_verified_at',
        'telegram_chat_id',
    ];

    /**
     * Konversi tipe data atribut model.
     */
    protected function casts(): array
    {
        return [
            'queue_date' => 'date',
            'is_walkin' => 'boolean',
            'called_at' => 'datetime',
            'exam_started_at' => 'datetime',
            'exam_completed_at' => 'datetime',
            'priority_verified_at' => 'datetime',
            'recall_count' => 'integer',
            'daily_sequence' => 'integer',
        ];
    }

    /**
     * Relasi pasien.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /**
     * Relasi dokter.
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    /**
     * Relasi jadwal dokter.
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(DoctorSchedule::class, 'schedule_id');
    }

    /**
     * Relasi petugas verifikasi prioritas.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'priority_verified_by');
    }

    /**
     * Relasi reminder antrean.
     */
    public function reminders()
    {
        return $this->hasMany(QueueReminder::class, 'queue_id');
    }

    /**
     * Scope antrean hari ini.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('queue_date', today());
    }

    /**
     * Scope antrean berdasarkan dokter.
     */
    public function scopeForDoctor($query, $doctorId)
    {
        return $query->where('doctor_id', $doctorId);
    }

    /**
     * Urutan panggilan: prioritas terlebih dahulu,
     * kemudian berdasarkan urutan pendaftaran.
     */
    public function scopeOrderedForCalling($query)
    {
        return $query
            ->orderByRaw(
                "CASE WHEN priority_status = 'prioritas' THEN 1 ELSE 2 END ASC"
            )
            ->orderBy('daily_sequence', 'asc');
    }

    /**
     * Label status antrean.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'menunggu' => 'Menunggu Giliran',
            'dipanggil' => 'Sedang Dipanggil',
            'sedang_diperiksa' => 'Dalam Pemeriksaan',
            'selesai' => 'Selesai',
            'dilewati' => 'Dilewati',
            'dibatalkan' => 'Dibatalkan',
            default => ucfirst($this->status),
        };
    }

    /**
     * Kelas badge status antrean.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'menunggu' => 'badge-yellow',
            'dipanggil' => 'badge-blue',
            'sedang_diperiksa' => 'badge-purple',
            'selesai' => 'badge-green',
            'dilewati' => 'badge-orange',
            'dibatalkan' => 'badge-red',
            default => 'badge-gray',
        };
    }

    /**
     * Label status prioritas.
     */
    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority_status) {
            'prioritas' => 'Prioritas',
            'perlu_verifikasi' => 'Perlu Verifikasi',
            default => 'Normal',
        };
    }

    /**
     * Kelas badge prioritas.
     */
    public function getPriorityBadgeClassAttribute(): string
    {
        return match ($this->priority_status) {
            'prioritas' => 'badge-purple',
            'perlu_verifikasi' => 'badge-yellow',
            default => 'badge-gray',
        };
    }

    /**
     * Menghitung jumlah pasien yang berada di depan antrean ini.
     *
     * Aturan antrean klinik (lihat scopeOrderedForCalling):
     *   1. Pasien berstatus 'prioritas' selalu dipanggil lebih dulu.
     *   2. Setelah itu berdasarkan urutan pendaftaran (daily_sequence) terkecil.
     *
     * "Di depan" berarti pasien pada dokter & tanggal yang sama yang masih berada
     * dalam alur pelayanan, yaitu berstatus menunggu, sedang dipanggil, atau
     * sedang diperiksa. Pasien yang sudah selesai / dilewati / dibatalkan tidak
     * dihitung karena tidak lagi menempati antrean.
     */
    public function getPatientsAheadCount(): int
    {
        if (! in_array($this->status, ['menunggu'], true)) {
            return 0;
        }

        $activeStatuses = ['menunggu', 'dipanggil', 'sedang_diperiksa'];

        $baseQuery = self::whereDate('queue_date', $this->queue_date)
            ->where('doctor_id', $this->doctor_id)
            ->whereIn('status', $activeStatuses);

        if ($this->priority_status === 'prioritas') {
            return $baseQuery
                ->where('priority_status', 'prioritas')
                ->where('daily_sequence', '<', $this->daily_sequence)
                ->count();
        }

        $priorityCount = (clone $baseQuery)
            ->where('priority_status', 'prioritas')
            ->count();

        $normalBeforeCount = (clone $baseQuery)
            ->where('priority_status', '!=', 'prioritas')
            ->where('daily_sequence', '<', $this->daily_sequence)
            ->count();

        return $priorityCount + $normalBeforeCount;
    }

    /**
     * Menghitung estimasi sisa waktu tunggu.
     *
     * Menggunakan Random Forest jika aktif dan berhasil.
     * Jika tidak, menggunakan perhitungan fallback:
     * jumlah pasien di depan x rata-rata waktu pemeriksaan.
     */
    public function getEstimatedWaitTimeMinutes(): int
    {
        if ($this->status !== 'menunggu') {
            return 0;
        }

        try {
            /** @var RandomForestPredictionService $rfService */
            $rfService = app(RandomForestPredictionService::class);

            $rfResult = $rfService->predict($this);

            if ($rfResult !== null) {
                return (int) round($rfResult);
            }
        } catch (\Throwable $e) {
            Log::error(
                '[RandomForest] Gagal memanggil service prediksi.',
                [
                    'error' => $e->getMessage(),
                ]
            );
        }

        // Fallback menggunakan rata-rata waktu pemeriksaan.
        $avgTime = (int) AppSetting::get(
            'avg_consultation_time',
            15
        );

        $ahead = $this->getPatientsAheadCount();

        return $ahead * $avgTime;
    }
}
