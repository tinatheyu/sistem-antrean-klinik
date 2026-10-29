<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'telegram_chat_id',
        'telegram_username',
        'telegram_link_token',
        'telegram_link_token_expires_at',
        'telegram_linked_at',
        'role',
        'status',
        'specialization',
        'email_verified_at',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'telegram_link_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'telegram_link_token_expires_at' => 'datetime',
            'telegram_linked_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Apakah akun pasien sudah terhubung dengan Telegram.
     */
    public function hasTelegramLinked(): bool
    {
        return ! empty($this->telegram_chat_id);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isDoctor(): bool
    {
        return $this->role === 'dokter';
    }

    public function isPatient(): bool
    {
        return $this->role === 'pasien';
    }

    public function isActive(): bool
    {
        return $this->status === 'aktif';
    }

    public function getRoleDisplayNameAttribute(): string
    {
        return match ($this->role) {
            'admin' => 'Administrator',
            'dokter' => 'Dokter',
            'pasien' => 'Pasien',
            default => ucfirst($this->role),
        };
    }

    public function doctorSchedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class, 'doctor_id');
    }

    public function doctorQueues(): HasMany
    {
        return $this->hasMany(Queue::class, 'doctor_id');
    }

    public function patientQueues(): HasMany
    {
        return $this->hasMany(Queue::class, 'patient_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'user_id');
    }
}
