<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\FileAsset;
use App\Models\Program;
use App\Models\PublicContentSection;
use App\Models\User;
use App\Models\WebsiteContentChange;
use App\Models\WebsiteContentDraft;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WebsiteContentService
{
    public function authorize(User $actor): void
    {
        abort_unless($actor->role_code === 'superadmin' && $actor->is_active, 403);
    }

    public function rows(User $actor, string $tab): array
    {
        $this->authorize($actor);
        $live = PublicContentSection::all()->keyBy('section_key');
        $drafts = WebsiteContentDraft::all()->keyBy('section_key');
        $masters = $tab === 'program' ? Program::all()->keyBy('id') : ($tab === 'branch' ? Branch::all()->keyBy('id') : collect());
        $keys = array_keys(WebsiteContentDefinition::FIXED);
        $keys = array_merge($keys, $live->keys()->all(), $drafts->keys()->all());
        if ($tab === 'program') {
            $keys = array_merge($keys, $masters->keys()->map(fn ($id) => 'program.'.$id)->all());
        } elseif ($tab === 'branch') {
            $keys = array_merge($keys, $masters->keys()->map(fn ($id) => 'branch.'.$id)->all());
        }
        $rows = [];
        foreach (array_unique($keys) as $key) {
            try {
                if (WebsiteContentDefinition::tab($key) !== $tab) {
                    continue;
                }
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                if ($e->getStatusCode() !== 404) {
                    throw $e;
                }
                continue; // Legacy keys outside the website template are preserved, not remapped.
            }
            $master = in_array($tab, ['program', 'branch'], true) ? $masters->get(substr($key, strlen($tab) + 1)) : null;
            if (in_array($tab, ['program', 'branch'], true) && ! $master) {
                continue;
            }
            $record = $live->get($key);
            $draft = $drafts->get($key);
            $rows[] = ['key' => $key, 'label' => WebsiteContentDefinition::label($key), 'live' => $record, 'draft' => $draft,
                'name' => $master?->name ?? ($draft?->payload['title'] ?? $record?->title ?? WebsiteContentDefinition::label($key)),
                'master_active' => $master?->is_active ?? true, 'order' => $draft?->payload['display_order'] ?? $record?->display_order ?? 0];
        }
        usort($rows, fn ($a, $b) => ($a['order'] <=> $b['order']) ?: strcmp($a['name'], $b['name']));
        return $rows;
    }

    public function editor(User $actor, string $key): array
    {
        $this->authorize($actor);
        $state = $this->state($key);
        $payload = $state['draft']?->payload ?? $this->payload($state['live']) ?? [
            'title' => $state['master']?->name ?? '', 'body' => null, 'image_file_id' => null, 'display_order' => 0, 'is_published' => true,
        ];
        $fields = WebsiteContentDefinition::body($payload['body']);
        foreach (WebsiteContentDefinition::fields($key) as $field => $label) {
            $value = $fields[$field] ?? WebsiteContentDefinition::defaults($key)[$field] ?? '';
            $fields[$field] = $field === 'features' && is_array($value) ? implode("\n", array_filter($value, 'is_string'))
                : (is_string($value) ? $value : '');
        }
        return $state + ['key' => $key, 'tab' => WebsiteContentDefinition::tab($key), 'label' => WebsiteContentDefinition::label($key),
            'payload' => $payload, 'fields' => $fields, 'expectedSnapshot' => $this->signature($actor, $key, $state),
            'conflict' => $state['draft'] && $state['draft']->base_live_hash !== $this->liveHash($state),
            'history' => WebsiteContentChange::where('section_key', $key)->orderByDesc('created_at')->orderByDesc('id')->limit(15)->get()];
    }

    public function save(User $actor, string $key, array $input): WebsiteContentDraft
    {
        $this->authorize($actor);
        $newPath = null;
        try {
            return DB::transaction(function () use ($actor, $key, $input, &$newPath) {
                $state = $this->state($key, true);
                $this->check($actor, $key, $state, $input['expected_snapshot'] ?? '');
                $previous = $state['draft']?->payload;
                $payload = $previous ?? $this->payload($state['live']) ?? [];
                $body = WebsiteContentDefinition::body($payload['body'] ?? null);
                foreach (WebsiteContentDefinition::fields($key) as $field => $label) {
                    if (! array_key_exists($field, $input)) {
                        continue;
                    }
                    $value = trim((string) ($input[$field] ?? ''));
                    $body[$field] = $field === 'features' ? array_values(array_filter(array_map('trim', preg_split('/\R/u', $value)), fn ($s) => $s !== '')) : $value;
                }
                $payload = ['title' => $state['master']?->name ?? trim($input['title']), 'body' => $this->json($body),
                    'image_file_id' => $payload['image_file_id'] ?? null, 'display_order' => (int) $input['display_order'],
                    'is_published' => (bool) $input['is_published']];
                if (! WebsiteContentDefinition::visibilityAllowed($key) && ! $payload['is_published']) {
                    throw ValidationException::withMessages(['is_published' => 'Bagian utama website ditayangkan melalui tombol Tayangkan.']);
                }
                if (strlen($payload['body']) > 60000) {
                    throw ValidationException::withMessages(['description' => 'Isi bagian ini terlalu panjang. Perpendek teks sebelum menyimpan.']);
                }
                if (! empty($input['remove_image'])) {
                    $payload['image_file_id'] = null;
                }
                if (($input['image'] ?? null) instanceof UploadedFile) {
                    $file = $input['image'];
                    $id = (string) Str::uuid();
                    $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$file->getMimeType()] ?? null;
                    if (! $extension || ! WebsiteContentDefinition::imageAllowed($key)) {
                        throw ValidationException::withMessages(['image' => 'Format foto tidak didukung.']);
                    }
                    $newPath = 'website/content/'.$id.'.'.$extension;
                    if (! Storage::disk('local')->putFileAs('website/content', $file, $id.'.'.$extension)) {
                        throw ValidationException::withMessages(['image' => 'Foto belum berhasil disimpan. Coba kembali.']);
                    }
                    $asset = FileAsset::create(['id' => $id, 'storage_key' => $newPath, 'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(), 'sha256_hex' => hash_file('sha256', $file->getRealPath())]);
                    $payload['image_file_id'] = $asset->id;
                }
                if ($payload['image_file_id']) {
                    $this->validateImage($payload['image_file_id'], $key);
                }
                $draft = $state['draft'] ?? new WebsiteContentDraft(['id' => (string) Str::uuid(), 'section_key' => $key]);
                $draft->fill(['payload' => $payload, 'revision' => ($state['draft']?->revision ?? 0) + 1,
                    'base_live_hash' => $this->liveHash($state), 'saved_by_user_id' => $actor->id, 'saved_by_name' => $actor->full_name ?: $actor->username, 'saved_at' => now()]);
                $draft->save();
                $this->audit($actor, $key, 'save_draft', $previous, $payload);
                return $draft->refresh();
            });
        } catch (\Throwable $e) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            if ($e instanceof UniqueConstraintViolationException) {
                $this->stale();
            }
            throw $e;
        }
    }

    public function publish(User $actor, string $key, string $expected): void
    {
        $this->authorize($actor);
        DB::transaction(function () use ($actor, $key, $expected) {
            $state = $this->state($key, true);
            $this->check($actor, $key, $state, $expected);
            $draft = $state['draft'];
            if (! $draft || $draft->base_live_hash !== $this->liveHash($state)) {
                $this->stale();
            }
            $payload = $draft->payload;
            if ($payload['is_published'] && $state['master'] && ! $state['master']->is_active) {
                throw ValidationException::withMessages(['content' => 'Program atau cabang sudah nonaktif. Aktifkan data operasionalnya atau siapkan draft yang disembunyikan.']);
            }
            if ($payload['image_file_id']) {
                $this->validateImage($payload['image_file_id'], $key);
            }
            $before = $this->payload($state['live']);
            $section = $state['live'] ?? new PublicContentSection(['id' => (string) Str::uuid(), 'section_key' => $key]);
            $section->fill($payload)->save();
            $this->audit($actor, $key, $payload['is_published'] ? 'publish' : 'hide', $before, $payload);
            $draft->delete();
        });
    }

    public function discard(User $actor, string $key, string $expected): void
    {
        $this->authorize($actor);
        DB::transaction(function () use ($actor, $key, $expected) {
            $state = $this->state($key, true);
            $this->check($actor, $key, $state, $expected);
            if (! $state['draft']) {
                $this->stale();
            }
            $this->audit($actor, $key, 'discard_draft', $state['draft']->payload, null);
            $state['draft']->delete();
        });
    }

    public function hide(User $actor, string $key, string $expected): void
    {
        $this->authorize($actor);
        abort_unless(WebsiteContentDefinition::visibilityAllowed($key), 404);
        DB::transaction(function () use ($actor, $key, $expected) {
            $state = $this->state($key, true);
            $this->check($actor, $key, $state, $expected);
            if (! $state['live']?->is_published) {
                $this->stale();
            }
            $before = $this->payload($state['live']);
            $state['live']->update(['is_published' => false]);
            $this->audit($actor, $key, 'hide', $before, $this->payload($state['live']));
        });
    }

    public function preview(User $actor, string $key): array
    {
        $this->authorize($actor);
        $state = $this->state($key);
        abort_unless($state['draft'], 404);
        // Exactly one saved draft replaces its live section in this private preview.
        return app(PublicWebsiteService::class)->data([$key => $state['draft']->payload]);
    }

    public function remove(User $actor, string $key, string $expected): void
    {
        $this->authorize($actor);
        abort_unless(isset(WebsiteContentDefinition::REPEATED[WebsiteContentDefinition::kind($key)]), 404);
        DB::transaction(function () use ($actor, $key, $expected) {
            $state = $this->state($key, true);
            $this->check($actor, $key, $state, $expected);
            if (! $state['live'] && ! $state['draft']) {
                $this->stale();
            }
            $this->audit($actor, $key, 'remove_content', ['website' => $this->payload($state['live']), 'draft' => $state['draft']?->payload], null);
            $state['draft']?->delete();
            $state['live']?->delete();
        });
    }

    public function previewImage(User $actor, string $id): FileAsset
    {
        $this->authorize($actor);
        return $this->validateImage($id);
    }

    private function state(string $key, bool $lock = false): array
    {
        WebsiteContentDefinition::kind($key);
        $live = PublicContentSection::where('section_key', $key);
        $draft = WebsiteContentDraft::where('section_key', $key);
        return ['live' => ($lock ? $live->lockForUpdate() : $live)->first(),
            'draft' => ($lock ? $draft->lockForUpdate() : $draft)->first(), 'master' => $this->master($key, $lock)];
    }

    private function master(string $key, bool $lock = false): Program|Branch|null
    {
        $kind = WebsiteContentDefinition::kind($key);
        if (! in_array($kind, ['program', 'branch'], true)) {
            return null;
        }
        $query = $kind === 'program' ? Program::query() : Branch::query();
        return ($lock ? $query->lockForUpdate() : $query)->findOrFail(substr($key, strlen($kind) + 1));
    }

    private function payload(?PublicContentSection $section): ?array
    {
        return $section?->only(['title', 'body', 'image_file_id', 'display_order', 'is_published']);
    }

    private function liveHash(array $state): string
    {
        return hash('sha256', $this->json([$this->payload($state['live']), $this->imageStamp($state['live']?->image_file_id)]));
    }

    private function signature(User $actor, string $key, array $state): string
    {
        return hash_hmac('sha256', $this->json([$actor->id, $key, $this->liveHash($state), $state['draft']?->getRawOriginal(),
            $state['master']?->getRawOriginal(), $this->imageStamp($state['draft']?->payload['image_file_id'] ?? null)]), (string) config('app.key'));
    }

    private function check(User $actor, string $key, array $state, string $expected): void
    {
        if (! hash_equals($this->signature($actor, $key, $state), $expected)) {
            $this->stale();
        }
    }

    private function stale(): never
    {
        throw ValidationException::withMessages(['content' => 'Konten atau draft sudah berubah. Muat ulang halaman, periksa versi terbaru, lalu simpan draft kembali bila diperlukan.']);
    }

    private function imageStamp(?string $id): ?array
    {
        $asset = $id ? FileAsset::find($id) : null;
        if (! $asset) {
            return $id ? [$id, 'missing'] : null;
        }
        $path = app(PublicWebsiteService::class)->imagePath($asset);
        return [$asset->getRawOriginal(), $path ? hash_file('sha256', $path) : 'unavailable'];
    }

    private function validateImage(string $id, ?string $key = null): FileAsset
    {
        $asset = FileAsset::find($id);
        $alreadyPublic = PublicContentSection::where('is_published', true)->where('image_file_id', $id)->exists();
        $belongsHere = $key && PublicContentSection::where('section_key', $key)->where('image_file_id', $id)->where('is_published', true)->exists();
        if (! $asset || (! str_starts_with($asset->storage_key, 'website/') && ! $alreadyPublic && ! $belongsHere)
            || ! app(PublicWebsiteService::class)->imagePath($asset)) {
            throw ValidationException::withMessages(['image' => 'Foto website tidak tersedia atau tidak valid. Unggah foto baru atau hapus foto dari draft.']);
        }
        return $asset;
    }

    private function audit(User $actor, string $key, string $action, ?array $before, ?array $after): void
    {
        WebsiteContentChange::create(['id' => (string) Str::uuid7(), 'section_key' => $key, 'action' => $action,
            'actor_user_id' => $actor->id, 'actor_name' => $actor->full_name ?: $actor->username,
            'before_payload' => $before, 'after_payload' => $after, 'created_at' => now()]);
    }

    private function json(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
