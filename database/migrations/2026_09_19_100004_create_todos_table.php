<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('todos', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['handover', 'repair', 'scrap', 'handover_overdue', 'qty_mismatch'])->comment('类型');
            $table->string('title', 255)->comment('标题');
            $table->text('content')->nullable()->comment('内容');
            $table->enum('status', ['pending', 'completed'])->default('pending')->comment('状态');
            $table->morphs('source'); // source_type, source_id
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('创建人');
            $table->dateTime('first_handover_at')->nullable()->comment('首次交接时间（用于超时计算）');
            $table->dateTime('completed_at')->nullable()->comment('完成时间');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status']);
            $table->index('source_type');
            $table->index('source_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todos');
    }
};
