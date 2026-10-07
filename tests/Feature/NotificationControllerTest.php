<?php

namespace Tests\Feature;

use App\Http\Controllers\NotificationController;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private NotificationController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new NotificationController(
            new NotificationService()
        );
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
        return Notification::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'notification_type' => 'system',
            'title' => 'Test Notification',
            'body' => 'Test notification body.',
            'action_path' => '/dashboard',
        ]);
    }

    public function test_store_returns_created_notification(): void
    {
        $user = $this->makeUser();

        $request = \Illuminate\Http\Request::create(
            '/notifications',
            'POST',
            [
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'notification_type' => 'payment',
                'title' => 'Payment Received',
                'body' => 'Payment has been received.',
                'action_path' => '/payments',
            ]
        );

        $request->setUserResolver(
            fn() => $user
        );

        $formRequest = \App\Http\Requests\NotificationStoreRequest::createFrom(
            $request
        );

        $formRequest->setContainer(app());
        $formRequest->setRedirector(app('redirect'));
        $formRequest->setUserResolver(fn() => $user);

        $formRequest->validateResolved();

        $response = $this->controller->store($formRequest);

        $this->assertSame(201, $response->getStatusCode());

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'notification_type' => 'payment',
            'title' => 'Payment Received',
        ]);
    }

    public function test_show_returns_notification(): void
    {
        $user = $this->makeUser();
        $notification = $this->makeNotification($user);

        $response = $this->controller->show(
            $notification->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertSame(
            $notification->id,
            $response->getData()->id
        );
    }

    public function test_by_user_returns_user_notifications(): void
    {
        $user = $this->makeUser();

        $this->makeNotification($user);
        $this->makeNotification($user);

        $response = $this->controller->byUser(
            $user->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertCount(
            2,
            $response->getData()
        );
    }

    public function test_update_returns_updated_notification(): void
    {
        $user = $this->makeUser();
        $notification = $this->makeNotification($user);

        $request = \Illuminate\Http\Request::create(
            '/notifications/' . $notification->id,
            'PUT',
            [
                'id' => $notification->id,
                'user_id' => $user->id,
                'notification_type' => 'updated',
                'title' => 'Updated Notification',
                'body' => 'Updated notification body.',
                'action_path' => '/updated',
            ]
        );

        $request->setUserResolver(
            fn() => $user
        );

        $formRequest = \App\Http\Requests\NotificationStoreRequest::createFrom(
            $request
        );

        $formRequest->setContainer(app());
        $formRequest->setRedirector(app('redirect'));
        $formRequest->setUserResolver(fn() => $user);

        $formRequest->validateResolved();

        $response = $this->controller->update(
            $formRequest,
            $notification->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertSame(
            'Updated Notification',
            $response->getData()->title
        );
    }

    public function test_destroy_deletes_notification(): void
    {
        $user = $this->makeUser();
        $notification = $this->makeNotification($user);

        $response = $this->controller->destroy(
            $notification->id
        );

        $this->assertSame(
            200,
            $response->getStatusCode()
        );

        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
        ]);
    }

    public function test_show_throws_exception_for_missing_notification(): void
    {
        $this->expectException(
            \Illuminate\Database\Eloquent\ModelNotFoundException::class
        );

        $this->controller->show(
            (string) Str::uuid()
        );
    }
}
