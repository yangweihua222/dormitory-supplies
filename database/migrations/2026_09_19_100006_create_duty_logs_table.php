<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duty_logs', function (Blueprint $table) {
            $table->id();
            $table->date('log_date')->comment('日期');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->comment('创建人');
            $table->enum('status', ['undone', 'done'])->default('undone')->comment('状态：未完成/已完成');
            $table->boolean('is_handed_over')->default(0)->comment('是否已交接');
            $table->timestamp('handed_over_at')->nullable()->comment('交接时间');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['log_date', 'user_id']);
            $table->index('log_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_logs');
    }
};
