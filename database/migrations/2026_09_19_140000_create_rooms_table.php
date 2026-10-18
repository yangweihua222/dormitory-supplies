<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('room_code', 50)->unique()->comment('房间编码');
            $table->string('room_no', 50)->comment('房间号');
            $table->enum('type', ['dorm', 'kitchen', 'warehouse', 'drying', 'office', 'public'])
                ->comment('类型：宿舍/公共厨房/库房/晾衣间/办公室/公共区域');
            $table->unsignedInteger('beds')->default(0)->comment('床位数');
            $table->string('remark', 500)->nullable()->comment('备注（原型表单有此字段）');
            $table->timestamps();
            $table->softDeletes();

            $table->index('room_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
