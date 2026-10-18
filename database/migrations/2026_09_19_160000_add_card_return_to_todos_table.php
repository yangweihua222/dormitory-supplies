<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 房卡归还提醒（需求 §5.3 借用 + §1.1 待办联动）
     * 1. todos.type 增加 card_return（房卡归还提醒）
     * 2. todos 增加 remind_at（提醒时间）：到点才在工作台出现
     */
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->enum('type', [
                'handover', 'repair', 'scrap', 'handover_overdue', 'qty_mismatch', 'card_return',
            ])->comment('类型')->change();
        });

        Schema::table('todos', function (Blueprint $table) {
            $table->dateTime('remind_at')->nullable()
                ->comment('提醒时间：为空表示立即提醒，否则到点才出现在待办列表')
                ->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->dropColumn('remind_at');
        });

        Schema::table('todos', function (Blueprint $table) {
            $table->enum('type', [
                'handover', 'repair', 'scrap', 'handover_overdue', 'qty_mismatch',
            ])->comment('类型')->change();
        });
    }
};
