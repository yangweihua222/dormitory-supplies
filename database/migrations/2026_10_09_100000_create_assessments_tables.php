<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 考核管理（需求 10）：月度考核 / 考核申诉 / 同行评议。
 * 同步把三个新菜单注册进 menus 字典并授权给已有用户（考核面向全体前台人员，与原型一致）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->comment('被考核员工');
            $table->string('month', 7)->comment('考核月份 YYYY-MM');
            $table->date('assess_date')->comment('考核日期');
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->comment('直属主管');
            $table->json('items')->comment('评分明细 [{ded,bon,note}×5]，与字典模块顺序对齐');
            $table->decimal('total', 5, 1)->default(0)->comment('最终得分');
            $table->string('grade', 10)->comment('考核等级：优秀/合格/基本合格/不合格');
            $table->string('remark', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'month'], 'assessments_user_month_unique');
        });

        Schema::create('assessment_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->comment('申诉员工');
            $table->string('month', 7)->comment('考核月份 YYYY-MM');
            $table->string('matter', 1000)->comment('申诉事项');
            $table->dateTime('appeal_date')->comment('申诉日期');
            $table->string('result', 1000)->nullable()->comment('处理结果');
            $table->string('rectify', 500)->nullable()->comment('自我整改措施');
            $table->string('status', 10)->default('pending')->comment('pending 申诉中 / done 已完成');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('peer_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reviewer_id')->constrained('users')->comment('评议人');
            $table->foreignId('reviewee_id')->constrained('users')->comment('被评议人');
            $table->string('month', 7)->comment('考核月份 YYYY-MM');
            $table->json('scores')->comment('5 维度得分（0–10）');
            $table->unsignedSmallInteger('total')->default(0)->comment('总分（满分 50）');
            $table->string('remark', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // —— 注册菜单字典（sort 接在工作管理之后；工作管理挪到 23）——
        $now = now();
        DB::table('menus')->where('key', 'work')->update(['sort' => 23]);
        $menus = [
            ['key' => 'assess', 'label' => '月度考核', 'icon' => 'list', 'sort' => 20],
            ['key' => 'assess-appeal', 'label' => '考核申诉', 'icon' => 'edit', 'sort' => 21],
            ['key' => 'peer-review', 'label' => '同行评议', 'icon' => 'users', 'sort' => 22],
        ];
        $newIds = [];
        foreach ($menus as $m) {
            $existing = DB::table('menus')->where('key', $m['key'])->first();
            if ($existing) {
                DB::table('menus')->where('id', $existing->id)->update($m);
                $newIds[] = $existing->id;
            } else {
                $newIds[] = DB::table('menus')->insertGetId($m + ['created_at' => $now, 'updated_at' => $now]);
            }
        }
        // 授权给全部已有用户（考核面向全体前台人员；管理员本就全量）
        $userIds = DB::table('users')->pluck('id');
        foreach ($userIds as $uid) {
            foreach ($newIds as $mid) {
                DB::table('user_menu_permissions')->updateOrInsert(
                    ['user_id' => $uid, 'menu_id' => $mid],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('peer_reviews');
        Schema::dropIfExists('assessment_appeals');
        Schema::dropIfExists('assessments');
        $ids = DB::table('menus')->whereIn('key', ['assess', 'assess-appeal', 'peer-review'])->pluck('id');
        DB::table('menu_user')->whereIn('menu_id', $ids)->delete();
        DB::table('menus')->whereIn('id', $ids)->delete();
        DB::table('menus')->where('key', 'work')->update(['sort' => 20]);
    }
};
