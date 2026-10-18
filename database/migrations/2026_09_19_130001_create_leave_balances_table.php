<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 年假额度（需求 §4.2 假勤管理）
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->comment('人员');
            $table->decimal('total_days', 5, 1)->default(0)->comment('年假总天数');
            $table->decimal('remain_days', 5, 1)->default(0)->comment('剩余天数');
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
