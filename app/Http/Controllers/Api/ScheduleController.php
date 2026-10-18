<?php

namespace App\Http\Controllers\Api;

use App\Enums\ScheduleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleBatchRequest;
use App\Http\Requests\ScheduleStoreRequest;
use App\Models\Schedule;
use App\Models\Shift;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\ExportsExcel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ScheduleController extends Controller
{
    use ApiResponse;
    use ExportsExcel;

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Schedule::with(['user', 'shift']);

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->input('start_date'), $request->input('end_date')]);
        }

        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->input('shift_id'));
        }

        if ($kw = trim((string) $request->input('keyword', ''))) {
            $query->whereHas('user', fn ($q) => $q->where('username', 'like', "%{$kw}%"));
        }

        $paginator = $query->orderBy('date', 'desc')
            ->orderBy('user_id')
            ->paginate((int) $request->input('pageSize', 10));

        $paginator->getCollection()->transform(fn ($s) => $this->scheduleToRow($s));

        return $this->page($paginator);
    }

    /** GET /api/schedules/export 排班导出（复用列表筛选） */
    public function export(Request $request)
    {
        $user = $request->user();
        $query = Schedule::with(['user', 'shift']);

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->input('start_date'), $request->input('end_date')]);
        }
        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->input('shift_id'));
        }
        if ($kw = trim((string) $request->input('keyword', ''))) {
            $query->whereHas('user', fn ($q) => $q->where('username', 'like', "%{$kw}%"));
        }

        $rows = $query->orderByDesc('date')->orderBy('user_id')->get()
            ->map(fn ($s) => [
                $s->date->format('Y-m-d'),
                $s->user?->username,
                $s->shift?->name,
                $s->shift?->check_in_time?->format('H:i'),
                $s->shift?->check_out_time?->format('H:i'),
                $s->status->label(),
            ])->all();

        return $this->xlsxDownload('排班导出', ['日期', '姓名', '班次', '上班时间', '下班时间', '状态'], $rows);
    }

    public function store(ScheduleStoreRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();

        if (! $user->isAdmin() && $data['user_id'] != $user->id) {
            return $this->fail(1002, '无权为他人排班');
        }

        if (Schedule::where('user_id', $data['user_id'])->where('date', $data['date'])->exists()) {
            return $this->fail(3001, '该人员当日已有排班');
        }

        $schedule = Schedule::create([
            'user_id' => $data['user_id'],
            'date' => $data['date'],
            'shift_id' => $data['shift_id'],
            'status' => ScheduleStatus::NORMAL,
        ]);

        return $this->ok($this->scheduleToRow($schedule->load(['user', 'shift'])), '排班成功');
    }

    public function update(ScheduleStoreRequest $request, Schedule $schedule)
    {
        $data = $request->validated();
        $user = $request->user();

        if (! $user->isAdmin() && $schedule->user_id != $user->id) {
            return $this->fail(1002, '无权修改他人排班');
        }

        $exists = Schedule::where('user_id', $data['user_id'])
            ->where('date', $data['date'])
            ->where('id', '!=', $schedule->id)
            ->exists();

        if ($exists) {
            return $this->fail(3001, '该人员当日已有排班');
        }

        $schedule->update($data);

        return $this->ok($this->scheduleToRow($schedule->load(['user', 'shift'])), '更新成功');
    }

    public function destroy(Request $request, Schedule $schedule)
    {
        if (! $request->user()->isAdmin() && $schedule->user_id != $request->user()->id) {
            return $this->fail(1002, '无权删除他人排班');
        }

        $schedule->delete();

        return $this->ok(null, '删除成功');
    }

    public function batch(ScheduleBatchRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();
        $userIds = $data['user_ids'];

        if (! $user->isAdmin()) {
            $userIds = array_filter($userIds, fn ($id) => $id == $user->id);
            if (empty($userIds)) {
                return $this->fail(1002, '无权为他人排班');
            }
        }

        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($userIds, $start, $end, $data, &$created, &$skipped) {
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                foreach ($userIds as $userId) {
                    if (Schedule::where('user_id', $userId)->where('date', $date->format('Y-m-d'))->exists()) {
                        $skipped++;

                        continue;
                    }
                    Schedule::create([
                        'user_id' => $userId,
                        'date' => $date->format('Y-m-d'),
                        'shift_id' => $data['shift_id'],
                        'status' => ScheduleStatus::NORMAL,
                    ]);
                    $created++;
                }
            }
        });

        return $this->ok(['created' => $created, 'skipped' => $skipped], '批量排班完成');
    }

    public function calendar(Request $request)
    {
        $year = (int) $request->input('year', Carbon::now()->year);
        $month = (int) $request->input('month', Carbon::now()->month);
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $user = $request->user();
        $query = Schedule::with(['user', 'shift'])
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        $schedules = $query->get()
            ->groupBy(fn ($s) => $s->date->format('Y-m-d'));

        $days = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->format('Y-m-d');
            $daySchedules = $schedules->get($dateStr, collect());
            $sorted = $daySchedules->sortBy(fn ($s) => $s->shift->check_in_time)->values();

            $days[] = [
                'date' => $dateStr,
                'cells' => $sorted->map(fn ($s) => [
                    'user_id' => $s->user_id,
                    'username' => $s->user?->username,
                    'shift_id' => $s->shift_id,
                    'shift_name' => $s->shift?->name,
                    'status' => $s->status->value,
                    'status_label' => $s->status->label(),
                ])->toArray(),
            ];
        }

        return $this->ok([
            'year' => $year,
            'month' => $month,
            'days' => $days,
        ]);
    }

    /**
     * 下载导入模板（动态生成，班次下拉与系统现有班次一致）
     * GET /api/schedules/template
     */
    public function template(Request $request)
    {
        $shiftNames = Shift::orderBy('id')->pluck('name')->all();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('排班导入');

        // 表头
        $headers = ['人员名称', '日期', '班次名称'];
        foreach (['A', 'B', 'C'] as $i => $col) {
            $sheet->setCellValue($col.'1', $headers[$i]);
        }
        $headerStyle = $sheet->getStyle('A1:C1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType('solid')->getStartColor()->setRGB('E8F0FE');
        $headerStyle->getAlignment()->setHorizontal('center');
        $sheet->getColumnDimension('A')->setWidth(16);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getColumnDimension('C')->setWidth(16);

        // 班次下拉（名称不含逗号且总长 < 255 才内联到数据验证）
        if ($shiftNames && ! collect($shiftNames)->contains(fn ($n) => str_contains($n, ','))) {
            $formula = '"'.implode(',', $shiftNames).'"';
            if (strlen($formula) < 255) {
                $validation = new DataValidation;
                $validation->setType(DataValidation::TYPE_LIST)
                    ->setFormula1($formula)
                    ->setAllowBlank(false)
                    ->setShowDropDown(true)
                    ->setErrorTitle('班次名称无效')
                    ->setError('请从下拉列表中选择班次（须与「班次管理」中的名称一致）。');
                $sheet->setDataValidation('C2:C500', $validation);
            }
        }

        // 导入说明页
        $notes = $spreadsheet->createSheet();
        $notes->setTitle('导入说明');
        $lines = [
            '排班(排表) Excel 导入说明',
            '',
            '1. 请按「排班导入」页的列顺序填写，不要增删列、不要改列名。',
            '2. 表头（第 1 行）为字段名，请勿删除；数据从第 2 行开始填写。',
            '3. 必填项：人员名称、日期、班次名称。',
            '4. 日期格式：YYYY-MM-DD（如 2026-09-01）。',
            '5. 班次名称：从「排班导入」页的下拉列表选择，须与「班次管理」中的班次名称一致。当前可选班次：'.($shiftNames ? implode('、', $shiftNames) : '（暂无，请先到「班次管理」创建）'),
            '6. 人员名称：须为系统内的工作人员姓名（与「用户管理」中的用户名一致）。',
            '7. 同一人同一日期重复：以本次导入为准（覆盖原有排班）。',
            '8. 填写完成后，在「排班管理」页面点击「导入」上传本文件。',
        ];
        foreach ($lines as $i => $line) {
            $notes->setCellValue('A'.($i + 1), $line);
        }
        $notes->getStyle('A1')->getFont()->setBold(true);
        $notes->getColumnDimension('A')->setWidth(90);

        $fileName = '排班导入模板.xlsx';
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

        return response()->streamDownload(function () use ($writer, $spreadsheet) {
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Excel 导入排班
     * POST /api/schedules/import
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ], [
            'file.required' => '请选择要导入的 Excel 文件',
            'file.mimes' => '仅支持 .xlsx / .xls 格式文件',
        ]);

        $user = $request->user();

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        } catch (\Throwable $e) {
            return $this->fail(5002, '文件无法解析，请先下载模板并按格式填写');
        }

        $sheet = $spreadsheet->sheetNameExists('排班导入')
            ? $spreadsheet->getSheetByName('排班导入')
            : $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, true, true);

        $users = User::pluck('id', 'username');
        $shifts = Shift::pluck('id', 'name');

        $created = 0;
        $updated = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $users, $shifts, $user, &$created, &$updated, &$errors) {
            foreach ($rows as $line => $row) {
                if ($line === 1) {
                    continue; // 表头
                }

                $username = trim((string) ($row['A'] ?? ''));
                $dateRaw = $row['B'] ?? null;
                $shiftName = trim((string) ($row['C'] ?? ''));

                // 整行为空则跳过
                if ($username === '' && $this->normalizeDate($dateRaw) === null && $shiftName === '') {
                    continue;
                }

                if ($username === '') {
                    $errors[] = "第{$line}行：人员名称为空";

                    continue;
                }
                $date = $this->normalizeDate($dateRaw);
                if ($date === null) {
                    $errors[] = "第{$line}行：日期「{$dateRaw}」无法识别，应为 YYYY-MM-DD";

                    continue;
                }
                if ($shiftName === '') {
                    $errors[] = "第{$line}行：班次名称为空";

                    continue;
                }
                $userId = $users->get($username);
                if (! $userId) {
                    $errors[] = "第{$line}行：人员「{$username}」不存在";

                    continue;
                }
                if (! $user->isAdmin() && $userId != $user->id) {
                    $errors[] = "第{$line}行：无权为「{$username}」排班";

                    continue;
                }
                $shiftId = $shifts->get($shiftName);
                if (! $shiftId) {
                    $errors[] = "第{$line}行：班次「{$shiftName}」不存在，请与「班次管理」中的名称一致";

                    continue;
                }

                $schedule = Schedule::where('user_id', $userId)->where('date', $date)->first();
                if ($schedule) {
                    $schedule->update(['shift_id' => $shiftId]);
                    $updated++;
                } else {
                    Schedule::create([
                        'user_id' => $userId,
                        'date' => $date,
                        'shift_id' => $shiftId,
                        'status' => ScheduleStatus::NORMAL,
                    ]);
                    $created++;
                }
            }
        });

        $spreadsheet->disconnectWorksheets();

        return $this->ok([
            'created' => $created,
            'updated' => $updated,
            'failed' => count($errors),
            'errors' => array_slice($errors, 0, 20),
        ], '导入完成');
    }

    /**
     * 容错解析日期：兼容 Excel 日期序列值与常见字符串格式
     */
    private function normalizeDate($value): ?string
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }

        $value = trim((string) $value);
        foreach (['Y-m-d', 'Y/n/j', 'Y-n-j', 'Y/m/d', 'Y年n月j日'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function scheduleToRow(Schedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'user_id' => $schedule->user_id,
            'username' => $schedule->user?->username,
            'date' => $schedule->date->format('Y-m-d'),
            'shift_id' => $schedule->shift_id,
            'shift_name' => $schedule->shift?->name,
            'shift_check_in' => $schedule->shift?->check_in_time?->format('H:i:s'),
            'shift_check_out' => $schedule->shift?->check_out_time?->format('H:i:s'),
            'status' => $schedule->status->value,
            'status_label' => $schedule->status->label(),
        ];
    }
}
