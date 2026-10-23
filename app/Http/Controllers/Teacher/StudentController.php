<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\TeacherWorkspaceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function __construct(private TeacherWorkspaceService $workspace) {}

    public function index(Request $request): View
    {
        $actor = $this->workspace->actor($request->user());
        $month = $this->workspace->month($request);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $filters['month'] = $month->format('Y-m');
        $query = $this->workspace->students($actor, $month);
        if (! empty($filters['q'])) { $query->where('full_name', 'like', '%'.$filters['q'].'%'); }
        if (! empty($filters['status'])) { $query->where('status', $filters['status']); }
        $students = $query->orderBy('full_name')->orderBy('id')->paginate(15)->withQueryString();

        return view('teacher.students.index', compact('students', 'filters', 'month') + [
            'rows' => $this->workspace->studentRows($students->getCollection(), $actor, $month),
        ]);
    }

    public function show(Request $request, Student $student): View
    {
        $actor = $this->workspace->actor($request->user());
        $month = $this->workspace->month($request);
        $student = $this->workspace->students($actor, $month)->whereKey($student->id)->first();
        abort_unless($student, 403);
        $row = $this->workspace->studentRows(collect([$student]), $actor, $month)->first();
        $groups = $this->workspace->studentGroups($actor, $student, $month);
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $sessionModels = $this->workspace->applySessionPeriod($this->workspace->sessions($actor), $month->toDateString(), $month->endOfMonth()->toDateString())
            ->where(fn ($q) => $q->whereHas('sessionStudents', fn ($p) => $p->where('student_id', $student->id))
                ->orWhereHas('classGroup.memberships', fn ($m) => $m->where('student_id', $student->id)))
            ->with($this->workspace->sessionRelations())->orderByRaw('COALESCE(actual_start_at, planned_start_at) DESC')->orderBy('id')->get();
        $rows = $this->workspace->sessionRows($sessionModels)->filter(fn ($r) => $r['participants']->contains('id', $student->id))->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $sessions = new LengthAwarePaginator($rows->forPage($page, 10)->values(), $rows->count(), 10, $page, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);

        return view('teacher.students.show', compact('row', 'month', 'groups', 'sessions'));
    }
}
