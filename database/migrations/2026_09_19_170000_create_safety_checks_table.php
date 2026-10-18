<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 一张表承载安全检查 6 个类别（对齐原型）
        Schema::create('safety_checks', function (Blueprint $table) {
            $table->id();
            $table->enum('category', [
                'dorm_fire', 'public_order',
                'property', 'personal', 'environment', 'emergency',
            ])->comment('检查类别（见 SafetyCategory）');
            $table->date('check_date')->comment('检查日期 / 事件日期');

            // —— 检查类字段（宿舍消防安全检查、公寓消防安全隐患排查）——
            $table->string('scope_range', 200)->nullable()->comment('检查范围');
            $table->string('location', 200)->nullable()->comment('检查地点');
            $table->text('content')->nullable()->comment('检查内容');
            $table->enum('result', ['normal', 'abnormal'])->nullable()->comment('检查结果：正常/异常');
            $table->text('summary')->nullable()->comment('累计汇总');
            $table->string('remark', 500)->nullable()->comment('备注');
            $table->json('inspector_ids')->nullable()->comment('检查人员（系统用户 id 数组，多选）');

            // —— 事件类字段（公共秩序 / 财产安全 / 人身安全 / 环境卫生安全 / 突发类）——
            $table->text('event_desc')->nullable()->comment('事件描述');
            $table->dateTime('event_time')->nullable()->comment('事件时间');
            $table->string('place', 200)->nullable()->comment('地点');
            $table->string('person', 200)->nullable()->comment('人物');
            $table->text('process')->nullable()->comment('经过');
            $table->json('images')->nullable()->comment('现场图片（文件名数组）');

            // —— 突发类安全事件额外字段（§7.1.7）——
            $table->enum('event_category', ['fire', 'health', 'mental', 'disaster', 'mass'])
                ->nullable()->comment('突发类事件类别');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'check_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safety_checks');
    }
};
