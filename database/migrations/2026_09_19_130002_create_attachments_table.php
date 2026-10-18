<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 附件（需求 §4.1 请假图片、§3.2 值班日志异常图片、§7.1 安全事件图片共用）
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('related_type', 50)->comment('业务类型：leave / duty_log / safety_check');
            $table->unsignedBigInteger('related_id')->comment('业务记录 id');
            $table->string('path', 255)->comment('相对存储路径');
            $table->string('original_name', 255)->nullable()->comment('原始文件名');
            $table->unsignedInteger('size')->nullable()->comment('字节大小');
            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete()->comment('上传人');
            $table->timestamps();

            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
