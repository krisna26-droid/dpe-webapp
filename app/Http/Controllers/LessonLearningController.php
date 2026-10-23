<?php

namespace App\Http\Controllers;

use App\Http\Requests\LessonLearningIndexRequest;
use App\Http\Requests\SaveLessonLearningRequest;
use App\Models\Branch;
use App\Models\ClassGroup;
use App\Models\LessonLearningAudit;
use App\Models\LessonSession;
use App\Models\Student;
use App\Services\LessonLearningService;
use App\Services\StudentAttendanceAccessService;
use App\Services\StudentAttendanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LessonLearningController extends Controller
{
    public function __construct(private LessonLearningService $learning, private StudentAttendanceAccessService $access, private StudentAttendanceService $attendance) {}

    public function index(LessonLearningIndexRequest $request): View
    {
        $filters = $request->validated();
        $month = Carbon::createFromFormat('!Y-m', $filters['month'] ?? now()->format('Y-m'));
        $visible = $this->access->sessions($request->user());
        $branches = Branch::query()->whereIn('id', (clone $visible)->select('branch_id'))->orderBy('name')->get();
        $groups = ClassGroup::query()->with('branch')->whereIn('id', (clone $visible)->select('class_group_id'))->orderBy('name')->get();
        $query = clone $visible;
        if (! empty($filters['branch_id'])) {
            abort_unless($branches->contains('id', $filters['branch_id']), 403);
            $query->where('branch_id', $filters['branch_id']);
        }
        if (! empty($filters['class_group_id'])) {
            if ($filters['class_group_id'] === 'private') {
                $query->whereNull('class_group_id');
            } else {
                abort_unless($groups->contains('id', $filters['class_group_id']), 403);
                $query->where('class_group_id', $filters['class_group_id']);
            }
        }
        $query->whereRaw('COALESCE(actual_start_at, planned_start_at) >= ?', [$month->toDateTimeString()])
            ->whereRaw('COALESCE(actual_start_at, planned_start_at) < ?', [$month->copy()->addMonth()->toDateTimeString()]);
        if (filled($filters['q'] ?? null)) {
            $term = '%'.$filters['q'].'%';
            $query->where(fn ($q) => $q->where('topic', 'like', $term)
                ->orWhereHas('classGroup', fn ($group) => $group->where('name', 'like', $term))
                ->orWhereHas('program', fn ($program) => $program->where('name', 'like', $term))
                ->orWhereHas('branch', fn ($branch) => $branch->where('name', 'like', $term)));
        }
        $total = (clone $query)->count();
        $complete = $this->filterCompletion(clone $query, true)->count();
        if (! empty($filters['completion'])) {
            $this->filterCompletion($query, $filters['completion'] === 'complete');
        }
        $sessions = $query->with($this->learning->relations())->orderByRaw('COALESCE(actual_start_at, planned_start_at) DESC')->orderBy('id')->paginate(15)->withQueryString();
        $rows = collect($sessions->items())->map(function ($session) {
            $filled = collect(array_keys(LessonLearningService::FIELDS))->filter(fn ($field) => filled($session->$field))->count();

            return [
                'session' => $session, 'meetingAt' => $this->attendance->meetingAt($session),
                'participants' => $this->learning->participants($session), 'filled' => $filled,
                'blockReason' => $this->learning->blockReason($session),
            ];
        });

        return view('lesson-learning.index', [
            'filters' => array_merge($filters, ['month' => $month->format('Y-m')]), 'month' => $month,
            'branches' => $branches, 'groups' => $groups, 'rows' => $rows, 'sessions' => $sessions,
            'total' => $total, 'complete' => $complete, 'pending' => $total - $complete,
            'routeBase' => $this->routeBase($request),
        ]);
    }

    public function edit(Request $request, LessonSession $lessonSession): View
    {
        $this->access->authorize($request->user(), $lessonSession);
        $lessonSession->load($this->learning->relations());
        $participants = $this->learning->participants($lessonSession);
        $history = LessonLearningAudit::query()->with('actor')->where('session_id', $lessonSession->id)
            ->orderByDesc('id')->paginate(10, ['*'], 'history_page')->withQueryString()->fragment('learning-history');
        $studentIds = collect($history->items())->flatMap(fn ($event) => array_merge(array_keys($event->before_snapshot['student_notes'] ?? []), array_keys($event->after_snapshot['student_notes'] ?? [])))
            ->merge($participants->keys())->unique();
        // Names are only resolved from snapshots of this authorized session.
        $names = Student::query()->whereIn('id', $studentIds)->pluck('full_name', 'id');
        $historyRows = collect($history->items())->map(function ($event) use ($names) {
            $changes = [];
            foreach (LessonLearningService::FIELDS as $field => $label) {
                $before = $event->before_snapshot[$field] ?? null;
                $after = $event->after_snapshot[$field] ?? null;
                if ($before !== $after) {
                    $changes[] = ['label' => $label, 'before' => $before, 'after' => $after];
                }
            }
            $notes = array_unique(array_merge(array_keys($event->before_snapshot['student_notes'] ?? []), array_keys($event->after_snapshot['student_notes'] ?? [])));
            foreach ($notes as $id) {
                $before = $event->before_snapshot['student_notes'][$id] ?? null;
                $after = $event->after_snapshot['student_notes'][$id] ?? null;
                if ($before !== $after) {
                    $changes[] = ['label' => 'Catatan '.($names[$id] ?? 'siswa'), 'before' => $before, 'after' => $after];
                }
            }

            return ['event' => $event, 'changes' => $changes];
        });

        return view('lesson-learning.edit', [
            'lessonSession' => $lessonSession, 'participants' => $participants,
            'meetingAt' => $this->attendance->meetingAt($lessonSession),
            'expectedSnapshot' => $this->learning->signature($lessonSession, $participants),
            'blockReason' => $this->learning->blockReason($lessonSession),
            'history' => $history, 'historyRows' => $historyRows,
            'routeBase' => $this->routeBase($request),
        ]);
    }

    public function save(SaveLessonLearningRequest $request, LessonSession $lessonSession): RedirectResponse
    {
        $changed = $this->learning->save($request->user(), $lessonSession, $request->validated());

        return redirect()->route($this->routeBase($request).'.edit', $lessonSession)
            ->with('success', $changed ? 'Catatan belajar berhasil disimpan.' : 'Tidak ada perubahan catatan belajar.');
    }

    private function routeBase(Request $request): string
    {
        return explode('.', $request->route()->getName())[0].'.learning-notes';
    }

    private function filterCompletion(Builder $query, bool $complete): Builder
    {
        return $query->where(function ($q) use ($complete) {
            foreach (LessonLearningService::FIELDS as $field => $label) {
                if ($complete) {
                    $q->whereNotNull($field)->whereRaw('TRIM('.$field.") <> ''");
                } else {
                    $q->orWhereNull($field)->orWhereRaw('TRIM('.$field.") = ''");
                }
            }
        });
    }
}
