<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationHttpTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(
        string $roleCode = 'superadmin',
        bool $isActive = true
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => $roleCode . '_' . Str::lower(Str::random(8)),
            'email' => Str::uuid() . '@example.com',
            'password_hash' => Hash::make('password'),
            'full_name' => 'Test ' . ucfirst($roleCode),
            'role_code' => $roleCode,
            'is_active' => $isActive,
        ]);
    }

    private function makeNotification(User $user): Notification
    {
        return Notification::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'notification_type' => 'system',
            'title' => 'Test Notification',
            'body' => 'Test notification body.',
            'action_path' => '/dashboard',
        ]);
    }

    public function test_guest_cannot_access_notification(): void
    {
        $user = $this->makeUser();
        $notification = $this->makeNotification($user);

        $response = $this->get(
            route('superadmin.notifications.show', $notification->id)
        );

        $response->assertRedirect();
    }

    public function test_non_superadmin_cannot_access_notification(): void
    {
        $user = $this->makeUser('teacher');
        $notification = $this->makeNotification($user);

        $response = $this
            ->actingAs($user)
            ->get(
                route(
                    'superadmin.notifications.show',
                    $notification->id
                )
            );

        $response->assertForbidden();
    }

    public function test_superadmin_can_store_notification(): void
    {
        $superadmin = $this->makeUser();
        $targetUser = $this->makeUser('teacher');

        $notificationId = (string) Str::uuid();

        $response = $this
            ->actingAs($superadmin)
            ->post(
                route('superadmin.notifications.store'),
                [
                    'id' => $notificationId,
                    'user_id' => $targetUser->id,
                    'notification_type' => 'system',
                    'title' => 'New Notification',
                    'body' => 'Notification body.',
                    'action_path' => '/dashboard',
                ]
            );

        $response
            ->assertStatus(201)
            ->assertJson([
                'id' => $notificationId,
                'user_id' => $targetUser->id,
                'notification_type' => 'system',
                'title' => 'New Notification',
            ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'user_id' => $targetUser->id,
        ]);
    }

    public function test_superadmin_can_show_notification(): void
    {
        $superadmin = $this->makeUser();
        $targetUser = $this->makeUser('teacher');
        $notification = $this->makeNotification($targetUser);

        $response = $this
            ->actingAs($superadmin)
            ->get(
                route(
                    'superadmin.notifications.show',
                    $notification->id
                )
            );

        $response
            ->assertOk()
            ->assertJson([
                'id' => $notification->id,
                'user_id' => $targetUser->id,
                'title' => 'Test Notification',
            ]);
    }

    public function test_superadmin_can_get_notifications_by_user(): void
    {
        $superadmin = $this->makeUser();
        $targetUser = $this->makeUser('teacher');

        $first = $this->makeNotification($targetUser);
        $second = $this->makeNotification($targetUser);

        $otherUser = $this->makeUser('teacher');
        $this->makeNotification($otherUser);

        $response = $this
            ->actingAs($superadmin)
            ->get(
                route(
                    'superadmin.users.notifications',
                    $targetUser->id
                )
            );

        $response
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment([
                'id' => $first->id,
            ])
            ->assertJsonFragment([
                'id' => $second->id,
            ]);
    }

    public function test_superadmin_can_update_notification(): void
    {
        $superadmin = $this->makeUser();
        $targetUser = $this->makeUser('teacher');
        $notification = $this->makeNotification($targetUser);

        $response = $this
            ->actingAs($superadmin)
            ->put(
                route(
                    'superadmin.notifications.update',
                    $notification->id
                ),
                [
                    'id' => $notification->id,
                    'user_id' => $targetUser->id,
                    'notification_type' => 'updated',
                    'title' => 'Updated Notification',
                    'body' => 'Updated notification body.',
                    'action_path' => '/updated',
                ]
            );

        $response
            ->assertOk()
            ->assertJson([
                'id' => $notification->id,
                'notification_type' => 'updated',
                'title' => 'Updated Notification',
                'body' => 'Updated notification body.',
                'action_path' => '/updated',
            ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'title' => 'Updated Notification',
        ]);
    }

    public function test_superadmin_can_delete_notification(): void
    {
        $superadmin = $this->makeUser();
        $targetUser = $this->makeUser('teacher');
        $notification = $this->makeNotification($targetUser);

        $response = $this
            ->actingAs($superadmin)
            ->delete(
                route(
                    'superadmin.notifications.destroy',
                    $notification->id
                )
            );

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Notification deleted successfully.',
            ]);

        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
        ]);
    }
}
