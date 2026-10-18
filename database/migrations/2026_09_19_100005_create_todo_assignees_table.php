<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('todo_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todo_id')->constrained('todos')->cascadeOnDelete()->comment('待办');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->comment('被指派人');
            $table->dateTime('completed_at')->nullable()->comment('该指派人的完成时间');
            $table->timestamps();

            $table->unique(['todo_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todo_assignees');
    }
};
