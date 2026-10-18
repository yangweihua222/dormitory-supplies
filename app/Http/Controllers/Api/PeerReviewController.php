<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PeerReview;
use App\Support\ApiResponse;
use App\Support\AssessmentDict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 同行评议（需求 10.3，与原型「同行评议」一致）。
 * 全员可发起评议（评议人默认本人）；修改/删除仅本人评议或管理员。
 * total 由后端按 scores 合计（每维度 0–10，满分 50），不采信前端传值。
 */
class PeerReviewController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = PeerReview::query()->with(['reviewer', 'reviewee']);
        if ($name = trim((string) $request->input('name', ''))) {
            $query->whereHas('reviewee', fn ($q) => $q->where('username', 'like', "%{$name}%"));
        }
        if ($month = $request->input('month')) {
            $query->where('month', $month);
        }

        $paginator = $query->orderByDesc('month')->orderByDesc('id')
            ->paginate((int) $request->input('pageSize', 10));
        $paginator->setCollection($paginator->getCollection()->map(fn ($r) => $this->toRow($r, $request->user())));

        return $this->page($paginator);
    }

    /** 字典：评议维度（评分表单渲染依据，登录即可） */
    public function options(): JsonResponse
    {
        return $this->ok(['dims' => AssessmentDict::PEER_DIMS]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(), $this->messages());

        $scores = $this->normalizeScores($data['scores']);
        $row = PeerReview::create([
            'reviewer_id' => $request->user()->id,
            'reviewee_id' => $data['reviewee_id'],
            'month' => $data['month'],
            'scores' => $scores,
            'total' => array_sum($scores),
            'remark' => $data['remark'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return $this->ok($this->toRow($row->refresh()->load(['reviewer', 'reviewee']), $request->user()), '已保存评议');
    }

    public function update(Request $request, PeerReview $peerReview): JsonResponse
    {
        if (! $this->canEdit($request, $peerReview)) {
            return $this->fail(1002, '仅本人评议或管理员可修改', 403);
        }
        $data = $request->validate($this->rules(), $this->messages());

        $scores = $this->normalizeScores($data['scores']);
        $peerReview->fill([
            'reviewee_id' => $data['reviewee_id'],
            'month' => $data['month'],
            'scores' => $scores,
            'total' => array_sum($scores),
            'remark' => $data['remark'] ?? null,
        ])->save();

        return $this->ok($this->toRow($peerReview->refresh()->load(['reviewer', 'reviewee']), $request->user()), '已保存');
    }

    public function destroy(Request $request, PeerReview $peerReview): JsonResponse
    {
        if (! $this->canEdit($request, $peerReview)) {
            return $this->fail(1002, '仅本人评议或管理员可删除', 403);
        }
        $peerReview->delete();

        return $this->ok(null, '已删除');
    }

    private function canEdit(Request $request, PeerReview $row): bool
    {
        return $request->user()->isAdmin() || $row->reviewer_id === $request->user()->id;
    }

    private function rules(): array
    {
        return [
            'reviewee_id' => ['required', 'integer', 'exists:users,id'],
            'month' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'scores' => ['required', 'array', 'size:'.count(AssessmentDict::PEER_DIMS)],
            'scores.*' => ['required', 'integer', 'min:0', 'max:10'],
            'remark' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function messages(): array
    {
        return [
            'reviewee_id.required' => '请选择被评议人',
            'month.required' => '请选择考核月份',
            'month.regex' => '考核月份格式应为 YYYY-MM',
            'scores.required' => '请完成各维度评分',
            'scores.size' => '评分数组须包含全部评议维度',
            'scores.*.max' => '每个维度评分 0–10 分',
        ];
    }

    /** 每维度钳制到 0–10 整数 */
    private function normalizeScores(array $scores): array
    {
        return array_map(fn ($s) => min(10, max(0, (int) $s)), array_values($scores));
    }

    private function toRow(PeerReview $r, $me): array
    {
        return [
            'id' => $r->id,
            'reviewer_id' => $r->reviewer_id,
            'reviewer' => $r->reviewer?->username,
            'reviewee_id' => $r->reviewee_id,
            'reviewee' => $r->reviewee?->username,
            'month' => $r->month,
            'scores' => $r->scores ?? [],
            'total' => (int) $r->total,
            'remark' => $r->remark,
            'can_edit' => $me->isAdmin() || $r->reviewer_id === $me->id,
            'created_at' => $r->created_at?->format('Y-m-d H:i'),
            'updated_at' => $r->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
