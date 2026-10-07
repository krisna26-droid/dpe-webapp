<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new NotificationService();
    }

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

    private function makeNotification(User $user): Notification
    {
        return $this->service->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'notification_type' => 'system',
            'title' => 'Test Notification',
            'body' => 'Test notification body.',
            'action_path' => '/dashboard',
        ]);
    }

    public function test_create_creates_notification(): void
    {
        $user = $this->makeUser();

        $id = (string) Str::uuid();

        $notification = $this->service->create([
            'id' => $id,
            'user_id' => $user->id,
            'notification_type' => 'payment',
            'title' => 'Payment Received',
            'body' => 'Payment has been received.',
            'action_path' => '/payments',
        ]);

        $this->assertSame($id, $notification->id);
        $this->assertSame($user->id, $notification->user_id);
        $this->assertSame('payment', $notification->notification_type);
        $this->assertSame('Payment Received', $notification->title);
        $this->assertSame(
            'Payment has been received.',
            $notification->body
        );
        $this->assertSame('/payments', $notification->action_path);

        $this->assertDatabaseHas('notifications', [
            'id' => $id,
            'user_id' => $user->id,
            'notification_type' => 'payment',
        ]);
    }

    public function test_create_allows_null_action_path(): void
    {
        $user = $this->makeUser();

        $notification = $this->service->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'notification_type' => 'system',
            'title' => 'System Notification',
            'body' => 'System notification body.',
            'action_path' => null,
        ]);

        $this->assertNull($notification->action_path);
    }

    public function test_find_returns_notification(): void
    {
        $user = $this->makeUser();
        $notification = $this->makeNotification($user);

        $result = $this->service->find($notification->id);

        $this->assertTrue($result->is($notification));
    }

    public function test_find_throws_exception_when_notification_does_not_exist(): void
    {
        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->find((string) Str::uuid());
    }

    public function test_get_by_user_returns_only_notifications_belonging_to_user(): void
    {
        $user = $this->makeUser();
        $otherUser = $this->makeUser();

        $first = $this->makeNotification($user);
        $second = $this->makeNotification($user);

        $this->makeNotification($otherUser);

        $notifications = $this->service->getByUser($user);

        $this->assertCount(2, $notifications);
        $this->assertTrue(
            $notifications->contains('id', $first->id)
        );
        $this->assertTrue(
            $notifications->contains('id', $second->id)
        );
    }

    public function test_get_by_user_orders_notifications_by_latest_created_at(): void
    {
        $user = $this->makeUser();

        $older = $this->makeNotification($user);

        $older->created_at = now()->subMinutes(10);
        $older->save();

        $newer = $this->makeNotification($user);

        $newer->created_at = now();
        $newer->save();

        $notifications = $this->service->getByUser($user);

        $this->assertSame(
            $newer->id,
            $notifications->first()->id
        );

        $this->assertSame(
            $older->id,
            $notifications->last()->id
        );
    }

    public function test_update_updates_notification(): void
    {
        $user = $this->makeUser();
        $notification = $this->makeNotification($user);

        $result = $this->service->update(
            $notification->id,
            [
                'notification_type' => 'payment_updated',
                'title' => 'Updated Payment',
                'body' => 'Payment notification has been updated.',
                'action_path' => '/payment-history',
            ]
        );

        $this->assertSame(
            'payment_updated',
            $result->notification_type
        );

        $this->assertSame(
            'Updated Payment',
            $result->title
        );

        $this->assertSame(
            'Payment notification has been updated.',
            $result->body
        );

        $this->assertSame(
            '/payment-history',
            $result->action_path
        );

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'notification_type' => 'payment_updated',
            'title' => 'Updated Payment',
            'body' => 'Payment notification has been updated.',
            'action_path' => '/payment-history',
        ]);
    }

    public function test_update_can_set_action_path_to_null(): void
    {
        $user = $this->makeUser();
        $notification = $this->makeNotification($user);

        $result = $this->service->update(
            $notification->id,
            [
                'notification_type' => 'system',
                'title' => 'Updated System Notification',
                'body' => 'Updated body.',
                'action_path' => null,
            ]
        );

        $this->assertNull($result->action_path);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'action_path' => null,
        ]);
    }

    public function test_delete_deletes_notification(): void
    {
        $user = $this->makeUser();
        $notification = $this->makeNotification($user);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
        ]);

        $this->service->delete($notification->id);

        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
        ]);
    }

    public function test_delete_throws_exception_when_notification_does_not_exist(): void
    {
        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->service->delete((string) Str::uuid());
    }
}
