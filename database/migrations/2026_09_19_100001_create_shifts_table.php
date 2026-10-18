<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('班次名称');
            $table->time('check_in_time')->comment('签到时间');
            $table->time('check_out_time')->comment('签退时间');
            $table->enum('type', ['duty', 'normal'])->default('normal')->comment('班次类型：值班/正常班');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
