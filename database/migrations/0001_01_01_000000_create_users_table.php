<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->comment('用户名（姓名）');
            $table->string('employee_no', 32)->unique()->comment('工号');
            $table->string('phone', 20)->nullable()->comment('手机号');
            $table->string('identity', 20)->default('staff')->comment('身份：admin 管理员 / supervisor 主管 / staff 工作人员');
            $table->string('password')->comment('bcrypt');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('identity');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('employee_no')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
