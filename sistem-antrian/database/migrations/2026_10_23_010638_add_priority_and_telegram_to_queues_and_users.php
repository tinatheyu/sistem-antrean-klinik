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
        Schema::table('queues', function (Blueprint $table) {
            $table->string('priority_status', 30)->default('normal')->after('status');
            $table->string('priority_reason')->nullable()->after('priority_status');
            $table->foreignId('priority_verified_by')->nullable()->after('priority_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('priority_verified_at')->nullable()->after('priority_verified_by');
            $table->string('telegram_chat_id')->nullable()->after('patient_phone');

            $table->index(['queue_date', 'doctor_id', 'status', 'priority_status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->after('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('queues', function (Blueprint $table) {
            $table->dropIndex(['queue_date', 'doctor_id', 'status', 'priority_status']);
            $table->dropForeign(['priority_verified_by']);
            $table->dropColumn([
                'priority_status',
                'priority_reason',
                'priority_verified_by',
                'priority_verified_at',
                'telegram_chat_id',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id']);
        });
    }
};
