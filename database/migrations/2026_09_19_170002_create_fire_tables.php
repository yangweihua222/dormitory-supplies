<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 消防志愿服务（需求 §7.2）
        Schema::create('fire_volunteers', function (Blueprint $table) {
            $table->id();
            $table->string('student_name', 50)->comment('学生姓名');
            $table->string('student_no', 50)->comment('学号');
            $table->string('grade', 20)->nullable()->comment('年级');
            $table->date('service_date')->comment('服务时间');
            $table->string('service_content', 500)->comment('服务内容');
            $table->decimal('service_hours', 5, 1)->default(0)->comment('服务时长（小时）');
            $table->string('remark', 500)->nullable()->comment('备注');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_name', 'grade']);
            $table->index('service_date');
        });

        // 消防培训（需求 §7.3）
        Schema::create('fire_trainings', function (Blueprint $table) {
            $table->id();
            $table->date('train_date')->comment('培训时间');
            $table->string('trainer_name', 50)->comment('培训人员姓名');
            $table->string('student_no', 50)->nullable()->comment('学号');
            $table->string('grade', 20)->nullable()->comment('年级');
            $table->string('train_content', 500)->comment('培训内容');
            $table->decimal('train_hours', 5, 1)->default(0)->comment('培训时长（小时）');
            $table->string('remark', 500)->nullable()->comment('备注');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['trainer_name', 'grade']);
            $table->index('train_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fire_trainings');
        Schema::dropIfExists('fire_volunteers');
    }
};
