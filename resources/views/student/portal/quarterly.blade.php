@extends('layouts.dashboard')
@section('title', 'Rapor Tiga Bulan')
@section('heading', 'Rapor Tiga Bulan')
@section('description', 'PDF final dari salinan rapor yang sudah disetujui.')
@push('head')
    @include('student.portal._head')
@endpush
@section('content')
    @include('student.portal._nav', ['back' => route('student.reports.index'), 'backLabel' => 'Kembali ke Rapor Siswa'])
    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>{{ $public['student_name'] }} · {{ $period }}</h2><p>{{ $public['teacher_name'] }} · PDF versi {{ $file->version_number }}</p></div><span class="dpe-dashboard-badge dpe-dashboard-badge--success">Tersedia</span></header>
        <div class="dpe-dashboard-card__body dpe-portal-actions dpe-portal-spaced">
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" target="_blank" rel="noopener noreferrer" href="{{ route('student.reports.pdf', [$quarterlyReport, $file]) }}">Pratinjau PDF</a>
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('student.reports.pdf', [$quarterlyReport, $file, 'download' => 1]) }}">Unduh PDF</a>
        </div>
        <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-portal-skills"><thead><tr><th>Skill</th><th>Skor</th><th>Deskripsi</th></tr></thead><tbody>
            @foreach (\App\Services\QuarterlyReportContentService::SKILLS as $code => $label)
                <tr><td><strong>{{ $label }}</strong></td><td><span class="dpe-dashboard-badge">{{ $public['skills'][$code]['score'] }}</span></td><td class="dpe-portal-text">{{ $public['skills'][$code]['description'] }}</td></tr>
            @endforeach
        </tbody></table></div>
        <div class="dpe-dashboard-card__body"><h3>Note</h3><p class="dpe-portal-text">{{ $public['parent_note'] }}</p></div>
    </article>
@endsection

