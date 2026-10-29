<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'queue_id',
        'channel',
        'reminder_type',
        'recipient',
        'estimated_remaining_minutes',
        'message_body',
        'status',
        'sent_at',
        'scheduled_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'estimated_remaining_minutes' => 'integer',
        ];
    }

    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class, 'queue_id');
    }
}
