<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('queue_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_id')->constrained('queues')->cascadeOnDelete();
            $table->string('channel', 20); // whatsapp, telegram
            $table->string('reminder_type', 30); // 30_min, 60_min
            $table->string('recipient');
            $table->integer('estimated_remaining_minutes')->default(0);
            $table->text('message_body');
            $table->string('status', 30)->default('sent'); // sent, failed, cancelled, simulated
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['queue_id', 'channel', 'reminder_type'], 'queue_channel_reminder_unique');
            $table->index(['channel', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_reminders');
    }
};
