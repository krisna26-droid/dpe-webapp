<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Student's Evaluation</title>
<style>
@page { margin: {{ $headerHeight + 24 }}pt 42pt 42pt; }
body { font-family: 'DejaVu Serif', serif; font-size: 10.4pt; line-height: 1.3; color: #111; }
.letterhead { position: fixed; top: -{{ $headerHeight + 12 }}pt; left: 0; right: 0; height: {{ $headerHeight }}pt; border-bottom: 1.3pt solid #111; }
.logo { position: absolute; top: 3pt; left: 0; width: auto; height: auto; max-width: 56pt; max-height: 56pt; }
.identity { margin-left: 65pt; text-align: center; }
.identity-block { margin-bottom: 2pt; }
.organization_name { font-weight: bold; }
.slogan { font-style: italic; }
.text-line { white-space: pre; min-height: 13.52pt; }
h1 { font-size: 14pt; margin: 0 0 12pt; text-align: center; }
.student { margin-bottom: 10pt; }
.student table { width: 100%; border-collapse: collapse; }
.student td { padding: 0; vertical-align: top; }
.student-label { width: {{ $studentLabelWidth }}pt; }
.rubric { margin: 0 0 12pt; }
.period { margin: 0 0 10pt; }
.evaluation { border-collapse: collapse; width: 100%; table-layout: fixed; }
.evaluation thead { display: table-header-group; }
.evaluation th, .evaluation td { border: .7pt solid #111; padding: 5pt; vertical-align: top; }
.evaluation th { background: #13a6e8; color: #111; text-align: center; font-weight: bold; }
.evaluation th.number { width: 5%; }
.evaluation th.skill { width: 15%; }
.evaluation th.description { width: 70%; }
.evaluation th.score { width: 10%; }
.evaluation--expanded th.skill { width: 22%; }
.evaluation--expanded th.description { width: 63%; }
.evaluation--monthly th.skill { width: 18%; }
.evaluation--monthly th.description { width: 57%; }
.evaluation--monthly th.score { width: 20%; }
.evaluation tr { page-break-inside: avoid; }
.number, .score { text-align: center; }
.continued { display: block; font-size: 8pt; }
.section { margin-top: 13pt; }
.section-title { font-weight: bold; margin-bottom: 3pt; }
.monthly-report .section-title { page-break-after: avoid; }
.section-start { page-break-inside: avoid; }
.signatures { width: 100%; table-layout: fixed; border-collapse: collapse; margin-top: 20pt; page-break-inside: avoid; }
.signatures td { width: 50%; text-align: center; vertical-align: top; padding: 0 8pt; }
.signature-title { height: 28pt; font-weight: bold; }
.signature-area { height: 96pt; color: #666; font-size: 8pt; padding-top: 8pt; }
.signature-image { width: auto; height: auto; max-width: 200pt; max-height: 90pt; }
.signature-name { padding-top: 6pt; font-weight: bold; }
.signatures .text-line { white-space: pre; }
.closing-keep { page-break-inside: avoid; }
</style></head><body class="{{ ($monthly ?? false) ? 'monthly-report' : '' }}">
<div class="letterhead"><img class="logo" src="{{ $snapshot['template']['logo_data_uri'] }}" alt="Logo"><div class="identity">
@foreach ($header as $key => $block)<div class="identity-block {{ $key }}" style="font-size: {{ $block['size'] }}pt; line-height: 1.3;">
@foreach ($block['lines'] as $line)<div>{{ $line }}</div>
@endforeach</div>
@endforeach
</div></div>
<h1>Student's Evaluation</h1>
<div class="student"><table><tr><td class="student-label"><strong>Student's Name:</strong></td><td>
@foreach ($studentLines as $line)<div class="text-line">{{ $line }}</div>
@endforeach</td></tr></table></div>
@unless($monthly ?? false)<div class="rubric">A: 90 - 100 (Very Good)<br>B: 80 - 89 (Good)<br>C: 70 - 79 (Fair)</div>@endunless
<div class="period"><strong>Learning Period:</strong> {{ $period }}</div>
<table class="evaluation {{ ($monthly ?? false) ? 'evaluation--monthly' : '' }} {{ ($expandedSkills ?? false) ? 'evaluation--expanded' : '' }}">
<thead><tr><th class="number">No</th><th class="skill">Skills</th><th class="description">Descriptions</th><th class="score">{{ ($monthly ?? false) ? 'Progress' : 'Score' }}</th></tr></thead><tbody>
@foreach ($rows as $row)<tr><td class="number">{{ $row['continued'] ? '' : $row['number'] }}</td><td>@foreach($row['label_lines'] ?? [$row['label']] as $labelLine)<div>{{ $labelLine }}</div>@endforeach
@if ($row['continued'])<span class="continued">(continued)</span>
@endif</td><td>
@foreach ($row['lines'] as $line)<div class="text-line">{{ $line === '' ? "\u{00A0}" : $line }}</div>
@endforeach</td><td class="score">{{ $row['score'] }}</td></tr>
@endforeach
</tbody></table>
@unless($monthly ?? false)<div class="section"><div class="section-title">Note:</div>
@foreach ($noteLines as $line)<div class="text-line">{{ $line === '' ? "\u{00A0}" : $line }}</div>
@endforeach</div>@endunless
@foreach($sections ?? [] as $section)<div class="section"><div class="section-start"><div class="section-title">{{ $section['title'] }}</div>
@foreach(array_slice($section['lines'], 0, 2) as $line)<div class="text-line">{{ $line === '' ? "\u{00A0}" : $line }}</div>@endforeach</div>
@foreach(array_slice($section['lines'], 2) as $line)<div class="text-line">{{ $line === '' ? "\u{00A0}" : $line }}</div>@endforeach</div>@endforeach
@if($videoBlocks ?? [])<div class="section"><div class="section-title">Learning Videos:</div>
@foreach($videoBlocks as $video)<div style="margin-bottom:8pt;">@foreach($video['lines'] as $line)<div class="text-line">{{ $line }}</div>@endforeach
<a href="{{ $video['url'] }}">Open learning video</a></div>@endforeach</div>@endif
<div class="section">
@if(($expandedSkills ?? false) && count($documentationLines) <= 12)<div class="section-start">@endif
@foreach ($documentationLines as $line)<div class="text-line">{{ $line === '' ? "\u{00A0}" : $line }}</div>
@endforeach
@if(($expandedSkills ?? false) && count($documentationLines) <= 12)</div>@endif
<div style="height:5pt"></div>
@foreach ($socialLines as $line)<div class="text-line">{{ $line === '' ? "\u{00A0}" : $line }}</div>
@endforeach</div>
<div class="{{ count($closingLines) <= 12 && count($teacherLines) <= 8 && count($managerLines) <= 8 ? 'closing-keep' : '' }}"><div class="section">
@foreach ($closingLines as $line)<div class="text-line">{{ $line === '' ? "\u{00A0}" : $line }}</div>
@endforeach</div>
<table class="signatures"><tr><td><div class="signature-title">Teacher's Sign</div><div class="signature-area">@if($snapshot['signatures']['teacher'] ?? null)<img class="signature-image" src="{{ $snapshot['signatures']['teacher']['data_uri'] }}" alt="Teacher signature">@else{{ $snapshot['template']['signature_notice'] }}@endif</div><div class="signature-name">
@foreach ($teacherLines as $line)<div class="text-line">{{ $line }}</div>
@endforeach</div></td><td><div class="signature-title">Manager of Dharma Private English</div><div class="signature-area">@if($snapshot['signatures']['manager'] ?? null)<img class="signature-image" src="{{ $snapshot['signatures']['manager']['data_uri'] }}" alt="Manager signature">@else{{ $snapshot['template']['signature_notice'] }}@endif</div><div class="signature-name">
@foreach ($managerLines as $line)<div class="text-line">{{ $line }}</div>
@endforeach</div></td></tr></table></div>
</body></html>
