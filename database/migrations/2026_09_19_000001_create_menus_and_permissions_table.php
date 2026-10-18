<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 菜单表：key 与原型侧边栏 page id 一致（数据库设计 §三.2）
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique()->comment('与原型 page id 一致');
            $table->string('label', 50)->comment('菜单显示名');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('父菜单（预留）');
            $table->string('icon', 50)->nullable();
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        // 用户菜单权限：逐条勾选（数据库设计 §三.3）
        Schema::create('user_menu_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'menu_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_menu_permissions');
        Schema::dropIfExists('menus');
    }
};
