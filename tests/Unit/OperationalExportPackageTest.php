<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\OperationalExportXlsxWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ZipFormatAssertions;
use Tests\TestCase;

class OperationalExportPackageTest extends TestCase
{
    use ZipFormatAssertions;

    public static function documents(): array
    {
        return ['single row' => [1, false], 'all export rows' => [3000, false], 'long unicode text' => [2, true], 'empty writer input' => [0, false]];
    }

    #[DataProvider('documents')]
    public function test_xlsx_has_standard_zip_headers_consistent_sizes_offsets_and_crc(int $count, bool $long): void
    {
        $description = $long ? str_repeat('Kemajuan & latihan siswa, café 日本語 😀. ', 80) : 'Belajar & berlatih';
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = ['student' => 'Siswa Uji '.$i, 'phone' => '081234567890', 'date' => '2026-10-13', 'description' => $description,
                'literal' => '=HYPERLINK("https://example.org", "isi literal")'];
        }
        $data = ['actor' => new User(['full_name' => 'Teacher Uji']), 'rows' => $rows, 'total' => $count,
            'title' => 'Rekap Uji', 'description' => 'Data untuk pengujian format', 'generated' => '13/10/2026', 'filterLabels' => ['Cabang' => 'Cabang Uji'],
            'columns' => [['key' => 'student', 'label' => 'Siswa', 'width' => 28, 'type' => 'text'], ['key' => 'phone', 'label' => 'Nomor WA', 'width' => 23, 'type' => 'text'],
                ['key' => 'date', 'label' => 'Tanggal', 'width' => 18, 'type' => 'date'], ['key' => 'description', 'label' => 'Deskripsi', 'width' => 56, 'type' => 'text'],
                ['key' => 'literal', 'label' => 'Teks literal', 'width' => 50, 'type' => 'text']]];
        $bytes = app(OperationalExportXlsxWriter::class)->render($data);
        $parts = $this->assertStandardZip($bytes);
        $this->assertSame('[Content_Types].xml', array_key_first($parts));
        $this->assertCount($long ? 10 : 9, $parts);
        foreach ($parts as $name => $xml) {
            $document = new \DOMDocument;
            $this->assertTrue($document->loadXML($xml), $name);
        }
        $sheet = new \DOMDocument;
        $sheet->loadXML($parts['xl/worksheets/sheet1.xml']);
        $x = new \DOMXPath($sheet);
        $x->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $this->assertSame($count + 1, $x->query('//s:row')->length);
        $this->assertSame(0, $x->query('//s:f')->length);
        if ($count > 0) {
            $this->assertSame('081234567890', $x->evaluate('string(//s:c[@r="C2"]/s:is/s:t)'));
            $this->assertSame('inlineStr', $x->evaluate('string(//s:c[@r="C2"]/@t)'));
            $this->assertSame($description, $x->evaluate('string(//s:c[@r="E2"]/s:is/s:t)'));
        }
        if ($dir = getenv('DPE_V43_1_QA_DIR')) {
            \Illuminate\Support\Facades\File::ensureDirectoryExists($dir);
            file_put_contents($dir.'/package-'.$count.'-'.($long ? 'long' : 'short').'.xlsx', $bytes);
        }
    }
}
