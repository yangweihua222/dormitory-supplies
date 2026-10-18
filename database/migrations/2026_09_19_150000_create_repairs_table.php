<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repairs', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->nullable()->comment('报修日期（数据库设计未列，原型列表首列与表单必填项）');
            $table->string('location', 100)->comment('报修地点（手动填入）');
            $table->enum('category', ['water', 'wood', 'electric', 'ac', 'other'])->comment('报修类别（大类）');
            $table->string('sub_category', 50)->nullable()->comment('报修项目（子类，随大类联动）');
            $table->string('description', 1000)->nullable()->comment('问题描述');
            $table->date('repair_date')->nullable()->comment('维修日期');
            $table->enum('appoint_period', ['am', 'pm'])->nullable()->comment('预约时间：上午/下午（数据库设计为 appoint_at DATETIME，原型表单为上下午单选）');
            $table->boolean('is_agree_proxy')->default(false)->comment('是否同意代修');
            $table->string('contact', 50)->nullable()->comment('报修人联系方式');
            $table->boolean('is_reported_platform')->default(false)->comment('是否上报平台');
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete()->comment('报修人');
            $table->foreignId('follower_id')->nullable()->constrained('users')->nullOnDelete()->comment('跟修人（填写后生成维修待办）');
            $table->enum('repair_status', ['pending', 'processing', 'done'])->default('pending')->comment('维修情况：待处理/处理中/已完成（原型三态，数据库设计为 undone/done 二态）');
            $table->timestamps();
            $table->softDeletes();

            $table->index('follower_id');
            $table->index('category');
            $table->index('repair_status');
            $table->index('report_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repairs');
    }
};
