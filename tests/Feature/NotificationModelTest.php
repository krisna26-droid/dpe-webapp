<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'notification_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'full_name' => 'Notification Test User',
            'role_code' => 'superadmin',
            'is_active' => true,
        ]);
    }

    public function test_notification_uses_notifications_table(): void
    {
        $notification = new Notification();

        $this->assertSame(
            'notifications',
            $notification->getTable()
        );
    }

    public function test_notification_uses_string_non_incrementing_primary_key(): void
    {
        $notification = new Notification();

        $this->assertSame('id', $notification->getKeyName());
        $this->assertFalse($notification->getIncrementing());
        $this->assertSame('string', $notification->getKeyType());
    }

    public function test_notification_does_not_use_timestamps(): void
    {
        $notification = new Notification();

        $this->assertFalse($notification->usesTimestamps());
    }

    public function test_notification_can_be_created_with_uuid(): void
    {
        $user = $this->makeUser();

        $notification = Notification::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'notification_type' => 'payment',
            'title' => 'Payment notification',
            'body' => 'Payment has been received.',
            'action_path' => '/payments',
        ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'user_id' => $user->id,
            'notification_type' => 'payment',
            'title' => 'Payment notification',
            'body' => 'Payment has been received.',
            'action_path' => '/payments',
        ]);
    }

    public function test_action_path_can_be_null(): void
    {
        $user = $this->makeUser();

        $notification = Notification::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'notification_type' => 'system',
            'title' => 'System notification',
            'body' => 'This is a system notification.',
            'action_path' => null,
        ]);

        $this->assertNull($notification->action_path);
    }

    public function test_notification_belongs_to_user(): void
    {
        $user = $this->makeUser();

        $notification = Notification::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'notification_type' => 'system',
            'title' => 'System notification',
            'body' => 'Notification body.',
            'action_path' => null,
        ]);

        $this->assertTrue(
            $notification->relationLoaded('user') === false
        );

        $this->assertSame(
            $user->id,
            $notification->user->id
        );
    }

    public function test_user_has_many_notifications(): void
    {
        $user = $this->makeUser();

        Notification::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'notification_type' => 'system',
            'title' => 'Notification 1',
            'body' => 'Body 1',
            'action_path' => null,
        ]);

        Notification::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'notification_type' => 'system',
            'title' => 'Notification 2',
            'body' => 'Body 2',
            'action_path' => '/dashboard',
        ]);

        $this->assertCount(
            2,
            $user->notifications
        );
    }

    public function test_deleting_user_cascades_notifications(): void
    {
        $user = $this->makeUser();

        $notificationId = (string) Str::uuid();

        Notification::query()->create([
            'id' => $notificationId,
            'user_id' => $user->id,
            'notification_type' => 'system',
            'title' => 'Cascade test',
            'body' => 'This notification should be deleted.',
            'action_path' => null,
        ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
        ]);

        $user->delete();

        $this->assertDatabaseMissing('notifications', [
            'id' => $notificationId,
        ]);
    }

    public function test_created_at_is_cast_to_datetime(): void
    {
        $user = $this->makeUser();

        $notification = Notification::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'notification_type' => 'system',
            'title' => 'Created at test',
            'body' => 'Testing created_at cast.',
            'action_path' => null,
        ]);

        $notification->refresh();

        $this->assertInstanceOf(
            \Illuminate\Support\Carbon::class,
            $notification->created_at
        );
    }
}
