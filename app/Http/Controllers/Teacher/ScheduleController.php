<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\LessonSession;
use App\Services\StudentAttendanceService;
use App\Services\TeacherWorkspaceService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function __construct(private TeacherWorkspaceService $workspace, private StudentAttendanceService $attendance) {}

    public function index(Request $request): View
    {
        $actor = $this->workspace->actor($request->user());
        $options = $this->workspace->periodFilters($request, $actor, false, 'week');
        $filters = $options['filters'];
        $query = $this->workspace->applySessionPeriod($this->workspace->sessions($actor), $filters['from'], $filters['to']);
        foreach (['branch_id', 'program_id'] as $field) {
            if (! empty($filters[$field])) { $query->where($field, $filters[$field]); }
        }
        if (! empty($filters['status'])) {
            if ($filters['status'] === 'rescheduled') {
                $query->where(fn ($q) => $q->whereRaw('LOWER(TRIM(status)) = ?', ['rescheduled'])
                    ->orWhereNot(fn ($q) => $q->notSuperseded()));
            } else {
                $query->notSuperseded();
                if ($filters['status'] === 'cancelled') {
                    $query->whereRaw('LOWER(TRIM(status)) IN (?, ?, ?)', ['cancelled', 'canceled', 'dibatalkan']);
                } else { $query->whereRaw('LOWER(TRIM(status)) = ?', [$filters['status']]); }
            }
        }
        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(fn ($q) => $q->where('topic', 'like', $term)
                ->orWhereHas('classGroup', fn ($g) => $g->where('name', 'like', $term)->orWhere('code', 'like', $term))
                ->orWhereHas('program', fn ($p) => $p->where('name', 'like', $term)));
        }
        $sessions = $query->with($this->workspace->sessionRelations())
            ->orderByRaw('COALESCE(actual_start_at, planned_start_at)')->orderBy('id')->paginate(12)->withQueryString();

        return view('teacher.schedule.index', $options + [
            'sessions' => $sessions, 'rows' => $this->workspace->sessionRows($sessions->getCollection()),
        ]);
    }

    public function show(Request $request, LessonSession $lessonSession): View
    {
        $actor = $this->workspace->actor($request->user());
        $this->workspace->authorizeSession($actor, $lessonSession);
        $lessonSession->load($this->workspace->sessionRelations());
        $row = $this->workspace->sessionRows(collect([$lessonSession]))->first();
        $month = CarbonImmutable::instance($row['meetingAt'])->startOfMonth();
        $binaanIds = $this->workspace->students($actor, $month)->pluck('id');
        $participants = $row['participants']->map(fn ($student) => [
            'student' => $student, 'cell' => $this->attendance->cell($lessonSession, $student),
            'canViewProfile' => $binaanIds->contains($student->id),
        ]);
        $relatedIds = $lessonSession->replacementSessions->pluck('id')->push($lessonSession->rescheduled_from_session_id)->filter()->unique();
        $related = $this->workspace->sessions($actor)->whereIn('id', $relatedIds)
            ->with(['branch', 'program', 'classGroup'])->orderBy('planned_start_at')->get();

        return view('teacher.schedule.show', compact('row', 'participants', 'month', 'related'));
    }
}
