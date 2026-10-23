<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class QuarterlyReportPdfRenderer
{
    // Included in monthly archive snapshots to avoid reusing a previous PDF layout.
    public const LAYOUT_VERSION = 2;

    public function render(array $snapshot, bool $draft = false): string
    {
        $this->validateSnapshot($snapshot);
        // Release completed document graphs when a worker renders several reports.
        gc_collect_cycles();
        $cache = storage_path('framework/cache/report-pdf');
        File::ensureDirectoryExists($cache);
        $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false,
            'defaultFont' => 'DejaVu Serif', 'tempDir' => $cache, 'fontCache' => $cache,
            'chroot' => [public_path('assets/dpe'), base_path('vendor/dompdf/dompdf/lib/fonts')]]);
        $pdf = new Dompdf($options);
        $metrics = $pdf->getFontMetrics();
        $font = $metrics->getFont('DejaVu Serif');
        $bold = $metrics->getFont('DejaVu Serif', 'bold');
        $rows = [];
        $skillLabels = $this->skillLabels();
        foreach ($skillLabels as $code => $label) {
            $chunks = array_chunk($this->wrap($snapshot['skills'][$code]['description'], $this->isMonthly() ? 275 : 342, $metrics, $font, 10.4), 10);
            foreach ($chunks as $part => $lines) {
                $rows[] = ['number' => array_search($code, array_keys($skillLabels), true) + 1,
                    'label' => $label, 'label_lines' => $this->wrap($this->isMonthly() ? str_replace('/', '/ ', $label) : $label,
                        $this->isMonthly() ? 76 : 65, $metrics, $font, 10.4),
                    'continued' => $part > 0, 'lines' => $lines, 'score' => $snapshot['skills'][$code]['score']];
            }
        }
        $fields = $snapshot['template']['fields'];
        $header = [];
        $scale = 1;
        do {
            $headerHeight = 14;
            foreach (['organization_name' => 18, 'registration_number' => 11, 'slogan' => 10, 'address' => 9, 'phone' => 9] as $key => $size) {
                $size = max(6.5, $size * $scale);
                $text = $key === 'registration_number' ? 'SK: '.$fields[$key] : $fields[$key];
                $headerFont = $key === 'organization_name' ? $bold : $font;
                $lines = $this->wrap($text, 439, $metrics, $headerFont, $size);
                $header[$key] = ['lines' => $lines, 'size' => $size];
                $headerHeight += count($lines) * $metrics->getFontHeight($headerFont, $size) * 1.3 + 2;
            }
            $scale *= .9;
        } while ($headerHeight > 245 && $scale > .3);
        $headerHeight = max(78, $headerHeight) + 8;
        $period = $this->period($snapshot);
        $studentLabelWidth = round($metrics->getTextWidth("Student's Name:", $bold, 10.4) + 4, 2);
        $html = view('quarterly-reports.pdf', ['snapshot' => $snapshot, 'rows' => $rows, 'header' => $header, 'headerHeight' => $headerHeight,
            'period' => $period, 'noteLines' => $this->wrap($snapshot['parent_note'], 510, $metrics, $font, 10.4),
            'documentationLines' => $this->wrap($fields['documentation_text'], 510, $metrics, $font, 10.4),
            'closingLines' => $this->wrap($fields['closing_text'], 510, $metrics, $font, 10.4),
            'socialLines' => $this->wrap($fields['instagram_accounts'], 510, $metrics, $font, 10.4),
            'teacherLines' => $this->wrap('('.$snapshot['teacher_name'].')', 235, $metrics, $bold, 10.4),
            'managerLines' => $this->wrap('('.$fields['manager_name'].')', 235, $metrics, $bold, 10.4),
            'studentLabelWidth' => $studentLabelWidth,
            'studentLines' => $this->wrap($snapshot['student_name'], 510 - $studentLabelWidth, $metrics, $font, 10.4),
            'monthly' => $this->isMonthly(), ...$this->additionalContent($snapshot, $metrics, $font)])->render();
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $sans = $metrics->getFont('DejaVu Sans');
        $canvas->page_text(475, 819, '{PAGE_NUM} / {PAGE_COUNT}', $sans, 8, [.35, .35, .35]);
        if ($draft) {
            $canvas->page_text(42, 819, 'DRAF - BELUM DISETUJUI', $sans, 8, [.55, .2, .2]);
        }

        $bytes = $pdf->output();
        unset($canvas, $metrics, $pdf);
        gc_collect_cycles();

        return $bytes;
    }

    public function validateSnapshot(array $snapshot): void
    {
        $errors = [];
        foreach (QuarterlyReportContentService::SKILLS as $key => $label) {
            $row = $snapshot['skills'][$key] ?? null;
            if (! is_array($row) || ! is_string($row['description'] ?? null) || blank($row['description']) || mb_strlen($row['description']) > 4000 || ! in_array($row['score'] ?? null, QuarterlyReportContentService::SCORES, true)) {
                $errors['skills.'.$key] = 'Lengkapi deskripsi dan skor '.$label.' sebelum melihat PDF.';
            }
        }
        if (! is_string($snapshot['parent_note'] ?? null) || blank($snapshot['parent_note']) || mb_strlen($snapshot['parent_note']) > 8000) {
            $errors['parent_note'] = 'Lengkapi Note sebelum melihat PDF.';
        }
        $errors += $this->identityErrors($snapshot);
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function identityErrors(array $snapshot): array
    {
        $errors = [];
        foreach (['student_name', 'teacher_name', 'start_month', 'end_month'] as $field) {
            if (! is_string($snapshot[$field] ?? null) || blank($snapshot[$field])) {
                $errors['snapshot'] = 'Identitas salinan rapor belum lengkap.';
            }
        }
        foreach (ReportTemplateService::FIELDS as $field => $label) {
            if (! is_string($snapshot['template']['fields'][$field] ?? null) || blank($snapshot['template']['fields'][$field])) {
                $errors['template'] = 'Template rapor belum lengkap.';
            }
        }
        if (! is_string($snapshot['template']['logo_data_uri'] ?? null) || ! preg_match('#^data:image/(png|jpeg|webp);base64,[A-Za-z0-9+/=]+$#D', $snapshot['template']['logo_data_uri'])) {
            $errors['template'] = 'Logo salinan rapor tidak valid.';
        }
        if (array_key_exists('signatures', $snapshot) && ! is_array($snapshot['signatures'])) {
            $errors['signatures'] = 'Salinan tanda tangan tidak valid.';
        } else {
            foreach (['teacher' => [$snapshot['teacher_name'] ?? '', $snapshot['teacher_id'] ?? null],
                'manager' => [$snapshot['template']['fields']['manager_name'] ?? '', null]] as $scope => [$name, $teacherId]) {
                $signature = $snapshot['signatures'][$scope] ?? null;
                if ($signature !== null && (! is_array($signature) || ! ReportSignatureService::validEmbedded($signature, $name, $teacherId))) {
                    $errors['signatures'] = 'Gambar atau identitas tanda tangan pada salinan rapor tidak valid.';
                }
            }
        }
        return $errors;
    }

    protected function skillLabels(): array
    {
        return QuarterlyReportContentService::SKILLS;
    }

    protected function isMonthly(): bool
    {
        return false;
    }

    protected function period(array $snapshot): string
    {
        $months = collect(range(0, 2))->map(fn ($i) => CarbonImmutable::parse($snapshot['start_month'])->addMonths($i));
        $sameYear = $months->first()->year === $months->last()->year;
        $labels = $months->map(fn ($m) => $m->format($sameYear ? 'F' : 'F Y'))->all();

        return $labels[0].', '.$labels[1].', and '.$labels[2].($sameYear ? ' '.$months->last()->year : '');
    }

    protected function additionalContent(array $snapshot, FontMetrics $metrics, string $font): array
    {
        return ['sections' => [], 'videoBlocks' => []];
    }

    protected function wrap(string $text, float $width, FontMetrics $metrics, string $font, float $size): array
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
