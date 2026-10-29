<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom untuk menghubungkan akun pasien dengan Telegram.
     *
     * Kolom bersifat aditif (nullable) sehingga data pasien yang sudah ada
     * tidak terpengaruh dan tidak ada duplikasi data.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram_username')->nullable()->after('telegram_chat_id');
            $table->string('telegram_link_token', 64)->nullable()->unique()->after('telegram_username');
            $table->timestamp('telegram_link_token_expires_at')->nullable()->after('telegram_link_token');
            $table->timestamp('telegram_linked_at')->nullable()->after('telegram_link_token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['telegram_link_token']);
            $table->dropColumn([
                'telegram_username',
                'telegram_link_token',
                'telegram_link_token_expires_at',
                'telegram_linked_at',
            ]);
        });
    }
};
