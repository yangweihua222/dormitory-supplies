<?php

namespace App\Support;

/**
 * 统一响应（技术方案 §4.3）：{ code, message, data }
 * code = 0 成功；列表统一 { list, total, page, pageSize }
 */
trait ApiResponse
{
    protected function ok(mixed $data = null, string $message = 'ok')
    {
        return response()->json([
            'code' => 0,
            'message' => $message,
            'data' => $data,
        ]);
    }

    protected function fail(int $code, string $message, int $httpStatus = 200, mixed $data = null)
    {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $httpStatus);
    }

    /** 分页列表统一结构 */
    protected function page($paginator)
    {
        return $this->ok([
            'list' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'pageSize' => $paginator->perPage(),
        ]);
    }
}
