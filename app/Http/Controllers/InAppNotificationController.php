<?php

namespace App\Http\Controllers;

use App\Services\InAppNotificationService;
use App\Services\OperationsAccessService;
use App\Models\TeacherAttendance;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class InAppNotificationController extends Controller
{
    public function __construct(private InAppNotificationService $service) {}

    public function index(Request $request)
    {
        $filters = $request->validate(['type' => ['nullable', Rule::in(array_keys(InAppNotificationService::TYPES))],
            'read' => ['nullable', 'in:all,unread,read'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $filters = array_replace(['type' => 'all', 'read' => 'all', 'page' => 1], array_filter($filters, fn ($value) => $value !== null));
        $all = $this->service->items($request->user());
        $request->attributes->set('dpe.inbox.items', $all);
        $items = $all->filter(fn ($item) => ($filters['type'] === 'all' || $item['type'] === $filters['type'])
            && ($filters['read'] === 'all' || $item['read'] === ($filters['read'] === 'read')))->values();
        $notifications = new LengthAwarePaginator($items->forPage($filters['page'], 15)->values(), $items->count(), 15, $filters['page'],
            ['path' => route('inbox.index'), 'query' => $request->only(['type', 'read'])]);

        return view('notifications.index', compact('notifications', 'filters') + ['types' => InAppNotificationService::TYPES,
            'unread' => $all->where('read', false)->count(), 'total' => $all->count()]);
    }

    public function read(Request $request, string $key)
    {
        $this->service->read($request->user(), [$key]);

        return redirect()->route('inbox.index')->with('success', 'Notifikasi ditandai sudah dibaca.');
    }

    public function readPage(Request $request)
    {
        $data = $request->validate(['keys' => ['required', 'array', 'min:1', 'max:15'], 'keys.*' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/', 'distinct']]);
        $this->service->read($request->user(), $data['keys']);

        return redirect()->route('inbox.index')->with('success', 'Notifikasi pada halaman yang Anda tinjau ditandai sudah dibaca.');
    }

    public function open(Request $request, string $key)
    {
        $item = $this->service->read($request->user(), [$key], true)->first();

        return redirect($item['path']);
    }

    public function attendance(Request $request, TeacherAttendance $teacherAttendance, OperationsAccessService $access)
    {
        $actor = $access->actor($request->user());
        abort_unless($actor->role_code === 'teacher' && $access->attendances($actor)->whereKey($teacherAttendance->id)->exists(), 404);
        $attendance = $teacherAttendance->fresh(['branch', 'teacher.user']);

        return view('notifications.attendance', compact('attendance'));
    }
}
