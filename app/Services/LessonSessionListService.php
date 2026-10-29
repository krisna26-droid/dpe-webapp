<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class LessonSessionListService
{
    private const FILTER_LIMITS = [
        'q' => 120, 'branch_id' => 36, 'program_id' => 36, 'teacher_id' => 36,
        'status' => 20, 'from' => 10, 'to' => 10,
    ];

    public function __construct(private BranchOperationsService $operations) {}

    public function listing(Request $request, Builder $query, string $prefix): array
    {
        $context = $this->operations->filters($request, 'sessions');
        $f = $context['filters'];
        $search = $f['q'] ?? '';
        $sessions = $query->with(['branch', 'teacher.user', 'program', 'classGroup'])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('topic', 'like', '%'.$search.'%')
                ->orWhereHas('classGroup', fn ($g) => $g->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%'))
                ->orWhereHas('teacher.user', fn ($u) => $u->where('full_name', 'like', '%'.$search.'%')->orWhere('username', 'like', '%'.$search.'%'))))
            ->when($f['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($f['program_id'] ?? null, fn ($q, $id) => $q->where('program_id', $id))
            ->when($f['teacher_id'] ?? null, fn ($q, $id) => $q->where('teacher_id', $id))
            ->when($f['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($f['from'] ?? null, fn ($q, $day) => $q->whereDate('planned_start_at', '>=', $day))
            ->when($f['to'] ?? null, fn ($q, $day) => $q->whereDate('planned_start_at', '<=', $day))
            ->orderByDesc('planned_start_at')->orderBy('id')->paginate(15)->withQueryString();
        $context['operationRoutePrefix'] = $prefix;
        $context['operationsFilterOptions']['action'] = route($prefix.'.lesson-sessions.index');

        return $context + ['lessonSessions' => $sessions];
    }

    public function fromIndex(array $filters, int $page): array
    {
        $context = $this->normalize(array_replace($filters, ['page' => $page]));

        return $context ? ['list' => $context] : [];
    }

    public function parameters(Request $request, array $parameters = []): array
    {
        $context = $this->backParameters($request);

        return $context ? $parameters + ['list' => $context] : $parameters;
    }

    public function backParameters(Request $request): array
    {
        return $this->normalize($request->query('list', []));
    }

    private function normalize(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }
        $context = [];
        foreach (self::FILTER_LIMITS as $key => $limit) {
            $value = $values[$key] ?? null;
            if (is_string($value) && $value !== '' && mb_strlen($value) <= $limit) {
                $context[$key] = $value;
            }
        }
        $page = $values['page'] ?? null;
        if ((is_string($page) || is_int($page)) && ctype_digit((string) $page) && $page > 1 && $page <= 2147483647) {
            $context['page'] = (int) $page;
        }

        return $context;
    }
}
