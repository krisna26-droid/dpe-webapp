<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\SaveAvailabilityRequest;
use App\Models\TeacherAvailability;
use App\Services\BranchOperationsService;
use App\Services\TeacherWorkspaceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __construct(private TeacherWorkspaceService $workspace, private BranchOperationsService $versions) {}

    public function index(Request $request): View
    {
        $actor = $this->workspace->actor($request->user());
        $options = $this->workspace->periodFilters($request, $actor, true);
        $filters = $options['filters'];
        $query = $this->workspace->slots($actor)->whereDate('available_on', '>=', $filters['from'])->whereDate('available_on', '<=', $filters['to']);
        if (! empty($filters['branch_id'])) { $query->where('branch_id', $filters['branch_id']); }
        if (! empty($filters['status'])) { $query->whereRaw('LOWER(TRIM(availability_status)) = ?', [strtolower($filters['status'])]); }
        if (! empty($filters['q'])) { $query->where('notes', 'like', '%'.$filters['q'].'%'); }
        $slots = $query->with('branch')->orderBy('available_on')->orderBy('starts_at')->orderBy('id')->paginate(15)->withQueryString();

        return view('teacher.availability.index', $options + compact('slots'));
    }

    public function create(Request $request): View
    {
        $actor = $this->workspace->actor($request->user());

        return view('teacher.availability.create', ['branches' => $this->workspace->slotOptions($actor)]);
    }

    public function store(SaveAvailabilityRequest $request): RedirectResponse
    {
        $slot = $this->workspace->saveSlot($request->user(), $request->validated());

        return redirect()->route('teacher.availability.show', $slot)->with('success', 'Slot ketersediaan berhasil ditambahkan.');
    }

    public function show(Request $request, TeacherAvailability $availability): View
    {
        $actor = $this->workspace->actor($request->user());
        $this->workspace->authorizeSlot($actor, $availability);

        return view('teacher.availability.show', ['slot' => $availability->load('branch'),
            'relatedSessions' => $this->workspace->relatedSlotSessions($actor, $availability)]);
    }

    public function edit(Request $request, TeacherAvailability $availability): View
    {
        $actor = $this->workspace->actor($request->user());
        $this->workspace->authorizeSlot($actor, $availability);

        return view('teacher.availability.edit', ['slot' => $availability->load('branch'),
            'branches' => $this->workspace->slotOptions($actor, $availability), 'recordVersion' => $this->versions->version($availability),
            'relatedSessions' => $this->workspace->relatedSlotSessions($actor, $availability)]);
    }

    public function update(SaveAvailabilityRequest $request, TeacherAvailability $availability): RedirectResponse
    {
        $slot = $this->workspace->saveSlot($request->user(), $request->validated(), $availability);

        return redirect()->route('teacher.availability.show', $slot)->with('success', 'Slot ketersediaan berhasil diperbarui.');
    }
}
