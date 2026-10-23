<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\PublicWebsiteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct(private readonly PublicWebsiteService $website) {}

    public function index(): View
    {
        return $this->page('home');
    }

    public function about(): View
    {
        return $this->page('about');
    }

    public function programs(): View
    {
        return $this->page('programs');
    }

    public function program(string $id): View
    {
        $site = $this->website->data();
        $program = collect($site['programs'])->firstWhere('id', $id);
        abort_unless($program, 404);

        return view('public.program', compact('site', 'program'));
    }

    public function branches(): View
    {
        return $this->page('branches');
    }

    public function contact(Request $request): View
    {
        $site = $this->website->data();
        $program = $request->query('program');
        if (is_string($program) && trim($program) !== '') {
            $site['contact']['whatsapp_url'] = $this->website->whatsapp(
                $site['contact']['whatsapp'],
                'Halo DPE, saya ingin berkonsultasi tentang program '.mb_substr(trim($program), 0, 120).'.'
            );
        }

        return view('public.contact', compact('site'));
    }

    private function page(string $name): View
    {
        return view('public.'.$name, ['site' => $this->website->data()]);
    }
}
