<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * 删除「房源排查」功能（2026-09-19 用户确认：原型早已移除该模块，仅 index.html 残留入口卡片）。
 * 主细表 room_inspections / room_inspection_items 一并删除，种子数据随表清除。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('room_inspection_items');
        Schema::dropIfExists('room_inspections');
    }

    public function down(): void
    {
        // 功能已下线，不提供回滚
    }
};
