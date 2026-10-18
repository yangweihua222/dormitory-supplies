<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccommodationStatus;
use App\Enums\TodoStatus;
use App\Enums\TodoType;
use App\Http\Controllers\Controller;
use App\Models\Accommodation;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Todo;
use App\Models\TodoAssignee;
use App\Models\User;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * 住宿管理（需求 §5.2）
 * 三视图：房间列表 / 房间详情（当前在住）/ 历史记录
 * 三种业务：入住 / 退宿 / 调宿
 */
class AccommodationController extends Controller
{
    use ApiResponse;

    /** GET /api/accommodations 房间维度列表（筛选：房间号 / 状态是否满 / 业务类型） */
    public function index(Request $request): JsonResponse
    {
        $roomNo = trim((string) $request->input('room_no', ''));
        $fullStatus = $request->input('full_status'); // full / available
        $bizType = $request->input('biz_type');       // checkin / checkout / transfer

        $rooms = Room::withCount([
            'accommodations as living_count' => fn ($q) => $q->where('status', AccommodationStatus::LIVING),
        ])
            ->when($roomNo, fn ($q) => $q->where('room_no', 'like', "%{$roomNo}%"))
            ->when($bizType, fn ($q) => $q->whereHas(
                'accommodations',
                fn ($q2) => $q2->where('biz_type', $bizType)
            ))
            ->orderBy('room_code')
            ->get();

        $filtered = $rooms->filter(function ($room) use ($fullStatus) {
            if ($fullStatus === 'full') {
                return $room->living_count >= (int) $room->beds;
            }
            if ($fullStatus === 'available') {
                return $room->living_count < (int) $room->beds;
            }

            return true;
        })->values();

        $page = max(1, (int) $request->input('page', 1));
        $pageSize = (int) $request->input('pageSize', 10);
        $total = $filtered->count();
        $items = $filtered->slice(($page - 1) * $pageSize, $pageSize)->values();

        return $this->ok([
            'list' => $items->map(function ($room) {
                $full = $room->living_count >= (int) $room->beds;

                return [
                    'id' => $room->id,
                    'room_no' => $room->room_no,
                    'room_code' => $room->room_code,
                    'beds' => $room->beds,
                    'living_count' => $room->living_count,
                    'count_text' => $room->living_count.' / '.$room->beds.' 人',
                    'is_full' => $full,
                    'status_text' => $full ? '已满' : '有空床',
                    'status_tag' => $full ? 'success' : 'primary',
                    'created_at' => $room->created_at?->format('Y-m-d H:i'),
                    'updated_at' => $room->updated_at?->format('Y-m-d H:i'),
                ];
            })->all(),
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ]);
    }

    /** GET /api/accommodations/rooms/{room}/detail 该房间当前在住学生 */
    public function detail(Room $room): JsonResponse
    {
        $list = Accommodation::with('room')
            ->where('room_id', $room->id)
            ->where('status', AccommodationStatus::LIVING)
            ->orderByDesc('check_in_at')
            ->orderBy('id')
            ->get()
            ->map(fn ($a) => $this->toRow($a))
            ->all();

        return $this->ok([
            'room' => [
                'id' => $room->id,
                'room_no' => $room->room_no,
                'beds' => $room->beds,
                'living_count' => count($list),
            ],
            'list' => $list,
        ]);
    }

    /** GET /api/accommodations/rooms/{room}/history 该房间住过的学生 */
    public function history(Room $room): JsonResponse
    {
        $list = Accommodation::with('room')
            ->where('room_id', $room->id)
            ->where('status', AccommodationStatus::MOVED_OUT)
            ->orderByDesc('check_out_at')
            ->orderBy('id')
            ->get()
            ->map(fn ($a) => $this->toRow($a))
            ->all();

        return $this->ok([
            'room' => ['id' => $room->id, 'room_no' => $room->room_no, 'beds' => $room->beds],
            'list' => $list,
        ]);
    }

    /** POST /api/accommodations 住宿登记（入住 / 退宿 / 调宿） */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(), $this->messages());
        $this->validateBizRequired($request, $data['biz_type']);

        $result = DB::transaction(function () use ($data) {
            $biz = $data['biz_type'];

            // 退宿：把原在住记录置为已搬出
            if ($biz === 'checkout' && ($data['source_id'] ?? null)) {
                $origin = Accommodation::findOrFail($data['source_id']);
                $origin->fill($this->bizFields($data, 'checkout'));
                $origin->status = AccommodationStatus::MOVED_OUT;
                $origin->biz_type = 'checkout';
                $origin->save();

                return $origin->refresh();
            }

            // 调宿：原记录置为已搬出（原房间留历史），新房间新增在住记录
            if ($biz === 'transfer' && ($data['source_id'] ?? null)) {
                $origin = Accommodation::findOrFail($data['source_id']);
                $origin->update([
                    'status' => AccommodationStatus::MOVED_OUT,
                    'check_out_at' => $data['check_in_at'] ?? now(),
                ]);
            }

            $payload = $this->bizFields($data, $biz);
            $payload['room_id'] = $data['room_id'];
            $payload['student_no'] = $data['student_no'];
            $payload['name'] = $data['name'];
            $payload['gender'] = $data['gender'];
            $payload['biz_type'] = $biz;
            $payload['status'] = $biz === 'checkout'
                ? AccommodationStatus::MOVED_OUT
                : AccommodationStatus::LIVING;

            return Accommodation::create($payload)->refresh();
        });

        $label = match ($data['biz_type']) {
            'checkin' => '入住登记已保存',
            'checkout' => '退宿登记已保存',
            default => '调宿登记已保存',
        };

        return $this->ok($this->toRow($result), $label);
    }

    /** PUT /api/accommodations/{accommodation} 编辑 */
    public function update(Request $request, Accommodation $accommodation): JsonResponse
    {
        $data = $request->validate($this->rules(), $this->messages());

        DB::transaction(function () use ($accommodation, $data) {
            $payload = $this->bizFields($data, $data['biz_type']);
            $payload['room_id'] = $data['room_id'];
            $payload['student_no'] = $data['student_no'];
            $payload['name'] = $data['name'];
            $payload['gender'] = $data['gender'];
            $payload['biz_type'] = $data['biz_type'];
            $accommodation->fill($payload)->save();
        });

        return $this->ok($this->toRow($accommodation->refresh()), '已保存');
    }

    /** DELETE /api/accommodations/{accommodation} 删除 */
    public function destroy(Accommodation $accommodation): JsonResponse
    {
        $accommodation->delete();

        return $this->ok(null, '已删除');
    }

    /** POST /api/accommodations/{accommodation}/handover 交接（需求 §5.2.4） */
    public function handover(Request $request, Accommodation $accommodation): JsonResponse
    {
        if (! $accommodation->canHandOver()) {
            return $this->fail(3002, '该记录无需交接（手续已完成或交接已办结）');
        }

        $message = DB::transaction(function () use ($accommodation, $request) {
            $accommodation->update(['is_handed_over' => true]);

            // 派发给次日全部排班人员；次日无人排班时兜底派给全体管理员
            $nextDay = Carbon::today()->addDay()->format('Y-m-d');
            $nextUsers = Schedule::with('user')
                ->where('date', $nextDay)
                ->get()
                ->pluck('user')
                ->filter()
                ->unique('id')
                ->values();

            $fallback = false;
            if ($nextUsers->isEmpty()) {
                $fallback = true;
                $nextUsers = User::where('identity', 'admin')->get();
            }
            $notified = $nextUsers->pluck('username')->filter()->implode('、');

            $todo = Todo::create([
                'type' => TodoType::HANDOVER,
                'title' => '住宿交接：'.($accommodation->room?->room_no ?? '—').' '.$accommodation->name,
                'content' => sprintf(
                    '%s（学号 %s）的%s记录手续未办理完成，请跟进处理。',
                    $accommodation->name,
                    $accommodation->student_no,
                    $accommodation->biz_type->label()
                ),
                'status' => TodoStatus::PENDING,
                'source_type' => Accommodation::class,
                'source_id' => $accommodation->id,
                'created_by' => $request->user()?->id,
                'first_handover_at' => now(),
            ]);

            foreach ($nextUsers as $u) {
                TodoAssignee::create(['todo_id' => $todo->id, 'user_id' => $u->id]);
            }

            return $fallback
                ? '次日无人排班，已通知全体管理员：'.($notified ?: '无')
                : '交接完成，已通知次日值班人：'.($notified ?: '无');
        });

        return $this->ok($this->toRow($accommodation->refresh()), $message ?: '交接完成');
    }

    /** GET /api/accommodations/export 导出（按搜索条件导出房间及其学生） */
    public function export(Request $request)
    {
        $roomNo = trim((string) $request->input('room_no', ''));
        $bizType = $request->input('biz_type');
        $fullStatus = $request->input('full_status');

        $query = Accommodation::with('room')
            ->when($roomNo, fn ($q) => $q->whereHas('room', fn ($q2) => $q2->where('room_no', 'like', "%{$roomNo}%")))
            ->when($bizType, fn ($q) => $q->where('biz_type', $bizType));

        if ($fullStatus) {
            $roomIds = Room::withCount([
                'accommodations as living_count' => fn ($q) => $q->where('status', AccommodationStatus::LIVING),
            ])->get()
                ->filter(fn ($r) => $fullStatus === 'full'
                    ? $r->living_count >= (int) $r->beds
                    : $r->living_count < (int) $r->beds)
                ->pluck('id');
            $query->whereIn('room_id', $roomIds);
        }

        $rows = $query->orderBy('room_id')->orderBy('id')->get();

        $headers = [
            '业务类型', '学号', '姓名', '性别', '房间号', '原房间号', '入住时间', '退宿时间',
            '安全员责任书', '家具确认单', '外国人住宿登记', '床垫', '床品', '门禁系统',
            '住宿协议状态', '手续办理是否完成', '备注', '创建时间', '修改时间',
        ];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($headers as $i => $h) {
            $sheet->setCellValue($this->colLetter($i + 1).'1', $h);
        }
        $lastCol = $this->colLetter(count($headers));
        $headerStyle = $sheet->getStyle("A1:{$lastCol}1");
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD9E1F2');

        foreach ($rows as $r => $row) {
            $values = [
                $row->biz_type->label(),
                $row->student_no,
                $row->name,
                $this->genderLabel($row->gender),
                $row->room?->room_no,
                $row->original_room_no,
                $row->check_in_at?->format('Y-m-d'),
                $row->check_out_at?->format('Y-m-d'),
                $this->enumLabel($row->safety_duty_status, ['signed' => '已签', 'unsigned' => '未签']),
                $this->enumLabel($row->furniture_confirm, ['signed' => '已签', 'unsigned' => '未签']),
                $this->enumLabel($row->foreigner_registration, ['registered' => '已登记', 'unregistered' => '未登记']),
                $this->enumLabel($row->mattress, ['issued' => '已发', 'unissued' => '未发']),
                $this->enumLabel($row->bedding, ['purchased' => '已购', 'unpurchased' => '未购']),
                $this->enumLabel($row->access_control, ['authorized' => '已授权', 'unauthorized' => '未授权']),
                $this->enumLabel($row->agreement_status, ['signed' => '已签', 'unsigned' => '未签']),
                $row->is_procedure_done ? '是' : '否',
                $row->remark,
                $row->created_at?->format('Y-m-d H:i'),
                $row->updated_at?->format('Y-m-d H:i'),
            ];
            foreach ($values as $c => $v) {
                $sheet->setCellValue($this->colLetter($c + 1).($r + 2), $v);
            }
        }

        foreach (range(1, count($headers)) as $c) {
            $sheet->getColumnDimension($this->colLetter($c))->setWidth(16);
        }

        $filename = '住宿管理导出_'.now()->format('Ymd_His').'.xlsx';
        ob_start();
        (new Xlsx($spreadsheet))->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename*=UTF-8''".rawurlencode($filename),
        ]);
    }

    private function rules(): array
    {
        return [
            'room_id' => ['required', 'exists:rooms,id'],
            'student_no' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:50'],
            'gender' => ['required', 'in:male,female'],
            'biz_type' => ['required', 'in:checkin,checkout,transfer'],
            'source_id' => ['nullable', 'exists:accommodations,id'],
            'original_room_no' => ['nullable', 'string', 'max:50'],
            'check_in_at' => ['nullable', 'date'],
            'check_out_at' => ['nullable', 'date'],
            'agreement_status' => ['nullable', 'in:signed,unsigned'],
            'safety_duty_status' => ['nullable', 'in:signed,unsigned'],
            'furniture_confirm' => ['nullable', 'in:signed,unsigned'],
            'foreigner_registration' => ['nullable', 'in:registered,unregistered'],
            'mattress' => ['nullable', 'in:issued,unissued'],
            'bedding' => ['nullable', 'in:purchased,unpurchased'],
            'access_control' => ['nullable', 'in:authorized,unauthorized'],
            'has_checkout_order' => ['nullable', 'boolean'],
            'is_belongings_cleared' => ['nullable', 'boolean'],
            'furniture_state' => ['nullable', 'in:complete,missing,damaged'],
            'access_action' => ['nullable', 'in:cancel,keep'],
            'has_transfer_application' => ['nullable', 'boolean'],
            'is_procedure_done' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function messages(): array
    {
        return [
            'room_id.required' => '请选择房间号',
            'student_no.required' => '请填写学号',
            'name.required' => '请填写姓名',
            'gender.required' => '请选择性别',
            'gender.in' => '性别无效',
            'biz_type.required' => '请选择业务类型',
        ];
    }

    /** 各业务类型的时间必填校验 */
    private function validateBizRequired(Request $request, string $biz): void
    {
        $rules = match ($biz) {
            'checkin' => ['check_in_at' => ['required', 'date']],
            'transfer' => ['check_in_at' => ['required', 'date']],
            default => ['check_out_at' => ['required', 'date']],
        };
        $messages = [
            'check_in_at.required' => $biz === 'transfer' ? '请选择调宿时间' : '请选择入住时间',
            'check_out_at.required' => '请选择退宿时间',
        ];
        $request->validate($rules, $messages);
    }

    /** 按业务类型取应写入的字段（避免入住写入退宿专属字段） */
    private function bizFields(array $data, string $biz): array
    {
        $fields = [
            'check_in_at' => $data['check_in_at'] ?? null,
            'check_out_at' => $data['check_out_at'] ?? null,
            'original_room_no' => $data['original_room_no'] ?? null,
            'remark' => $data['remark'] ?? null,
            'is_procedure_done' => (bool) ($data['is_procedure_done'] ?? false),
            'agreement_status' => $data['agreement_status'] ?? null,
            'access_control' => $data['access_control'] ?? null,
        ];

        if ($biz === 'checkin') {
            $fields += [
                'safety_duty_status' => $data['safety_duty_status'] ?? null,
                'furniture_confirm' => $data['furniture_confirm'] ?? null,
                'foreigner_registration' => $data['foreigner_registration'] ?? null,
                'mattress' => $data['mattress'] ?? null,
                'bedding' => $data['bedding'] ?? null,
            ];
        } else {
            $fields += [
                'has_checkout_order' => $data['has_checkout_order'] ?? null,
                'is_belongings_cleared' => $data['is_belongings_cleared'] ?? null,
                'furniture_state' => $data['furniture_state'] ?? null,
                'access_action' => $data['access_action'] ?? null,
                'has_transfer_application' => $data['has_transfer_application'] ?? null,
            ];
        }

        return $fields;
    }

    /** 1 → A，27 → AA（PhpSpreadsheet 列号转字母；与考勤导出一致） */
    private function colLetter(int $column): string
    {
        $letter = '';
        while ($column > 0) {
            $mod = ($column - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $column = intdiv($column - $mod, 26);
        }

        return $letter;
    }

    private function enumLabel(?string $value, array $map): ?string
    {
        return $value ? ($map[$value] ?? $value) : null;
    }

    private function genderLabel(?string $v): ?string
    {
        return match ($v) {
            'male' => '男',
            'female' => '女',
            default => null,
        };
    }

    private function toRow(Accommodation $a): array
    {
        return [
            'id' => $a->id,
            'room_id' => $a->room_id,
            'room_no' => $a->room?->room_no,
            'student_no' => $a->student_no,
            'name' => $a->name,
            'gender' => $a->gender,
            'gender_label' => $this->genderLabel($a->gender),
            'biz_type' => $a->biz_type->value,
            'biz_type_label' => $a->biz_type->label(),
            'biz_type_tag' => $a->biz_type->tagType(),
            'status' => $a->status->value,
            'status_label' => $a->status->label(),
            'check_in_at' => $a->check_in_at?->format('Y-m-d'),
            'check_out_at' => $a->check_out_at?->format('Y-m-d'),
            'original_room_no' => $a->original_room_no,
            'agreement_status' => $a->agreement_status,
            'agreement_label' => $this->enumLabel($a->agreement_status, ['signed' => '已签', 'unsigned' => '未签']),
            'safety_duty_status' => $a->safety_duty_status,
            'safety_duty_label' => $this->enumLabel($a->safety_duty_status, ['signed' => '已签', 'unsigned' => '未签']),
            'furniture_confirm' => $a->furniture_confirm,
            'furniture_confirm_label' => $this->enumLabel($a->furniture_confirm, ['signed' => '已签', 'unsigned' => '未签']),
            'foreigner_registration' => $a->foreigner_registration,
            'foreigner_label' => $this->enumLabel($a->foreigner_registration, ['registered' => '已登记', 'unregistered' => '未登记']),
            'mattress' => $a->mattress,
            'mattress_label' => $this->enumLabel($a->mattress, ['issued' => '已发', 'unissued' => '未发']),
            'bedding' => $a->bedding,
            'bedding_label' => $this->enumLabel($a->bedding, ['purchased' => '已购', 'unpurchased' => '未购']),
            'access_control' => $a->access_control,
            'access_control_label' => $this->enumLabel($a->access_control, ['authorized' => '已授权', 'unauthorized' => '未授权']),
            'has_checkout_order' => $a->has_checkout_order,
            'has_checkout_order_label' => is_null($a->has_checkout_order) ? null : ($a->has_checkout_order ? '有' : '无'),
            'is_belongings_cleared' => $a->is_belongings_cleared,
            'is_belongings_cleared_label' => is_null($a->is_belongings_cleared) ? null : ($a->is_belongings_cleared ? '是' : '否'),
            'furniture_state' => $a->furniture_state,
            'furniture_state_label' => match ($a->furniture_state) {
                'complete' => '齐全完好',
                'missing' => '家具缺失',
                'damaged' => '家具破损',
                default => null,
            },
            'access_action' => $a->access_action,
            'access_action_label' => match ($a->access_action) {
                'cancel' => '取消授权',
                'keep' => '保留授权',
                default => null,
            },
            'has_transfer_application' => $a->has_transfer_application,
            'has_transfer_label' => is_null($a->has_transfer_application) ? null : ($a->has_transfer_application ? '有' : '无'),
            'is_procedure_done' => (bool) $a->is_procedure_done,
            'is_procedure_done_label' => $a->is_procedure_done ? '是' : '否',
            'is_handed_over' => (bool) $a->is_handed_over,
            'can_hand_over' => $a->canHandOver(),
            'remark' => $a->remark,
            'created_at' => $a->created_at?->format('Y-m-d H:i'),
            'updated_at' => $a->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
