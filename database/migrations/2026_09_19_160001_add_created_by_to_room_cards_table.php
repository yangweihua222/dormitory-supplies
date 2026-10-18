<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 房卡归还提醒要「提醒创建这条数据的人」，故记录创建人
     */
    public function up(): void
    {
        Schema::table('room_cards', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete()
                ->comment('创建人（借用未还时提醒此人归还）')
                ->after('card_status');
        });
    }

    public function down(): void
    {
        Schema::table('room_cards', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
