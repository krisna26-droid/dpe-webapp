<form method="POST" action="{{ route($routeBase.'.save', [$attendanceSession, $student]) }}" class="dpe-student-attendance-quick"
    data-attendance-quick data-edit-url="{{ $editUrl }}" data-requires-reason="{{ $cell['requires_reason'] ? '1' : '0' }}"
    data-session-id="{{ $attendanceSession->id }}" data-student-id="{{ $student->id }}"
    data-student-name="{{ $student->full_name }}" data-meeting-label="{{ $cell['date']->locale('id')->translatedFormat('d F Y, H:i') }}"
    data-attendance-cell="{{ json_encode(['status' => $cell['status'], 'label' => $cell['label'], 'is_attending' => $cell['is_attending'], 'can_record' => $cell['can_record'], 'requires_reason' => (bool) $cell['requires_reason'], 'version' => (int) $cell['version'], 'contribution' => $cell['contribution']]) }}">
    @csrf
    <input type="hidden" name="expected_version" value="{{ $cell['version'] }}">
    <input type="hidden" name="attendance_status" value="">
    <input type="checkbox" name="attendance_status" value="present" @checked($cell['is_attending']) @disabled(! $cell['can_record']) aria-label="Kehadiran {{ $student->full_name }}, {{ $cell['date']->format('d M Y H:i') }}" data-attendance-check>
    @if ($cell['can_record'] && ! $cell['requires_reason'])
        <button type="button" class="dpe-dashboard-button dpe-dashboard-button--soft dpe-student-attendance-quick-save" data-attendance-open>Catat</button>
    @endif
    <noscript><small>Gunakan Catat / detail untuk mengisi kehadiran.</small></noscript>
</form>
