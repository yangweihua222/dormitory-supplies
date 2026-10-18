<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FireTraining;
use App\Support\ApiResponse;
use App\Support\ExportsExcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** 消防培训（需求 §7.3）：列表支持按培训人员姓名 + 年级查询，支持 Excel 导入 */
class FireTrainingController extends Controller
{
    use ApiResponse;
    use ExportsExcel;

    public function index(Request $request): JsonResponse
    {
        $query = FireTraining::query();

        if ($name = trim((string) $request->input('name', ''))) {
            $query->where('trainer_name', 'like', "%{$name}%");
        }
        if ($grade = $request->input('grade')) {
            $query->where('grade', $grade);
        }
        if ($date = $request->input('date')) {
            $query->whereDate('train_date', $date);
        }

        $paginator = $query->orderByDesc('train_date')->orderByDesc('id')
            ->paginate((int) $request->input('pageSize', 10));

        $paginator->setCollection($paginator->getCollection()->map(fn ($t) => $this->toRow($t)));

        return $this->page($paginator);
    }

    public function options(): JsonResponse
    {
        return $this->ok(['grades' => FireVolunteerController::GRADES]);
    }

    /** GET /api/fire-trainings/export 培训导出（复用列表筛选） */
    public function export(Request $request)
    {
        $query = FireTraining::query();
        if ($name = trim((string) $request->input('name', ''))) {
            $query->where('trainer_name', 'like', "%{$name}%");
        }
        if ($grade = $request->input('grade')) {
            $query->where('grade', $grade);
        }
        if ($date = $request->input('date')) {
            $query->whereDate('train_date', $date);
        }

        $rows = $query->orderByDesc('train_date')->orderByDesc('id')->get()
            ->map(fn ($t) => [
                $t->train_date?->format('Y-m-d'),
                $t->trainer_name,
                $t->student_no,
                $t->grade,
                $t->train_content,
                (float) $t->train_hours,
                $t->remark,
                $t->creator?->username,
            ])->all();

        return $this->xlsxDownload('消防培训导出', ['日期', '培训人员', '学号', '年级', '培训内容', '培训时长(小时)', '备注', '录入人'], $rows);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(), $this->messages());
        $data['created_by'] = $request->user()->id;

        $row = FireTraining::create($data);

        return $this->ok($this->toRow($row->refresh()), '已添加');
    }

    public function update(Request $request, FireTraining $training): JsonResponse
    {
        $data = $request->validate($this->rules(), $this->messages());
        $training->fill($data)->save();

        return $this->ok($this->toRow($training->refresh()), '已保存');
    }

    public function destroy(FireTraining $training): JsonResponse
    {
        $training->delete();

        return $this->ok(null, '已删除');
    }

    /** GET /api/fire-trainings/template 导入模板 */
    public function template()
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('消防培训导入');

        $headers = ['培训时间', '培训人员姓名', '学号', '年级', '培训内容', '培训时长(小时)', '备注'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i).'1', $h);
        }
        $headerStyle = $sheet->getStyle('A1:G1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType('solid')->getStartColor()->setRGB('E8F0FE');
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setWidth($col === 'E' ? 36 : 20);
        }

        $notes = $spreadsheet->createSheet();
        $notes->setTitle('导入说明');
        $lines = [
            '消防培训 Excel 导入说明',
            '',
            '1. 请按「消防培训导入」页的列顺序填写，不要增删列、不要改列名。',
            '2. 表头（第 1 行）为字段名，请勿删除；数据从第 2 行开始填写。',
            '3. 必填项：培训时间、培训人员姓名、培训内容、培训时长。',
            '4. 培训时间格式：YYYY-MM-DD（如 2026-09-01）。',
            '5. 年级填写示例：大一 / 大二 / 研一（与页面下拉一致）。',
        ];
        foreach ($lines as $i => $line) {
            $notes->setCellValue('A'.($i + 1), $line);
        }
        $notes->getStyle('A1')->getFont()->setBold(true);
        $notes->getColumnDimension('A')->setWidth(90);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer, $spreadsheet) {
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, '消防培训导入模板.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** POST /api/fire-trainings/import 导入 */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ], [
            'file.required' => '请选择要导入的 Excel 文件',
            'file.mimes' => '仅支持 .xlsx / .xls 格式文件',
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        } catch (\Throwable $e) {
            return $this->fail(5002, '文件无法解析，请先下载模板并按格式填写');
        }

        $sheet = $spreadsheet->sheetNameExists('消防培训导入')
            ? $spreadsheet->getSheetByName('消防培训导入')
            : $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, true, true);

        $imported = 0;
        $errors = [];
        $userId = $request->user()->id;

        foreach ($rows as $line => $row) {
            if ($line === 1) {
                continue;
            }
            $name = trim((string) ($row['B'] ?? ''));
            $date = $this->normalizeDate($row['A'] ?? null);
            if ($name === '' && $date === null) {
                continue;
            }
            if ($name === '') {
                $errors[] = "第{$line}行：培训人员姓名为空";

                continue;
            }
            if ($date === null) {
                $errors[] = "第{$line}行：培训时间无法识别，应为 YYYY-MM-DD";

                continue;
            }
            if (trim((string) ($row['E'] ?? '')) === '') {
                $errors[] = "第{$line}行：培训内容为空";

                continue;
            }

            FireTraining::create([
                'train_date' => $date,
                'trainer_name' => $name,
                'student_no' => trim((string) ($row['C'] ?? '')) ?: null,
                'grade' => trim((string) ($row['D'] ?? '')) ?: null,
                'train_content' => trim((string) ($row['E'] ?? '')),
                'train_hours' => is_numeric($row['F'] ?? null) ? (float) $row['F'] : 0,
                'remark' => trim((string) ($row['G'] ?? '')) ?: null,
                'created_by' => $userId,
            ]);
            $imported++;
        }

        if ($imported === 0) {
            return $this->fail(5003, '未导入任何数据'.($errors ? '：'.implode('；', array_slice($errors, 0, 5)) : ''), 200, ['errors' => $errors]);
        }

        return $this->ok(['imported' => $imported, 'errors' => $errors], "已导入 {$imported} 条");
    }

    private function rules(): array
    {
        return [
            'train_date' => ['required', 'date'],
            'trainer_name' => ['required', 'string', 'max:50'],
            'student_no' => ['nullable', 'string', 'max:50'],
            'grade' => ['nullable', 'string', 'max:20'],
            'train_content' => ['required', 'string', 'max:500'],
            'train_hours' => ['required', 'numeric', 'min:0', 'max:999'],
            'remark' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function messages(): array
    {
        return [
            'train_date.required' => '请选择培训时间',
            'trainer_name.required' => '请填写培训人员姓名',
            'train_content.required' => '请填写培训内容',
            'train_hours.required' => '请填写培训时长',
            'train_hours.numeric' => '培训时长须为数字',
        ];
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_numeric($value)) {
            // Excel 序列号日期
            $ts = ($value - 25569) * 86400;

            return gmdate('Y-m-d', (int) $ts) ?: null;
        }
        $raw = trim((string) $value);
        $raw = str_replace(['/', '.'], '-', $raw);
        try {
            return (new \DateTimeImmutable($raw))->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function toRow(FireTraining $t): array
    {
        return [
            'id' => $t->id,
            'train_date' => $t->train_date?->format('Y-m-d'),
            'trainer_name' => $t->trainer_name,
            'student_no' => $t->student_no,
            'grade' => $t->grade,
            'train_content' => $t->train_content,
            'train_hours' => (float) $t->train_hours,
            'remark' => $t->remark,
            'creator' => $t->creator?->username,
            'created_at' => $t->created_at?->format('Y-m-d H:i'),
            'updated_at' => $t->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
