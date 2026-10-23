<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebsiteContentDraftRequest;
use App\Services\PublicWebsiteService;
use App\Services\WebsiteContentDefinition;
use App\Services\WebsiteContentService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WebsiteContentController extends Controller
{
    public function __construct(private WebsiteContentService $content) {}

    public function index(Request $request)
    {
        $input = $request->validate(['tab' => ['nullable', Rule::in(array_keys(WebsiteContentDefinition::TABS))],
            'q' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1']]);
        $tab = $input['tab'] ?? 'identity';
        $query = trim($input['q'] ?? '');
        $items = collect($this->content->rows($request->user(), $tab));
        if ($query !== '') {
            $items = $items->filter(fn ($row) => mb_stripos($row['name'].' '.$row['label'], $query) !== false)->values();
        }
        $page = (int) ($input['page'] ?? 1);
        $rows = new LengthAwarePaginator($items->slice(($page - 1) * 15, 15)->values(), $items->count(), 15, $page,
            ['path' => route('superadmin.website.index'), 'query' => ['tab' => $tab, 'q' => $query]]);
        return view('superadmin.website.index', compact('rows', 'tab', 'query'));
    }

    public function create(Request $request)
    {
        if (! $request->has('kind')) {
            return view('superadmin.website.create');
        }
        $data = $request->validate(['kind' => ['required', Rule::in(array_keys(WebsiteContentDefinition::REPEATED))]]);
        return $this->edit($request, $data['kind'].'.'.Str::uuid());
    }

    public function edit(Request $request, string $key)
    {
        return view('superadmin.website.edit', $this->content->editor($request->user(), $key));
    }

    public function save(WebsiteContentDraftRequest $request, string $key)
    {
        $this->content->save($request->user(), $key, $request->validated());
        return redirect()->route('superadmin.website.edit', $key)->with('success', 'Draft tersimpan. Periksa pratinjau sebelum menayangkan perubahan.');
    }

    public function publish(Request $request, string $key)
    {
        $this->content->publish($request->user(), $key, $this->expected($request));
        return redirect()->route('superadmin.website.edit', $key)->with('success', 'Perubahan bagian ini sudah diterapkan pada website.');
    }

    public function discard(Request $request, string $key)
    {
        $this->content->discard($request->user(), $key, $this->expected($request));
        return redirect()->route('superadmin.website.index', ['tab' => WebsiteContentDefinition::tab($key)])
            ->with('success', 'Draft dibuang. Konten yang ditayangkan tetap memakai versi sebelumnya.');
    }

    public function hide(Request $request, string $key)
    {
        $this->content->hide($request->user(), $key, $this->expected($request));
        return redirect()->route('superadmin.website.edit', $key)->with('success', 'Bagian ini sudah disembunyikan dari website.');
    }

    public function preview(Request $request, string $key)
    {
        $input = $request->validate(['page' => ['nullable', Rule::in(['home', 'program'])]]);
        $editor = $this->content->editor($request->user(), $key);
        $site = $this->content->preview($request->user(), $key);
        $program = WebsiteContentDefinition::kind($key) === 'program' ? collect($site['programs'])->firstWhere('id', $editor['master']?->id) : null;
        $showProgram = $program && ($input['page'] ?? 'program') === 'program';
        return view($showProgram ? 'public.program' : 'public.home', ['site' => $site, 'program' => $program,
            'websitePreview' => ['label' => $editor['label'], 'backUrl' => route('superadmin.website.edit', $key),
                'homeUrl' => route('superadmin.website.preview', ['key' => $key, 'page' => 'home']),
                'savedAt' => $editor['draft']?->saved_at?->format('d/m/Y H:i')]]);
    }

    public function remove(Request $request, string $key)
    {
        $this->content->remove($request->user(), $key, $this->expected($request));
        return redirect()->route('superadmin.website.index', ['tab' => WebsiteContentDefinition::tab($key)])
            ->with('success', 'Bagian tambahan dihapus. Riwayat perubahan tetap tersimpan.');
    }

    public function image(Request $request, string $file, PublicWebsiteService $website)
    {
        try {
            $asset = $this->content->previewImage($request->user(), $file);
        } catch (ValidationException $e) {
            abort(404);
        }
        $path = $website->imagePath($asset);
        abort_unless($path, 404);
        return response()->file($path, ['Content-Type' => $asset->mime_type, 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0', 'Content-Security-Policy' => "default-src 'none'; sandbox"]);
    }

    private function expected(Request $request): string
    {
        return $request->validate(['expected_snapshot' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/']])['expected_snapshot'];
    }
}
