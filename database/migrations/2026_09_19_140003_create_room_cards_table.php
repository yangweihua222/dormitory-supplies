<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 单表 + type 区分三种业务，差异字段可空
        Schema::create('room_cards', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['borrow', 'resell', 'checkout'])->comment('借用 / 代售 / 退宿');
            $table->date('happened_at')->nullable()->comment('业务日期（数据库设计未列，原型表格首列）');
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete()->comment('房间号');
            $table->string('total_card', 50)->nullable()->comment('房间总卡（借用）');
            $table->string('student_no', 50)->nullable()->comment('学号');
            $table->string('name', 50)->nullable()->comment('借卡人姓名');
            $table->string('contact', 50)->nullable()->comment('联系方式');
            $table->boolean('is_returned')->nullable()->comment('房卡交回情况：1 已还 / 0 未还');
            $table->enum('pay_method', ['alipay', 'wechat'])->nullable()->comment('支付方式（代售）');
            $table->unsignedInteger('quantity')->nullable()->comment('数量（代售 / 退宿）');
            $table->enum('card_status', ['disabled', 'active'])->nullable()->comment('卡状态：停用 / 未停用');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'happened_at']);
            $table->index('room_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_cards');
    }
};
