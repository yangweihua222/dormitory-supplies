<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 对齐原型：安全检查类别为 6 个，删除「公寓消防安全隐患排查」(apartment_hazard)。
 * 存量 apartment_hazard 数据并入 dorm_fire（宿舍消防安全检查，同为用电安全检查表单）。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('safety_checks')->where('category', 'apartment_hazard')->update(['category' => 'dorm_fire']);

        // SQLite（测试内存库）建表语句已是目标枚举集，且不支持 MODIFY COLUMN，跳过
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE safety_checks MODIFY COLUMN category ENUM(
            'dorm_fire', 'public_order', 'property', 'personal', 'environment', 'emergency'
        ) NOT NULL COMMENT '检查类别（见 SafetyCategory）'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE safety_checks MODIFY COLUMN category ENUM(
            'dorm_fire', 'apartment_hazard', 'public_order', 'property', 'personal', 'environment', 'emergency'
        ) NOT NULL COMMENT '检查类别（见 SafetyCategory）'");
    }
};
