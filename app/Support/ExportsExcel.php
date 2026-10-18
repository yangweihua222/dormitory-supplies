<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel 导出公共能力（P3 报表导出）。
 *
 * 用法：控制器 use ExportsExcel 后调用
 *   return $this->xlsxDownload('文件名前缀', ['列1', '列2'], [[$v1, $v2], ...]);
 */
trait ExportsExcel
{
    /** 生成并返回 xlsx 下载响应（表头加粗 + 浅蓝底 + 等宽 18） */
    protected function xlsxDownload(string $prefix, array $headers, array $rows)
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($prefix, 0, 28));

        foreach ($headers as $i => $h) {
            $sheet->setCellValue($this->colLetter($i + 1).'1', $h);
        }
        $lastCol = $this->colLetter(count($headers));
        $headerStyle = $sheet->getStyle("A1:{$lastCol}1");
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD9E1F2');

        foreach ($rows as $r => $values) {
            foreach (array_values($values) as $c => $v) {
                $sheet->setCellValue($this->colLetter($c + 1).($r + 2), $v);
            }
        }

        foreach (range(1, count($headers)) as $c) {
            $sheet->getColumnDimension($this->colLetter($c))->setWidth(18);
        }

        $filename = $prefix.'_'.now()->format('Ymd_His').'.xlsx';
        ob_start();
        (new Xlsx($spreadsheet))->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename*=UTF-8''".rawurlencode($filename),
        ]);
    }

    /** 1 → A，27 → AA */
    protected function colLetter(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $s = chr(65 + $m).$s;
            $n = intdiv($n - $m - 1, 26);
        }

        return $s;
    }
}
