<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 物资列表 / 台账（需求 §8 子模块①）
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', ['fixed', 'consumable'])->comment('种类：固定资产/耗材');
            $table->enum('consumable_sub', ['appliance', 'bedding', 'cleaning', 'toiletries', 'prize', 'other'])
                ->nullable()->comment('耗材子类（kind=consumable 时必填）');
            $table->string('name', 100)->comment('名称');
            $table->unsignedSmallInteger('purchase_year')->comment('采购年限');
            $table->unsignedSmallInteger('scrap_year')->nullable()->comment('预计报废年限');
            $table->boolean('scrapped')->default(false)->comment('是否已报废');
            $table->unsignedSmallInteger('actual_scrap_year')->nullable()->comment('实际报废年限');
            $table->string('location', 100)->comment('放置地点（房间号/库房/公共厨房/前台/办公室）');
            $table->enum('use_type', ['office', 'life'])->comment('用途：办公/生活');
            $table->string('furniture_kind', 50)->nullable()->comment('家具种类：床类/桌椅类/柜类/其他');
            $table->string('code', 50)->nullable()->comment('资产编号');
            $table->unsignedInteger('qty')->default(0)->comment('数量');
            $table->date('stock_in_at')->nullable()->comment('入库时间');
            $table->string('stock_in_by', 50)->nullable()->comment('入库人');
            $table->date('stock_out_at')->nullable()->comment('出库时间');
            $table->string('stock_out_by', 50)->nullable()->comment('出库人');
            $table->integer('stock')->default(0)->comment('库存数量');
            $table->foreignId('created_by')->nullable()->comment('创建人');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kind', 'location']);
            $table->index('scrapped');
        });

        // 入库 / 出库流水（需求 §8 子模块②③，一张表 direction 区分；字段对齐四份台账 Excel）
        Schema::create('material_stocks', function (Blueprint $table) {
            $table->id();
            $table->enum('direction', ['in', 'out'])->comment('方向：入库/出库');
            $table->enum('type', ['fixed', 'consumable'])->comment('类型：固定资产/耗材类');
            $table->string('category', 100)->comment('名称类别（如 办公桌 / 棉被）');
            $table->unsignedInteger('qty')->comment('数量');
            $table->string('code', 50)->nullable()->comment('资产编号（仅固定资产）');
            $table->date('stock_date')->comment('入库/出库日期');
            $table->string('manager', 50)->comment('管理人');
            $table->string('location', 100)->comment('存放地点（入库）/ 使用地点（出库）');
            $table->string('remark', 500)->nullable()->comment('备注');
            $table->foreignId('created_by')->nullable()->comment('创建人');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['direction', 'type']);
            $table->index('stock_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_stocks');
        Schema::dropIfExists('materials');
    }
};
