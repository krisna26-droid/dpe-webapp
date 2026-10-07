<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function create(array $data): Notification
    {
        return DB::transaction(function () use ($data) {
            return Notification::query()->create([
                'id' => $data['id'],
                'user_id' => $data['user_id'],
                'notification_type' => $data['notification_type'],
                'title' => $data['title'],
                'body' => $data['body'],
                'action_path' => $data['action_path'] ?? null,
            ]);
        });
    }

    public function find(string $id): Notification
    {
        return Notification::query()
            ->findOrFail($id);
    }

    public function getByUser(User $user)
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();
    }

    public function update(string $id, array $data): Notification
    {
        return DB::transaction(function () use ($id, $data) {
            $notification = $this->find($id);

            $notification->update([
                'notification_type' => $data['notification_type'],
                'title' => $data['title'],
                'body' => $data['body'],
                'action_path' => $data['action_path'] ?? null,
            ]);

            return $notification->refresh();
        });
    }

    public function delete(string $id): void
    {
        DB::transaction(function () use ($id) {
            $notification = $this->find($id);

            $notification->delete();
        });
    }
}
