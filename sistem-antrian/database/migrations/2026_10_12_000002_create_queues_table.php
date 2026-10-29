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
        Schema::create('queues', function (Blueprint $table) {
            $table->id();
            $table->string('queue_number'); // e.g. A-001
            $table->integer('daily_sequence'); // Strict FIFO ordering per day/doctor
            $table->date('queue_date');
            $table->foreignId('patient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('schedule_id')->nullable()->constrained('doctor_schedules')->nullOnDelete();
            $table->string('patient_name');
            $table->string('patient_phone')->nullable();
            $table->string('ticket_token', 64)->unique();
            $table->enum('status', ['menunggu', 'dipanggil', 'sedang_diperiksa', 'selesai', 'dilewati', 'dibatalkan'])->default('menunggu');
            $table->boolean('is_walkin')->default(false);
            $table->timestamp('called_at')->nullable();
            $table->timestamp('exam_started_at')->nullable();
            $table->timestamp('exam_completed_at')->nullable();
            $table->text('skip_reason')->nullable();
            $table->text('doctor_notes')->nullable();
            $table->integer('recall_count')->default(0);
            $table->timestamps();

            $table->index(['queue_date', 'doctor_id', 'status']);
            $table->index(['ticket_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queues');
    }
};
