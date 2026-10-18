<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 一张表承载入住 / 退宿 / 调宿三种业务 + 历史记录（status=moved_out 即历史）
        Schema::create('accommodations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete()->comment('房间号');
            $table->string('student_no', 50)->comment('学号');
            $table->string('name', 50)->comment('姓名');
            $table->enum('gender', ['male', 'female'])->comment('性别（三种业务均必填）');
            $table->enum('biz_type', ['checkin', 'checkout', 'transfer'])->comment('业务类型：入住/退宿/调宿');
            $table->enum('status', ['living', 'moved_out'])->default('living')->comment('在住 / 已搬出（历史）');
            $table->dateTime('check_in_at')->nullable()->comment('入住时间');
            $table->dateTime('check_out_at')->nullable()->comment('退宿时间（数据库设计未列，原型表格有此列）');
            $table->string('original_room_no', 50)->nullable()->comment('原房间号（调宿）');

            // 入住登记文件 / 物品确认
            $table->enum('agreement_status', ['signed', 'unsigned'])->nullable()->comment('住宿协议状态');
            $table->enum('safety_duty_status', ['signed', 'unsigned'])->nullable()->comment('安全员责任书');
            $table->enum('furniture_confirm', ['signed', 'unsigned'])->nullable()->comment('家具确认单');
            $table->enum('foreigner_registration', ['registered', 'unregistered'])->nullable()->comment('外国人住宿登记');
            $table->enum('mattress', ['issued', 'unissued'])->nullable()->comment('床垫');
            $table->enum('bedding', ['purchased', 'unpurchased'])->nullable()->comment('床品');
            $table->enum('access_control', ['authorized', 'unauthorized'])->nullable()->comment('门禁系统');

            // 退宿 / 调宿额外字段
            $table->boolean('has_checkout_order')->nullable()->comment('退宿转单（有无）');
            $table->boolean('is_belongings_cleared')->nullable()->comment('个人物品是否清理完成');
            $table->enum('furniture_state', ['complete', 'missing', 'damaged'])->nullable()->comment('家具：齐全完好/缺失/破损');
            $table->enum('access_action', ['cancel', 'keep'])->nullable()->comment('门禁：取消授权/保留授权');
            $table->boolean('has_transfer_application')->nullable()->comment('调整申请（有无）');

            $table->boolean('is_procedure_done')->default(false)->comment('手续办理是否完成');
            $table->boolean('is_handed_over')->default(false)->comment('是否已交接');
            $table->string('remark', 500)->nullable()->comment('备注');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['room_id', 'status']);
            $table->index('student_no');
            $table->index('biz_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accommodations');
    }
};
