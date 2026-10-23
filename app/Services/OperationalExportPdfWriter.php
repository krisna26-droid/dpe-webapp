<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class OperationalExportPdfWriter
{
    public function render(array $data): string
    {
        if ($data['total'] > OperationalExportDefinition::MAX_PDF_ROWS) {
            throw ValidationException::withMessages(['export' => 'PDF maksimal 300 baris sumber. Persempit filter atau pilih Excel untuk hasil yang lebih besar.']);
        }
        gc_collect_cycles();
        $cache = storage_path('framework/cache/operational-pdf');
        File::ensureDirectoryExists($cache);
        $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false,
            'defaultFont' => 'DejaVu Sans', 'tempDir' => $cache, 'fontCache' => $cache, 'chroot' => [base_path('vendor/dompdf/dompdf/lib/fonts')]]);
        $pdf = new Dompdf($options);
        $wide = count($data['columns']) >= 9;
        $paper = $wide ? 'A3' : 'A4';
        $pageWidth = $wide ? 1190.55 : 841.89;
        $pageHeight = $wide ? 841.89 : 595.28;
        $tableWidth = $pageWidth - 60;
        $columns = [['key' => '_no', 'label' => 'No.', 'width' => 8, 'type' => 'integer'], ...$data['columns']];
        $sum = array_sum(array_column($columns, 'width'));
        $metrics = $pdf->getFontMetrics();
        $font = $metrics->getFont('DejaVu Sans');
        foreach ($columns as &$column) {
            $column['points'] = $tableWidth * $column['width'] / $sum;
        }
        unset($column);
        $rows = [];
        foreach ($data['rows'] as $i => $row) {
            $wrapped = [];
            $parts = 1;
            foreach ($columns as $column) {
                $text = $column['key'] === '_no' ? (string) ($i + 1) : OperationalExportDefinition::display($row[$column['key']] ?? null, $column['type']);
                $lines = $this->wrap($text, $column['points'] - 10, $metrics, $font, 8.5);
                $wrapped[$column['key']] = array_chunk($lines, 9);
                $parts = max($parts, count($wrapped[$column['key']]));
            }
            for ($part = 0; $part < $parts; $part++) {
                $cells = [];
                foreach ($columns as $column) {
                    $cells[] = $column['key'] === '_no' ? [(string) ($i + 1), ...($part > 0 ? ['lanj.'] : [])] : ($wrapped[$column['key']][$part] ?? []);
                }
                $rows[] = ['cells' => $cells, 'continued' => $part > 0, 'even' => $i % 2 === 0];
                if (count($rows) > 1200) {
                    throw ValidationException::withMessages(['export' => 'Teks panjang membuat PDF terlalu besar. Persempit filter atau pilih Excel. Isi teks tidak dipotong.']);
                }
            }
        }
        $pdf->loadHtml(view('exports.pdf', compact('data', 'columns', 'rows', 'tableWidth'))->render(), 'UTF-8');
        $pdf->setPaper($paper, 'landscape');
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $canvas->page_text(30, $pageHeight - 22, 'DPE | Rekap operasional', $font, 8, [.35, .4, .5]);
        $canvas->page_text($pageWidth - 110, $pageHeight - 22, '{PAGE_NUM} / {PAGE_COUNT}', $font, 8, [.35, .4, .5]);
        $bytes = $pdf->output();
        unset($canvas, $pdf, $metrics);
        gc_collect_cycles();

        return $bytes;
    }

    private function wrap(string $text, float $width, FontMetrics $metrics, string $font, float $size): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', $text) as $paragraph) {
            $line = '';
            foreach (preg_split('/\s+/u', trim($paragraph), -1, PREG_SPLIT_NO_EMPTY) as $word) {
                $candidate = $line === '' ? $word : $line.' '.$word;
                if ($metrics->getTextWidth($candidate, $font, $size) <= $width) {
                    $line = $candidate;
                    continue;
                }
                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }
                if ($metrics->getTextWidth($word, $font, $size) <= $width) {
                    $line = $word;
                    continue;
                }
                foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) as $char) {
                    if ($line !== '' && $metrics->getTextWidth($line.$char, $font, $size) > $width) {
                        $lines[] = $line;
                        $line = '';
                    }
                    $line .= $char;
                }
            }
            $lines[] = $line;
        }

        return $lines ?: [''];
    }
}
