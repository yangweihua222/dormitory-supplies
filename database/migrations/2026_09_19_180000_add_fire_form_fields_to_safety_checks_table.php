<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 对齐原型：「宿舍消防安全检查」为逐房间用电安全检查表单
 * （房间号 + 6 项用电检查 + 检查结果 + 整改状态 + 外来排查人员），
 * 不再使用检查范围/检查地点/检查内容/累计汇总。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('safety_checks', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable()->after('category')->constrained()->nullOnDelete();
            $table->boolean('private_wiring')->nullable()->after('room_id');
            $table->boolean('strip_overload')->nullable()->after('private_wiring');
            $table->boolean('high_wattage')->nullable()->after('strip_overload');
            $table->boolean('power_off')->nullable()->after('high_wattage');
            $table->boolean('trash_at_door')->nullable()->after('power_off');
            $table->boolean('smoke_blocked')->nullable()->after('trash_at_door');
            $table->string('rectify', 20)->nullable()->after('smoke_blocked')->comment('整改状态 done/pending，仅检查结果=异常时有值');
            $table->string('external_inspector', 200)->nullable()->after('rectify')->comment('外来排查人员');
        });
    }

    public function down(): void
    {
        Schema::table('safety_checks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_id');
            $table->dropColumn([
                'private_wiring', 'strip_overload', 'high_wattage', 'power_off',
                'trash_at_door', 'smoke_blocked', 'rectify', 'external_inspector',
            ]);
        });
    }
};
