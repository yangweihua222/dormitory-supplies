<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->comment('签到人');
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->restrictOnDelete()->comment('班次');
            $table->date('date')->comment('日期');
            $table->dateTime('plan_check_in_at')->nullable()->comment('计划签到');
            $table->dateTime('plan_check_out_at')->nullable()->comment('计划签退');
            $table->dateTime('check_in_at')->nullable()->comment('签到时间');
            $table->dateTime('check_out_at')->nullable()->comment('签退时间');
            $table->integer('late_minutes')->default(0)->comment('迟到分钟');
            $table->integer('early_leave_minutes')->default(0)->comment('早退分钟');
            $table->string('mark', 8)->nullable()->comment('导出符号');
            $table->integer('returned_cards')->nullable()->comment('归还房卡数量');
            $table->integer('returned_keys')->nullable()->comment('归还钥匙数量');
            $table->boolean('is_confirmed')->default(0)->comment('是否已确认物品交接核对');
            $table->foreignId('leave_id')->nullable()->constrained('leaves')->nullOnDelete()->comment('关联请假');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'date', 'shift_id']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
