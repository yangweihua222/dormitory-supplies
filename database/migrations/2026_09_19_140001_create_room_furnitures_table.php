<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_furnitures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete()->comment('所属房间');
            $table->string('kind', 50)->comment('家具种类：床类/桌椅类/柜类/其他');
            $table->unsignedInteger('count')->default(0)->comment('配置数量');
            $table->timestamps();

            $table->unique(['room_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_furnitures');
    }
};
