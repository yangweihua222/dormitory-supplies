<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FireVolunteer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** 消防志愿服务（需求 §7.2）：列表支持按姓名 + 年级查询 */
class FireVolunteerController extends Controller
{
    use ApiResponse;

    /** 年级字典：消防模块共用 */
    public const GRADES = ['大一', '大二', '大三', '大四', '大五', '研一', '研二', '研三', '博一', '博二', '博三'];

    public function index(Request $request): JsonResponse
    {
        $query = FireVolunteer::query();

        if ($name = trim((string) $request->input('name', ''))) {
            $query->where('student_name', 'like', "%{$name}%");
        }
        if ($grade = $request->input('grade')) {
            $query->where('grade', $grade);
        }
        if ($date = $request->input('date')) {
            $query->whereDate('service_date', $date);
        }

        $paginator = $query->orderByDesc('service_date')->orderByDesc('id')
            ->paginate((int) $request->input('pageSize', 10));

        $paginator->setCollection($paginator->getCollection()->map(fn ($v) => $this->toRow($v)));

        return $this->page($paginator);
    }

    public function options(): JsonResponse
    {
        return $this->ok(['grades' => self::GRADES]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(), $this->messages());
        $data['created_by'] = $request->user()->id;

        $row = FireVolunteer::create($data);

        return $this->ok($this->toRow($row->refresh()), '已添加');
    }

    public function update(Request $request, FireVolunteer $volunteer): JsonResponse
    {
        $data = $request->validate($this->rules(), $this->messages());
        $volunteer->fill($data)->save();

        return $this->ok($this->toRow($volunteer->refresh()), '已保存');
    }

    public function destroy(FireVolunteer $volunteer): JsonResponse
    {
        $volunteer->delete();

        return $this->ok(null, '已删除');
    }

    /** GET /api/fire-volunteers/template 下载导入模板 */
    public function template()
    {
        $headers = ['学生姓名', '学号', '年级', '服务时间', '服务内容', '服务时长(小时)', '备注'];
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('消防志愿服务导入');
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i).'1', $h);
        }
        $headerStyle = $sheet->getStyle('A1:'.chr(65 + count($headers) - 1).'1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD9E1F2');
        foreach (range(0, count($headers) - 1) as $c) {
            $sheet->getColumnDimension(chr(65 + $c))->setWidth(18);
        }
        $sheet->getColumnDimension('E')->setWidth(36);

        $notes = $spreadsheet->createSheet();
        $notes->setTitle('导入说明');
        $lines = [
            '消防志愿服务 Excel 导入说明',
            '',
            '1. 请按「消防志愿服务导入」页的列顺序填写，不要增删列、不要改列名。',
            '2. 表头（第 1 行）为字段名，请勿删除；数据从第 2 行开始填写。',
            '3. 必填项：学生姓名、学号、服务时间、服务内容。',
            '4. 服务时间格式：YYYY-MM-DD（如 2026-09-01）。',
            '5. 年级填写示例：大一 / 大二 / 研一（与页面下拉一致）。',
        ];
        foreach ($lines as $i => $line) {
            $notes->setCellValue('A'.($i + 1), $line);
        }
        $notes->getStyle('A1')->getFont()->setBold(true);
        $notes->getColumnDimension('A')->setWidth(90);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, '消防志愿服务导入模板.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** POST /api/fire-volunteers/import 导入（列序：姓名/学号/年级/服务时间/服务内容/服务时长/备注） */
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
            return $this->fail(5002, '文件无法解析，请按列顺序填写后再上传');
        }

        $rows = $spreadsheet->getSheet(0)->toArray(null, true, true, true);

        $imported = 0;
        $errors = [];
        $userId = $request->user()->id;

        foreach ($rows as $line => $row) {
            if ($line === 1) {
                continue; // 表头
            }
            $name = trim((string) ($row['A'] ?? ''));
            $studentNo = trim((string) ($row['B'] ?? ''));
            $date = $this->normalizeDate($row['D'] ?? null);
            if ($name === '' && $studentNo === '' && $date === null) {
                continue; // 空行
            }
            if ($name === '') {
                $errors[] = "第{$line}行：学生姓名为空";

                continue;
            }
            if ($studentNo === '') {
                $errors[] = "第{$line}行：学号为空";

                continue;
            }
            if ($date === null) {
                $errors[] = "第{$line}行：服务时间无法识别，应为 YYYY-MM-DD";

                continue;
            }
            if (trim((string) ($row['E'] ?? '')) === '') {
                $errors[] = "第{$line}行：服务内容为空";

                continue;
            }

            FireVolunteer::create([
                'student_name' => $name,
                'student_no' => $studentNo,
                'grade' => trim((string) ($row['C'] ?? '')) ?: null,
                'service_date' => $date,
                'service_content' => trim((string) ($row['E'] ?? '')),
                'service_hours' => is_numeric($row['F'] ?? null) ? (float) $row['F'] : 0,
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

    /** 导出（按当前查询条件） */
    public function export(Request $request)
    {
        $query = FireVolunteer::query();
        if ($name = trim((string) $request->input('name', ''))) {
            $query->where('student_name', 'like', "%{$name}%");
        }
        if ($grade = $request->input('grade')) {
            $query->where('grade', $grade);
        }

        $rows = $query->orderByDesc('service_date')->orderBy('id')->get();

        $headers = ['学生姓名', '学号', '年级', '服务时间', '服务内容', '服务时长(小时)', '备注', '创建时间', '修改时间'];
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i).'1', $h);
        }
        $headerStyle = $sheet->getStyle('A1:'.chr(65 + count($headers) - 1).'1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD9E1F2');

        foreach ($rows as $r => $row) {
            $values = [
                $row->student_name, $row->student_no, $row->grade,
                $row->service_date?->format('Y-m-d'), $row->service_content, $row->service_hours,
                $row->remark, $row->created_at?->format('Y-m-d H:i'), $row->updated_at?->format('Y-m-d H:i'),
            ];
            foreach ($values as $c => $v) {
                $sheet->setCellValue(chr(65 + $c).($r + 2), $v);
            }
        }
        foreach (range(0, count($headers) - 1) as $c) {
            $sheet->getColumnDimension(chr(65 + $c))->setWidth(18);
        }
        $sheet->getColumnDimension('E')->setWidth(36);

        $filename = '消防志愿服务导出_'.now()->format('Ymd_His').'.xlsx';
        ob_start();
        (new Xlsx($spreadsheet))->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename*=UTF-8''".rawurlencode($filename),
        ]);
    }

    /** Excel 日期归一化：支持日期单元格 / 序列号 / 字符串（与消防培训导入同逻辑） */
    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_numeric($value)) {
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

    private function rules(): array
    {
        return [
            'student_name' => ['required', 'string', 'max:50'],
            'student_no' => ['required', 'string', 'max:50'],
            'grade' => ['nullable', 'string', 'max:20'],
            'service_date' => ['required', 'date'],
            'service_content' => ['required', 'string', 'max:500'],
            'service_hours' => ['required', 'numeric', 'min:0', 'max:999'],
            'remark' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function messages(): array
    {
        return [
            'student_name.required' => '请填写学生姓名',
            'student_no.required' => '请填写学号',
            'service_date.required' => '请选择服务时间',
            'service_content.required' => '请填写服务内容',
            'service_hours.required' => '请填写服务时长',
            'service_hours.numeric' => '服务时长须为数字',
        ];
    }

    private function toRow(FireVolunteer $v): array
    {
        return [
            'id' => $v->id,
            'student_name' => $v->student_name,
            'student_no' => $v->student_no,
            'grade' => $v->grade,
            'service_date' => $v->service_date?->format('Y-m-d'),
            'service_content' => $v->service_content,
            'service_hours' => (float) $v->service_hours,
            'remark' => $v->remark,
            'creator' => $v->creator?->username,
            'created_at' => $v->created_at?->format('Y-m-d H:i'),
            'updated_at' => $v->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
