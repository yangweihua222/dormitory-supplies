<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duty_log_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('duty_log_id')->constrained('duty_logs')->cascadeOnDelete()->comment('所属日志');
            $table->enum('category', ['phone', 'consult', 'abnormal'])->comment('分类：电话接听/咨询接待/异常情况');
            // 电话接听
            $table->string('caller', 100)->nullable()->comment('来电人/单位');
            $table->boolean('is_need_callback')->nullable()->comment('是否需要回电');
            $table->string('contact', 50)->nullable()->comment('联系方式');
            $table->boolean('is_recalled')->nullable()->comment('是否已回电');
            // 咨询接待
            $table->string('visitor', 100)->nullable()->comment('来访人/单位');
            $table->string('matter', 255)->nullable()->comment('咨询事项');
            $table->boolean('is_need_transfer')->nullable()->comment('是否需要移交');
            $table->string('transfer_to', 100)->nullable()->comment('移交部门/联系人');
            $table->boolean('is_finished')->nullable()->comment('是否完成');
            // 异常情况
            $table->text('description')->nullable()->comment('事件描述');
            $table->boolean('is_reported')->nullable()->comment('是否上报');
            $table->string('handling', 255)->nullable()->comment('处置情况');
            $table->timestamps();

            $table->index(['duty_log_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_log_items');
    }
};
