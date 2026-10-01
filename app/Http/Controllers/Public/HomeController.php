<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\PublicContentSection;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $sections = PublicContentSection::query()
            ->where('is_published', true)
            ->orderBy('display_order')
            ->get();

        return view('public.home', [
            'sections' => $sections,
        ]);
    }
}