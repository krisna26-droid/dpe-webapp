<?php

namespace App\Http\Controllers;

use App\Models\LessonScheduleSeries;
use App\Models\LessonSession;
use App\Services\BranchOperationsService;
use App\Services\LessonSeriesService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LessonSeriesController extends Controller
{
    public function __construct(private LessonSeriesService $seriesService, private BranchOperationsService $operations) {}

    private function prefix(Request $request): string { return explode('.', $request->route()->getName())[0]; }

    private function draftsKey(Request $request): string
    {
        return 'lesson_series_form_drafts.'.$request->user()->id.'.'.$this->prefix($request);
    }

    private function formValues(Request $request, string $operation, ?string $seriesId = null, ?string $anchorId = null): array
    {
        if ($request->boolean('reset')) { return ['formValues' => [], 'formDraftId' => null, 'errors' => new ViewErrorBag()]; }
        $id = $request->query('draft');
        $draft = null;
        if ($id !== null) {
            abort_unless(is_string($id) && Str::isUuid($id), 404);
            $draft = $request->session()->get($this->draftsKey($request).'.'.$id);
            abort_unless($draft && $draft['operation'] === $operation && $draft['series_id'] === $seriesId && $draft['anchor_id'] === $anchorId, 404);
            if ($operation === 'create') { $this->operations->authorizeBranch($request->user(), $draft['data']['branch_id']); }
        }
        $values = $draft['data'] ?? [];
        if (! $draft || ($request->session()->has('errors') && $request->session()->getOldInput('draft_id') === $id)) {
            $values = array_replace($values, $request->session()->getOldInput());
        }

        return ['formValues' => $values, 'formDraftId' => $id];
    }

    private function rememberDraft(Request $request, array $preview): string
    {
        $drafts = $request->session()->get($this->draftsKey($request), []);
        $id = $request->input('draft_id');
        $context = ['operation' => $preview['operation'], 'series_id' => $preview['series']?->id, 'anchor_id' => $preview['anchor']?->id];
        if (! is_string($id) || ! Str::isUuid($id) || ! isset($drafts[$id])
            || array_intersect_key($drafts[$id], $context) !== $context) { $id = (string) Str::uuid(); }
        unset($drafts[$id]);
        $drafts[$id] = $context + ['data' => $preview['data']];
        $request->session()->put($this->draftsKey($request), array_slice($drafts, -20, null, true));

        return $id;
    }

    public function cancel(Request $request): RedirectResponse
    {
        $id = $request->validate(['draft_id' => ['required', 'uuid']])['draft_id'];
        $key = $this->draftsKey($request).'.'.$id;
        $draft = $request->session()->get($key);
        abort_unless($draft, 404);
        $parameters = ['reset' => 1];
        $action = 'create';
        if ($draft['operation'] === 'future') {
            $series = LessonScheduleSeries::findOrFail($draft['series_id']);
            $anchor = $this->seriesService->anchor($request->user(), $series, $draft['anchor_id']);
            $parameters = [$series, $anchor, 'reset' => 1];
            $action = 'edit';
        } else { $this->operations->authorizeBranch($request->user(), $draft['data']['branch_id']); }
        $request->session()->forget($key);

        return redirect()->route($this->prefix($request).'.lesson-series.'.$action, $parameters);
    }

    public function create(Request $request): View
    {
        $options = $this->operations->options($request->user());
        abort_if($options['branches']->isEmpty(), 403, 'Belum ada cabang aktif yang dapat Anda kelola.');

        return view('lesson-series.create', array_replace($options, $this->formValues($request, 'create'),
            ['operationRoutePrefix' => $this->prefix($request), 'dayLabels' => LessonSeriesService::DAYS]));
    }

    private function previewResponse(Request $request, array $preview, ?ValidationException $error = null): Response
    {
        $data = $preview + ['operationRoutePrefix' => $this->prefix($request), 'dayLabels' => LessonSeriesService::DAYS,
            'formDraftId' => $this->rememberDraft($request, $preview)];
        if ($error) { $data['errors'] = (new ViewErrorBag())->put('default', $error->validator->errors()); }

        return response()->view('lesson-series.preview', $data, $error ? 422 : 200);
    }

    private function invalid(Request $request, ValidationException $error, string $route, array $parameters = []): RedirectResponse
    {
        if ($id = $request->input('draft_id')) { $parameters['draft'] = $id; }
        return redirect()->route($this->prefix($request).'.lesson-series.'.$route, $parameters)
            ->withErrors($error->validator)->withInput($request->except('_token'));
    }

    public function preview(Request $request): Response|RedirectResponse
    {
        try { return $this->previewResponse($request, $this->seriesService->previewCreate($request->user(), $request->all())); }
        catch (ValidationException $error) { return $this->invalid($request, $error, 'create'); }
    }

    public function store(Request $request): RedirectResponse|Response
    {
        try {
            $series = $this->seriesService->create($request->user(), $request->all());

            return redirect()->route($this->prefix($request).'.lesson-series.show', $series)
                ->with('success', 'Seri jadwal tersimpan. '.$series->sessions()->count().' pertemuan tersedia untuk Teacher.');
        } catch (ValidationException $error) {
            try { return $this->previewResponse($request, $this->seriesService->previewCreate($request->user(), $request->all()), $error); }
            catch (ValidationException) { return $this->invalid($request, $error, 'create'); }
        }
    }

    public function show(Request $request, LessonScheduleSeries $series): View
    {
        $this->seriesService->authorize($request->user(), $series);
        $series->load(['branch', 'classGroup', 'createdBy']);
        $sessions = $series->sessions()->with('teacher.user')->orderBy('planned_start_at')->orderBy('id')->paginate(30)->withQueryString();
        $changes = $series->changes()->with('actor')->orderByDesc('created_at')->orderByDesc('id')->paginate(10, ['*'], 'history_page')->fragment('series-history');

        return view('lesson-series.show', compact('series', 'sessions', 'changes')
            + ['operationRoutePrefix' => $this->prefix($request), 'dayLabels' => LessonSeriesService::DAYS]);
    }

    public function edit(Request $request, LessonScheduleSeries $series, LessonSession $lessonSession): View
    {
        $anchor = $this->seriesService->anchor($request->user(), $series, $lessonSession->id);
        $series->load(['branch', 'classGroup']);
        $options = $this->operations->options($request->user(), $anchor);

        return view('lesson-series.edit', array_replace($options, $this->formValues($request, 'future', $series->id, $anchor->id), ['series' => $series, 'anchor' => $anchor,
            'operationRoutePrefix' => $this->prefix($request)]));
    }

    public function previewFuture(Request $request, LessonScheduleSeries $series, LessonSession $lessonSession): Response|RedirectResponse
    {
        $anchor = $this->seriesService->anchor($request->user(), $series, $lessonSession->id);
        try { return $this->previewResponse($request, $this->seriesService->previewFuture($request->user(), $series, $anchor, $request->all())); }
        catch (ValidationException $error) { return $this->invalid($request, $error, 'edit', [$series, $anchor]); }
    }

    public function updateFuture(Request $request, LessonScheduleSeries $series, LessonSession $lessonSession): Response|RedirectResponse
    {
        $anchor = $this->seriesService->anchor($request->user(), $series, $lessonSession->id);
        try {
            $updated = $this->seriesService->updateFuture($request->user(), $series, $anchor, $request->all());

            return redirect()->route($this->prefix($request).'.lesson-series.show', $updated)
                ->with('success', 'Perubahan seri tersimpan. Pertemuan yang sudah memiliki pelaksanaan atau catatan tetap dipertahankan.');
        } catch (ValidationException $error) {
            try { return $this->previewResponse($request, $this->seriesService->previewFuture($request->user(), $series, $anchor, $request->all()), $error); }
            catch (ValidationException) { return $this->invalid($request, $error, 'edit', [$series, $anchor]); }
        }
    }
}
