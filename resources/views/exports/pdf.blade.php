<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><style>
@page { margin: 28pt 30pt 36pt; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #172d4d; margin: 0; }
h1 { margin: 0 0 8pt; font-size: 16pt; color: #1f55c7; }
.context { margin-bottom: 14pt; line-height: 1.5; }
.context p { margin: 0 0 5pt; }
.filters { margin: 0 0 10pt; line-height: 1.5; }
.filter { margin-right: 12pt; }
table { border-collapse: collapse; table-layout: fixed; width: {{ $tableWidth }}pt; }
thead { display: table-header-group; }
th { background: #1f55c7; color: white; font-weight: bold; text-align: center; padding: 7pt 5pt; border-right: .5pt solid #dbe7ff; }
td { padding: 5pt; vertical-align: top; border-bottom: .5pt solid #d9e3f2; line-height: 1.3; }
tr.even td { background: #f0f5fd; }
td:first-child { text-align: center; color: #526681; }
.empty { padding: 24pt; text-align: center; }
.continued td { border-top: .5pt dashed #b6c9e8; }
</style></head><body>
<h1>DPE — {{ $data['title'] }}</h1>
<div class="context"><p>{{ $data['description'] }}</p><p><strong>{{ $data['total'] }} baris sumber</strong> · Dibuat oleh {{ $data['actor']->full_name }} · {{ $data['generated'] }}</p><p>Rekap ini digunakan untuk pemeriksaan operasional. Rapor resmi siswa tersedia melalui menu Rapor Tiga Bulan. Waktu sesi dan absensi memakai nilai waktu yang tersimpan.</p></div>
<div class="filters">@foreach ($data['filterLabels'] as $label => $value)<span class="filter"><strong>{{ $label }}:</strong> {{ $value }}</span> @endforeach</div>
<table><thead><tr>
    @foreach ($columns as $column)
        <th style="width: {{ 100 * $column['points'] / $tableWidth }}%;">{{ $column['label'] }}</th>
    @endforeach
</tr></thead><tbody>
@forelse ($rows as $row)
    <tr class="{{ $row['even'] ? 'even' : '' }} {{ $row['continued'] ? 'continued' : '' }}">
        @foreach ($row['cells'] as $lines)
            <td>
                @foreach ($lines as $line)
                    {{ $line }}@if (! $loop->last)<br>@endif
                @endforeach
            </td>
        @endforeach
    </tr>
@empty
    <tr><td class="empty" colspan="{{ count($columns) }}">Belum ada data sesuai filter.</td></tr>
@endforelse
</tbody></table></body></html>
