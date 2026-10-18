<?php

use App\Enums\Identity;
use App\Http\Controllers\Api\AccommodationController;
use App\Http\Controllers\Api\AssessmentAppealController;
use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DutyLogController;
use App\Http\Controllers\Api\FireTrainingController;
use App\Http\Controllers\Api\FireVolunteerController;
use App\Http\Controllers\Api\LeaveBalanceController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\MaterialStockController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\PeerReviewController;
use App\Http\Controllers\Api\RepairController;
use App\Http\Controllers\Api\RoomCardController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\SafetyCheckController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\TodoController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkController;
use Illuminate\Support\Facades\Route;

// 认证（技术方案 §4.1）
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);

    // 字典：前端下拉选项与后端同源（技术方案 §4.5）
    Route::get('/dicts', fn () => response()->json([
        'code' => 0,
        'message' => 'ok',
        'data' => ['identity' => Identity::options()],
    ]));

    // 工作台（全员可见）
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // 考勤签到（工作台入口，全员可用）
    Route::post('/attendance/sign-in', [AttendanceController::class, 'signIn']);
    Route::post('/attendance/sign-out', [AttendanceController::class, 'signOut']);
    Route::get('/attendance/today', [AttendanceController::class, 'today']);

    // 考勤统计（需勾选「考勤统计」菜单权限）
    Route::get('/attendance', [AttendanceController::class, 'index'])->middleware('menu:attendance');
    Route::get('/attendance/stats', [AttendanceController::class, 'stats'])->middleware('menu:attendance');
    Route::get('/attendance/export', [AttendanceController::class, 'export'])->middleware('menu:attendance');

    // 班次字典（排班管理下拉依赖，登录即可访问）
    Route::get('/shifts/options', [ShiftController::class, 'options']);

    // 班次管理（需勾选「班次管理」菜单权限）
    Route::apiResource('shifts', ShiftController::class)
        ->except(['show'])
        ->middleware('menu:shift');

    // 排班管理（需勾选「排班管理」菜单权限）
    Route::get('/schedules', [ScheduleController::class, 'index'])->middleware('menu:schedule');
    Route::get('/schedules/export', [ScheduleController::class, 'export'])->middleware('menu:schedule');
    Route::post('/schedules', [ScheduleController::class, 'store'])->middleware('menu:schedule');
    Route::put('/schedules/{schedule}', [ScheduleController::class, 'update'])->middleware('menu:schedule');
    Route::delete('/schedules/{schedule}', [ScheduleController::class, 'destroy'])->middleware('menu:schedule');
    Route::post('/schedules/batch', [ScheduleController::class, 'batch'])->middleware('menu:schedule');
    Route::get('/schedules/template', [ScheduleController::class, 'template'])->middleware('menu:schedule');
    Route::post('/schedules/import', [ScheduleController::class, 'import'])->middleware('menu:schedule');

    // 排班日历（需勾选「排班日历」菜单权限）
    Route::get('/schedules/calendar', [ScheduleController::class, 'calendar'])->middleware('menu:calendar');

    // 值班日志（需勾选「值班日志」菜单权限；扁平单表结构，需求 2-2）
    Route::get('/duty-logs', [DutyLogController::class, 'index'])->middleware('menu:dutylog');
    Route::get('/duty-logs/schema', [DutyLogController::class, 'schema'])->middleware('menu:dutylog');
    Route::post('/duty-logs', [DutyLogController::class, 'store'])->middleware('menu:dutylog');
    Route::put('/duty-logs/{dutyLog}', [DutyLogController::class, 'update'])->middleware('menu:dutylog');
    Route::delete('/duty-logs/{dutyLog}', [DutyLogController::class, 'destroy'])->middleware('menu:dutylog');
    Route::post('/duty-logs/{dutyLog}/handover', [DutyLogController::class, 'handover'])->middleware('menu:dutylog');
    Route::post('/duty-logs/upload', [DutyLogController::class, 'upload'])->middleware('menu:dutylog');
    Route::get('/duty-logs/image', [DutyLogController::class, 'file'])->middleware('menu:dutylog');

    // 请假列表（需勾选「请假列表」菜单权限；需求 §4.1）
    Route::get('/leaves', [LeaveController::class, 'index'])->middleware('menu:leave');
    Route::get('/leaves/export', [LeaveController::class, 'export'])->middleware('menu:leave');
    Route::get('/leaves/options', [LeaveController::class, 'options']); // 字典：登录即可
    Route::get('/leaves/annual-remain', [LeaveController::class, 'annualRemain'])->middleware('menu:leave');
    Route::post('/leaves', [LeaveController::class, 'store'])->middleware('menu:leave');
    Route::put('/leaves/{leave}', [LeaveController::class, 'update'])->middleware('menu:leave');
    Route::delete('/leaves/{leave}', [LeaveController::class, 'destroy'])->middleware('menu:leave');
    Route::post('/leaves/{leave}/approve', [LeaveController::class, 'approve'])->middleware('menu:leave');
    Route::post('/leaves/{leave}/reject', [LeaveController::class, 'reject'])->middleware('menu:leave');
    Route::post('/leaves/upload', [LeaveController::class, 'upload'])->middleware('menu:leave');
    Route::get('/leaves/image', [LeaveController::class, 'image'])->middleware('menu:leave');

    // 假勤管理（需勾选「假勤管理」菜单权限；需求 §4.2）
    Route::get('/leave-balances', [LeaveBalanceController::class, 'index'])->middleware('menu:leavebalance');
    Route::get('/leave-balances/options', [LeaveBalanceController::class, 'options']); // 字典：登录即可
    Route::post('/leave-balances', [LeaveBalanceController::class, 'store'])->middleware('menu:leavebalance');
    Route::put('/leave-balances/{balance}', [LeaveBalanceController::class, 'update'])->middleware('menu:leavebalance');
    Route::delete('/leave-balances/{balance}', [LeaveBalanceController::class, 'destroy'])->middleware('menu:leavebalance');

    // 房源管理（需勾选「房源管理」菜单权限；需求 §5.1）
    Route::get('/rooms', [RoomController::class, 'index'])->middleware('menu:room');
    // 房间字典：住宿管理 / 房卡管理的下拉依赖，登录即可访问
    Route::get('/rooms/all', [RoomController::class, 'all']);
    Route::get('/rooms/options', [RoomController::class, 'options']);
    Route::post('/rooms', [RoomController::class, 'store'])->middleware('menu:room');
    Route::put('/rooms/{room}', [RoomController::class, 'update'])->middleware('menu:room');
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->middleware('menu:room');

    // 住宿管理（需勾选「住宿管理」菜单权限；需求 §5.2）
    Route::get('/accommodations', [AccommodationController::class, 'index'])->middleware('menu:accommodation');
    Route::get('/accommodations/export', [AccommodationController::class, 'export'])->middleware('menu:accommodation');
    Route::get('/accommodations/rooms/{room}/detail', [AccommodationController::class, 'detail'])->middleware('menu:accommodation');
    Route::get('/accommodations/rooms/{room}/history', [AccommodationController::class, 'history'])->middleware('menu:accommodation');
    Route::post('/accommodations', [AccommodationController::class, 'store'])->middleware('menu:accommodation');
    Route::put('/accommodations/{accommodation}', [AccommodationController::class, 'update'])->middleware('menu:accommodation');
    Route::delete('/accommodations/{accommodation}', [AccommodationController::class, 'destroy'])->middleware('menu:accommodation');
    Route::post('/accommodations/{accommodation}/handover', [AccommodationController::class, 'handover'])->middleware('menu:accommodation');

    // 房卡管理（需勾选「房卡管理」菜单权限；需求 §5.3）
    Route::get('/room-cards', [RoomCardController::class, 'index'])->middleware('menu:card');
    Route::post('/room-cards', [RoomCardController::class, 'store'])->middleware('menu:card');
    Route::put('/room-cards/{roomCard}', [RoomCardController::class, 'update'])->middleware('menu:card');
    Route::delete('/room-cards/{roomCard}', [RoomCardController::class, 'destroy'])->middleware('menu:card');

    // 报修管理（需勾选「报修管理」菜单权限；需求 §6）
    Route::get('/repairs', [RepairController::class, 'index'])->middleware('menu:repair');
    Route::get('/repairs/options', [RepairController::class, 'options']); // 字典：登录即可
    Route::get('/repairs/export', [RepairController::class, 'export'])->middleware('menu:repair');
    Route::post('/repairs', [RepairController::class, 'store'])->middleware('menu:repair');
    Route::put('/repairs/{repair}', [RepairController::class, 'update'])->middleware('menu:repair');
    Route::delete('/repairs/{repair}', [RepairController::class, 'destroy'])->middleware('menu:repair');

    // 安全管理 · 检查列表（需求 §7.1，含前 7 个子类；原型无导入/模板，P3 提供导出）
    Route::get('/safety-checks', [SafetyCheckController::class, 'index'])->middleware('menu:safety');
    Route::get('/safety-checks/export', [SafetyCheckController::class, 'export'])->middleware('menu:safety');
    Route::get('/safety-checks/options', [SafetyCheckController::class, 'options']);
    Route::post('/safety-checks', [SafetyCheckController::class, 'store'])->middleware('menu:safety');
    Route::put('/safety-checks/{safetyCheck}', [SafetyCheckController::class, 'update'])->middleware('menu:safety');
    Route::delete('/safety-checks/{safetyCheck}', [SafetyCheckController::class, 'destroy'])->middleware('menu:safety');
    Route::post('/safety-checks/upload', [SafetyCheckController::class, 'upload'])->middleware('menu:safety');
    Route::get('/safety-checks/image', [SafetyCheckController::class, 'image'])->middleware('menu:safety');

    // 安全管理 · 消防志愿服务（需求 §7.2；原型无导出，P3 提供导出）
    Route::get('/fire-volunteers', [FireVolunteerController::class, 'index'])->middleware('menu:volunteer');
    Route::get('/fire-volunteers/export', [FireVolunteerController::class, 'export'])->middleware('menu:volunteer');
    Route::get('/fire-volunteers/options', [FireVolunteerController::class, 'options']);
    Route::post('/fire-volunteers', [FireVolunteerController::class, 'store'])->middleware('menu:volunteer');
    Route::put('/fire-volunteers/{volunteer}', [FireVolunteerController::class, 'update'])->middleware('menu:volunteer');
    Route::delete('/fire-volunteers/{volunteer}', [FireVolunteerController::class, 'destroy'])->middleware('menu:volunteer');
    Route::post('/fire-volunteers/import', [FireVolunteerController::class, 'import'])->middleware('menu:volunteer');
    Route::get('/fire-volunteers/template', [FireVolunteerController::class, 'template'])->middleware('menu:volunteer');

    // 安全管理 · 消防培训（需求 §7.3；原型仅「导入 Excel」）
    Route::get('/fire-trainings', [FireTrainingController::class, 'index'])->middleware('menu:training');
    Route::get('/fire-trainings/export', [FireTrainingController::class, 'export'])->middleware('menu:training');
    Route::get('/fire-trainings/options', [FireTrainingController::class, 'options']);
    Route::post('/fire-trainings', [FireTrainingController::class, 'store'])->middleware('menu:training');
    Route::put('/fire-trainings/{training}', [FireTrainingController::class, 'update'])->middleware('menu:training');
    Route::delete('/fire-trainings/{training}', [FireTrainingController::class, 'destroy'])->middleware('menu:training');
    Route::post('/fire-trainings/import', [FireTrainingController::class, 'import'])->middleware('menu:training');
    Route::get('/fire-trainings/template', [FireTrainingController::class, 'template'])->middleware('menu:training');

    // 物资管理 · 物资列表（需求 §8 子模块①；保存含房间配置数量校验 → 数量不符待办）
    Route::get('/materials', [MaterialController::class, 'index'])->middleware('menu:material');
    Route::get('/materials/export', [MaterialController::class, 'export'])->middleware('menu:material');
    Route::get('/materials/options', [MaterialController::class, 'options']);
    Route::post('/materials', [MaterialController::class, 'store'])->middleware('menu:material');
    Route::put('/materials/{material}', [MaterialController::class, 'update'])->middleware('menu:material');
    Route::delete('/materials/{material}', [MaterialController::class, 'destroy'])->middleware('menu:material');

    // 物资管理 · 入库管理（需求 §8 子模块②）
    Route::get('/material-stocks/in', [MaterialStockController::class, 'index'])->defaults('direction', 'in')->middleware('menu:inbound');
    Route::get('/material-stocks/in/export', [MaterialStockController::class, 'export'])->defaults('direction', 'in')->middleware('menu:inbound');
    Route::post('/material-stocks/in', [MaterialStockController::class, 'store'])->defaults('direction', 'in')->middleware('menu:inbound');
    Route::put('/material-stocks/in/{stock}', [MaterialStockController::class, 'update'])->defaults('direction', 'in')->middleware('menu:inbound');
    Route::delete('/material-stocks/in/{stock}', [MaterialStockController::class, 'destroy'])->defaults('direction', 'in')->middleware('menu:inbound');

    // 物资管理 · 出库管理（需求 §8 子模块③）
    Route::get('/material-stocks/out', [MaterialStockController::class, 'index'])->defaults('direction', 'out')->middleware('menu:outbound');
    Route::get('/material-stocks/out/export', [MaterialStockController::class, 'export'])->defaults('direction', 'out')->middleware('menu:outbound');
    Route::post('/material-stocks/out', [MaterialStockController::class, 'store'])->defaults('direction', 'out')->middleware('menu:outbound');
    Route::put('/material-stocks/out/{stock}', [MaterialStockController::class, 'update'])->defaults('direction', 'out')->middleware('menu:outbound');
    Route::delete('/material-stocks/out/{stock}', [MaterialStockController::class, 'destroy'])->defaults('direction', 'out')->middleware('menu:outbound');

    // 入出库字典（下拉依赖，登录即可访问）
    Route::get('/material-stocks/options', [MaterialStockController::class, 'options']);

    // 工作管理（需求 §9 年度统计 Dashboard，只读）
    Route::get('/work/stats', [WorkController::class, 'stats'])->middleware('menu:work');
    // 工作管理 · 月度考核统计 + 图表导出（需求 10.4 / 原型工作管理）
    Route::get('/work/assess-stats', [WorkController::class, 'assessStats'])->middleware('menu:work');
    Route::get('/work/export-buy-scrap', [WorkController::class, 'exportBuyScrap'])->middleware('menu:work');
    Route::get('/work/export-in-use', [WorkController::class, 'exportInUse'])->middleware('menu:work');

    // 考核管理 · 月度考核（需求 10.1；管理员可写，其他人只读本人）
    Route::get('/assessments', [AssessmentController::class, 'index'])->middleware('menu:assess');
    Route::get('/assessments/export', [AssessmentController::class, 'export'])->middleware('menu:assess');
    Route::get('/assessments/options', [AssessmentController::class, 'options']);
    Route::post('/assessments', [AssessmentController::class, 'store'])->middleware('menu:assess');
    Route::put('/assessments/{assessment}', [AssessmentController::class, 'update'])->middleware('menu:assess');
    Route::delete('/assessments/{assessment}', [AssessmentController::class, 'destroy'])->middleware('menu:assess');

    // 考核管理 · 考核申诉（需求 10.2；管理员可写，其他人只读本人）
    Route::get('/assessment-appeals', [AssessmentAppealController::class, 'index'])->middleware('menu:assess-appeal');
    Route::get('/assessment-appeals/export', [AssessmentAppealController::class, 'export'])->middleware('menu:assess-appeal');
    Route::post('/assessment-appeals', [AssessmentAppealController::class, 'store'])->middleware('menu:assess-appeal');
    Route::put('/assessment-appeals/{appeal}', [AssessmentAppealController::class, 'update'])->middleware('menu:assess-appeal');
    Route::delete('/assessment-appeals/{appeal}', [AssessmentAppealController::class, 'destroy'])->middleware('menu:assess-appeal');

    // 考核管理 · 同行评议（需求 10.3；全员可发起，仅本人评议或管理员可改删）
    Route::get('/peer-reviews', [PeerReviewController::class, 'index'])->middleware('menu:peer-review');
    Route::get('/peer-reviews/options', [PeerReviewController::class, 'options']);
    Route::post('/peer-reviews', [PeerReviewController::class, 'store'])->middleware('menu:peer-review');
    Route::put('/peer-reviews/{peerReview}', [PeerReviewController::class, 'update'])->middleware('menu:peer-review');
    Route::delete('/peer-reviews/{peerReview}', [PeerReviewController::class, 'destroy'])->middleware('menu:peer-review');

    // 待办（工作台入口）
    Route::get('/todos', [TodoController::class, 'index']);
    Route::post('/todos/{todo}/complete', [TodoController::class, 'complete']);
    Route::post('/todos/complete-all', [TodoController::class, 'completeAll']);

    // 人员字典（跨模块下拉用：值班日志 / 排班 / 考勤 / 假勤都要选人）
    // 只校验登录，不占用「用户管理」菜单权限，否则其他模块会误报无权限
    Route::get('/users/options', [UserController::class, 'options']);

    // 用户管理（需勾选「用户管理」菜单权限）
    Route::get('/menus', [MenuController::class, 'index'])->middleware('menu:user');
    Route::apiResource('users', UserController::class)
        ->except(['show'])
        ->middleware('menu:user');
});
