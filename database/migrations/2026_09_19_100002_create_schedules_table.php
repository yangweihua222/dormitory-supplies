<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->comment('人员');
            $table->date('date')->comment('日期');
            $table->foreignId('shift_id')->constrained('shifts')->restrictOnDelete()->comment('班次');
            $table->enum('status', ['normal', 'leave'])->default('normal')->comment('状态：正常/请假');
            $table->foreignId('leave_id')->nullable()->constrained('leaves')->nullOnDelete()->comment('关联请假');
            $table->timestamps();
            $table->softDeletes();

            $table->index('date');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
