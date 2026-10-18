<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 值班日志改为原型（需求 2-2）的扁平单表结构：一条记录 = 一个分类 + 分类字段详情（JSON 平铺）
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('duty_log_items');
        Schema::dropIfExists('duty_logs');

        Schema::create('duty_logs', function (Blueprint $table) {
            $table->id();
            $table->date('log_date')->comment('日期');
            $table->string('category', 20)->comment('分类：phone 电话接听 / visit 咨询接待 / exception 异常情况 / service 服务保障');
            $table->json('detail')->nullable()->comment('分类字段详情（按分类存各自字段的 JSON）');
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete()->comment('创建人');
            $table->string('status', 10)->default('pending')->comment('状态：pending 未完成 / done 已完成');
            $table->boolean('handoff_done')->default(false)->comment('是否已交接');
            $table->timestamp('handed_over_at')->nullable()->comment('交接时间');
            $table->timestamps();

            $table->index('log_date');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_logs');
        // 回不去旧的两表结构（旧迁移负责创建），此处仅作回滚占位
    }
};
