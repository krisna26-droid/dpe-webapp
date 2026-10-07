<?php

namespace App\Http\Controllers;

use App\Http\Requests\LearningSkill\StoreLearningSkillRequest;
use App\Http\Requests\LearningSkill\UpdateLearningSkillRequest;
use App\Models\LearningSkill;
use App\Services\LearningSkillService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LearningSkillController extends Controller
{
    public function __construct(
        private LearningSkillService $learningSkillService
    ) {}

    public function index(): View
    {
        $learningSkills = LearningSkill::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->paginate(15);

        return view(
            'learning-skills.index',
            compact('learningSkills')
        );
    }

    public function create(): View
    {
        return view('learning-skills.create');
    }

    public function store(
        StoreLearningSkillRequest $request
    ): RedirectResponse {
        $learningSkill = $this->learningSkillService->create(
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.learning-skills.show',
                $learningSkill
            )
            ->with(
                'success',
                'Learning skill berhasil dibuat.'
            );
    }

    public function show(
        LearningSkill $learningSkill
    ): View {
        return view(
            'learning-skills.show',
            compact('learningSkill')
        );
    }

    public function edit(
        LearningSkill $learningSkill
    ): View {
        return view(
            'learning-skills.edit',
            compact('learningSkill')
        );
    }

    public function update(
        UpdateLearningSkillRequest $request,
        LearningSkill $learningSkill
    ): RedirectResponse {
        $learningSkill = $this->learningSkillService->update(
            $learningSkill,
            $request->validated()
        );

        return redirect()
            ->route(
                'superadmin.learning-skills.show',
                $learningSkill
            )
            ->with(
                'success',
                'Learning skill berhasil diperbarui.'
            );
    }

    public function destroy(
        LearningSkill $learningSkill
    ): RedirectResponse {
        $this->learningSkillService->delete(
            $learningSkill
        );

        return redirect()
            ->route(
                'superadmin.learning-skills.index'
            )
            ->with(
                'success',
                'Learning skill berhasil dihapus.'
            );
    }
}
