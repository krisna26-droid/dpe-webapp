<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Dompdf\FontMetrics;
use Illuminate\Validation\ValidationException;

class MonthlyReportPdfRenderer extends QuarterlyReportPdfRenderer
{
    protected function skillLabels(): array
    {
        return StudentProgressService::CATEGORIES;
    }

    protected function isMonthly(): bool
    {
        return true;
    }

    protected function period(array $snapshot): string
    {
        return CarbonImmutable::parse($snapshot['report_month'])->format('F Y');
    }

    public function validateSnapshot(array $snapshot): void
    {
        $errors = $this->identityErrors($snapshot);
        $month = $snapshot['report_month'] ?? null;
        if (! is_string($month) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])-01$/D', $month)
            || ($snapshot['start_month'] ?? null) !== $month || ($snapshot['end_month'] ?? null) !== $month
            || ($snapshot['report_kind'] ?? null) !== 'monthly') {
            $errors['period'] = 'Periode PDF harus sesuai satu bulan laporan.';
        }
        foreach ($this->skillLabels() as $code => $label) {
            $skill = $snapshot['skills'][$code] ?? null;
            if (! is_array($skill) || ! in_array($skill['score'] ?? null, StudentProgressService::TRENDS, true)
                || ! is_string($skill['description'] ?? null) || blank($skill['description']) || mb_strlen($skill['description']) > 4000) {
                $errors['skills.'.$code] = 'Salinan penilaian '.$label.' belum lengkap.';
            }
        }
        foreach (['development_summary', 'parent_challenges_summary', 'parent_message'] as $field) {
            if (! is_string($snapshot[$field] ?? null) || blank($snapshot[$field]) || mb_strlen($snapshot[$field]) > 8000) {
                $errors[$field] = 'Salinan narasi laporan bulanan belum lengkap.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function additionalContent(array $snapshot, FontMetrics $metrics, string $font): array
    {
        $sections = [];
        foreach (['development_summary' => 'Development Summary:', 'parent_challenges_summary' => 'Challenges:',
            'parent_message' => "Teacher's Message:"] as $key => $title) {
            $sections[] = ['title' => $title, 'lines' => $this->wrap($snapshot[$key], 510, $metrics, $font, 10.4)];
        }
        if (is_array($snapshot['attendance'] ?? null)) {
            $a = $snapshot['attendance'];
            $text = 'Total hadir: '.$a['attended_count'].' dari '.$a['meeting_count'].' pertemuan berlangsung.'
                ."\nHadir ".$a['present'].' - Terlambat '.$a['late'].' - Tidak Hadir '.$a['absent'].' - Sakit '.$a['sick'].' - Izin '.$a['permission']
                ."\nBelum dicatat: ".$a['unrecorded'].' - Dijadwalkan Ulang: '.$a['rescheduled'];
            $sections[] = ['title' => 'Kehadiran Siswa:', 'lines' => $this->wrap($text, 510, $metrics, $font, 10.4)];
        }
        $videoBlocks = [];
        foreach ($snapshot['videos'] ?? [] as $video) {
            if (! is_array($video) || ! StudentProgressService::safeUrl($video['video_url'] ?? null)) {
                continue;
            }
            $text = ($video['video_on'] ?? '').' - '.($video['topic'] ?? 'Video belajar');
            if (filled($video['description'] ?? null)) {
                $text .= "\n".$video['description'];
            }
            $videoBlocks[] = ['url' => $video['video_url'], 'lines' => $this->wrap($text, 510, $metrics, $font, 10.4)];
        }

        return compact('sections', 'videoBlocks');
    }
}
