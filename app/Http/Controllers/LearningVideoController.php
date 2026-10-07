<?php

namespace App\Http\Controllers;

use App\Http\Requests\LearningVideo\StoreLearningVideoRequest;
use App\Http\Requests\LearningVideo\UpdateLearningVideoRequest;
use App\Models\LearningVideo;
use App\Services\LearningVideoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LearningVideoController extends Controller
{
    public function __construct(
        private LearningVideoService $learningVideoService
    ) {}

    public function index(): View
    {
        $learningVideos = LearningVideo::query()
            ->with([
                'student',
                'teacher',
                'session',
            ])
            ->latest('video_on')
            ->latest('created_at')
            ->paginate(15);

        return view('learning-videos.index', compact('learningVideos'));
    }

    public function create(): View
    {
        return view('learning-videos.create');
    }

    public function store(
        StoreLearningVideoRequest $request
    ): RedirectResponse {
        $learningVideo = $this->learningVideoService->create(
            $request->validated()
        );

        return redirect()
            ->route('superadmin.learning-videos.show', $learningVideo)
            ->with('success', 'Learning video berhasil dibuat.');
    }

    public function show(
        LearningVideo $learningVideo
    ): View {
        $learningVideo->load([
            'student',
            'teacher',
            'session',
        ]);

        return view(
            'learning-videos.show',
            compact('learningVideo')
        );
    }

    public function edit(
        LearningVideo $learningVideo
    ): View {
        $learningVideo->load([
            'student',
            'teacher',
            'session',
        ]);

        return view(
            'learning-videos.edit',
            compact('learningVideo')
        );
    }

    public function update(
        UpdateLearningVideoRequest $request,
        LearningVideo $learningVideo
    ): RedirectResponse {
        $learningVideo = $this->learningVideoService->update(
            $learningVideo,
            $request->validated()
        );

        return redirect()
            ->route('superadmin.learning-videos.show', $learningVideo)
            ->with('success', 'Learning video berhasil diperbarui.');
    }

    public function destroy(
        LearningVideo $learningVideo
    ): RedirectResponse {
        $this->learningVideoService->delete($learningVideo);

        return redirect()
            ->route('superadmin.learning-videos.index')
            ->with('success', 'Learning video berhasil dihapus.');
    }
}
