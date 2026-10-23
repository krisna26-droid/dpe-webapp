<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class OperationalExportXlsxWriter
{
    public function render(array $data): string
    {
        $columns = [['key' => '_no', 'label' => 'No.', 'width' => 8, 'type' => 'integer'], ...$data['columns']];
        $rows = [];
        $long = [];
        foreach ($data['rows'] as $i => $row) {
            $rows[] = ['_no' => $i + 1] + $row;
            foreach ($data['columns'] as $column) {
                $text = $row[$column['key']] ?? null;
                if ($column['type'] === 'text' && is_string($text) && (mb_strlen($text) > 300 || substr_count($text, "\n") > 5)) {
                    foreach ($this->chunks($text, 240) as $part => $chunk) {
                        $long[] = ['number' => $i + 1, 'field' => $column['label'], 'part' => $part + 1, 'text' => $chunk];
                    }
                }
            }
        }
        $summary = [['field' => 'Jenis rekap', 'value' => $data['title']], ['field' => 'Jumlah baris sumber', 'value' => $data['total']],
            ['field' => 'Dibuat oleh', 'value' => $data['actor']->full_name], ['field' => 'Dibuat pada', 'value' => $data['generated']],
            ['field' => 'Cakupan data', 'value' => $data['description']]];
        foreach ($data['filterLabels'] as $field => $value) {
            $summary[] = compact('field', 'value');
        }
        if ($long) {
            $summary[] = ['field' => 'Teks panjang', 'value' => 'Isi lengkap tetap tersimpan di Rincian. Sheet Uraian membagi teks panjang agar dapat dibaca tanpa tinggi baris berlebihan. No. mengacu pada baris sumber di Rincian.'];
        }
        $sheets = [['name' => 'Rincian', 'columns' => $columns, 'rows' => $rows, 'filter' => true],
            ['name' => 'Ringkasan', 'columns' => [['key' => 'field', 'label' => 'Keterangan', 'width' => 30, 'type' => 'text'], ['key' => 'value', 'label' => 'Nilai / cakupan', 'width' => 100, 'type' => 'mixed']], 'rows' => $summary, 'filter' => false]];
        if ($long) {
            $sheets[] = ['name' => 'Uraian', 'columns' => [['key' => 'number', 'label' => 'No. sumber', 'width' => 14, 'type' => 'integer'],
                ['key' => 'field', 'label' => 'Kolom', 'width' => 30, 'type' => 'text'], ['key' => 'part', 'label' => 'Bagian', 'width' => 12, 'type' => 'integer'],
                ['key' => 'text', 'label' => 'Uraian lengkap berurutan', 'width' => 86, 'type' => 'text']], 'rows' => $long, 'filter' => true];
        }
        $parts = [];
        $contentTypes = '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>';
        $book = '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets>';
        $rels = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($sheets as $i => $sheet) {
            $n = $i + 1;
            $parts['xl/worksheets/sheet'.$n.'.xml'] = $this->xml($this->sheet($sheet));
            $contentTypes .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $book .= '<sheet name="'.$sheet['name'].'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
            $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
        }
        $book .= '</sheets></workbook>';
        $rels .= '<Relationship Id="rId'.(count($sheets) + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $contentTypes .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>';
        foreach (['[Content_Types].xml' => $contentTypes, 'xl/workbook.xml' => $book, 'xl/_rels/workbook.xml.rels' => $rels,
            'xl/styles.xml' => $this->styles(), '_rels/.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>',
            'docProps/core.xml' => '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>'.$this->escape($data['title']).'</dc:title><dc:creator>'.$this->escape($data['actor']->full_name).'</dc:creator></cp:coreProperties>',
            'docProps/app.xml' => '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>DPE</Application></Properties>'] as $name => $xml) {
            $parts[$name] = $this->xml($xml);
        }

        // Put the OPC content-type declaration first, followed by the declared document parts.
        $contentTypePart = $parts['[Content_Types].xml'];
        unset($parts['[Content_Types].xml']);

        return $this->package(['[Content_Types].xml' => $contentTypePart] + $parts);
    }

    /**
     * Package the generated XML as a standard ZIP 2.0 archive.
     * All entry names are internal ASCII paths; sizes are known before writing.
     * This avoids the version-zero headers emitted by PharData for the old XLSX.
     */
    private function package(array $parts): string
    {
        $body = '';
        $directory = '';
        $now = now();
        $time = ($now->hour << 11) | ($now->minute << 5) | intdiv($now->second, 2);
        $date = ((max(1980, min(2107, $now->year)) - 1980) << 9) | ($now->month << 5) | $now->day;
        foreach ($parts as $name => $contents) {
            $compressed = function_exists('gzdeflate') ? gzdeflate($contents, 6) : false;
            $method = $compressed === false ? 0 : 8;
            $payload = $compressed === false ? $contents : $compressed;
            $crc = crc32($contents);
            $size = strlen($contents);
            $compressedSize = strlen($payload);
            $nameLength = strlen($name);
            $offset = strlen($body);
            // No encryption, data descriptor, extra fields, or ZIP64 is needed for these bounded exports.
            $body .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, $method, $time, $date,
                $crc, $compressedSize, $size, $nameLength, 0).$name.$payload;
            $directory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, $method, $time, $date,
                $crc, $compressedSize, $size, $nameLength, 0, 0, 0, 0, 0, $offset).$name;
        }

        return $body.$directory.pack('VvvvvVVv', 0x06054b50, 0, 0, count($parts), count($parts),
            strlen($directory), strlen($body), 0);
    }

    private function sheet(array $sheet): string
    {
        $last = $this->column(count($sheet['columns']));
        $lastRow = count($sheet['rows']) + 1;
        $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:'.$last.$lastRow.'"/><sheetViews><sheetView workbookViewId="0" showGridLines="1">';
        if ($sheet['filter']) {
            $xml .= '<pane xSplit="1" ySplit="1" topLeftCell="B2" activePane="bottomRight" state="frozen"/><selection pane="bottomRight" activeCell="B2" sqref="B2"/>';
        }
        $xml .= '</sheetView></sheetViews><sheetFormatPr defaultRowHeight="22"/><cols>';
        foreach ($sheet['columns'] as $i => $col) {
            $xml .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$col['width'].'" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData><row r="1" ht="38" customHeight="1">';
        foreach ($sheet['columns'] as $i => $col) {
            $xml .= $this->textCell($this->column($i + 1).'1', $col['label'], 1);
        }
        $xml .= '</row>';
        foreach ($sheet['rows'] as $i => $row) {
            $r = $i + 2;
            $height = 24;
            $cells = '';
            foreach ($sheet['columns'] as $j => $col) {
                $value = $row[$col['key']] ?? null;
                $style = ($i % 2 === 0 ? 2 : 3);
                $type = $col['type'];
                if ($value !== null && $value !== '' && in_array($type, ['date', 'datetime', 'month', 'time'], true)) {
                    $value = $this->serial((string) $value, $type);
                    $style = ['date' => 4, 'datetime' => 6, 'month' => 8, 'time' => 10][$type] + ($i % 2);
                } elseif ($type === 'integer' || ($type === 'mixed' && is_int($value))) {
                    $style = 12 + ($i % 2);
                }
                $ref = $this->column($j + 1).$r;
                if ($value === null || $value === '') {
                    $cells .= '<c r="'.$ref.'" s="'.$style.'"/>';
                } elseif (is_int($value) || is_float($value)) {
                    $cells .= '<c r="'.$ref.'" s="'.$style.'" t="n"><v>'.$value.'</v></c>';
                } else {
                    $text = (string) $value;
                    $height = max($height, min(409, 15 * max(1, array_sum(array_map(fn ($line) => max(1, ceil(mb_strlen($line) / max(8, $col['width'] - 3))), explode("\n", $text)))) + 8));
                    $cells .= $this->textCell($ref, $text, $style);
                }
            }
            $xml .= '<row r="'.$r.'" ht="'.$height.'" customHeight="1">'.$cells.'</row>';
        }
        $xml .= '</sheetData>';
        if ($sheet['filter']) {
            $xml .= '<autoFilter ref="A1:'.$last.$lastRow.'"/>';
        }
        return $xml.'<printOptions horizontalCentered="1"/><pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
    }

    private function styles(): string
    {
        $xml = '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="4"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/><numFmt numFmtId="165" formatCode="dd/mm/yyyy hh:mm"/><numFmt numFmtId="166" formatCode="mm/yyyy"/><numFmt numFmtId="167" formatCode="hh:mm"/></numFmts><fonts count="2"><font><sz val="11"/><color rgb="FF172D4D"/><name val="Arial"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font></fonts><fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F55C7"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF0F5FD"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="3"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFFFFFFF"/></left><right style="thin"><color rgb="FFFFFFFF"/></right><top style="thin"><color rgb="FFFFFFFF"/></top><bottom style="thin"><color rgb="FFFFFFFF"/></bottom><diagonal/></border><border><left style="thin"><color rgb="FFAFC1DC"/></left><right style="thin"><color rgb="FFAFC1DC"/></right><top style="thin"><color rgb="FFAFC1DC"/></top><bottom style="thin"><color rgb="FFAFC1DC"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="14"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>';
        foreach ([49, 164, 165, 166, 167, 3] as $format) {
            foreach ([0, 3] as $fill) {
                $xml .= '<xf numFmtId="'.$format.'" fontId="0" fillId="'.$fill.'" borderId="2" xfId="0" applyBorder="1" applyAlignment="1" applyNumberFormat="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>';
            }
        }
        return $xml.'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private function textCell(string $ref, string $text, int $style): string
    {
        if (mb_strlen($text) > 32767) {
            throw ValidationException::withMessages(['export' => 'Salah satu teks melebihi batas sel Excel. Persempit rekap atau periksa isi data.']);
        }
        // inlineStr stores user text literally, including leading =, +, -, @, tab or carriage return.
        return '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->escape($text).'</t></is></c>';
    }

    private function serial(string $value, string $type): float
    {
        if ($type === 'time') {
            $time = CarbonImmutable::parse($value, 'UTC');
            return ($time->hour * 3600 + $time->minute * 60 + $time->second) / 86400;
        }
        return CarbonImmutable::parse($value, 'UTC')->timestamp / 86400 + 25569;
    }

    private function escape(string $value): string
    {
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function chunks(string $text, int $size): array
    {
        $chunks = [];
        for ($offset = 0; $offset < mb_strlen($text);) {
            $chunk = mb_substr($text, $offset, $size);
            if (mb_strlen($chunk) === $size && preg_match('/.*\s/us', $chunk, $match) && mb_strlen($match[0]) >= $size / 2) {
                $chunk = $match[0];
            }
            $chunks[] = $chunk;
            $offset += mb_strlen($chunk);
        }
        return $chunks;
    }

    private function column(int $n): string
    {
        $name = '';
        while ($n > 0) {
            $n--;
            $name = chr(65 + $n % 26).$name;
            $n = intdiv($n, 26);
        }
        return $name;
    }

    private function xml(string $text): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.$text;
    }
}
