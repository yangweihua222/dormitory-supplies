<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->comment('请假人');
            $table->dateTime('applied_at')->comment('申请时间');
            $table->enum('type', ['annual', 'personal', 'sick'])->comment('类型：年假/事假/病假');
            $table->string('reason', 500)->nullable()->comment('理由');
            $table->date('start_date')->comment('请假开始日期');
            $table->date('end_date')->comment('请假结束日期');
            $table->foreignId('substitute_user_id')->nullable()->constrained('users')->nullOnDelete()->comment('替班人员');
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete()->comment('审批人');
            $table->enum('approve_status', ['pending', 'approved', 'rejected'])->default('pending')->comment('审批状态');
            $table->dateTime('approved_at')->nullable()->comment('审批时间');
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaves');
    }
};
