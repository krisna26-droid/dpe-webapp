<?php

namespace Tests\Feature;

use App\Http\Requests\NotificationStoreRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationStoreRequestTest extends TestCase
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

    private function validData(User $user): array
    {
        return [
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'notification_type' => 'system',
            'title' => 'System Notification',
            'body' => 'Notification body.',
            'action_path' => '/dashboard',
        ];
    }

    private function validator(array $data)
    {
        $request = new NotificationStoreRequest();

        return Validator::make(
            $data,
            $request->rules()
        );
    }

    public function test_valid_data_passes_validation(): void
    {
        $user = $this->makeUser();

        $validator = $this->validator(
            $this->validData($user)
        );

        $this->assertFalse($validator->fails());
    }

    public function test_id_is_required(): void
    {
        $user = $this->makeUser();

        $data = $this->validData($user);
        unset($data['id']);

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('id', $validator->errors()->toArray());
    }

    public function test_id_must_be_uuid(): void
    {
        $user = $this->makeUser();

        $data = $this->validData($user);
        $data['id'] = 'invalid-id';

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('id', $validator->errors()->toArray());
    }

    public function test_id_must_be_unique(): void
    {
        $user = $this->makeUser();

        $existingId = (string) Str::uuid();

        \App\Models\Notification::query()->create([
            'id' => $existingId,
            'user_id' => $user->id,
            'notification_type' => 'system',
            'title' => 'Existing',
            'body' => 'Existing notification.',
            'action_path' => null,
        ]);

        $data = $this->validData($user);
        $data['id'] = $existingId;

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('id', $validator->errors()->toArray());
    }

    public function test_user_id_is_required(): void
    {
        $user = $this->makeUser();

        $data = $this->validData($user);
        unset($data['user_id']);

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'user_id',
            $validator->errors()->toArray()
        );
    }

    public function test_user_id_must_exist(): void
    {
        $data = $this->validData(
            $this->makeUser()
        );

        $data['user_id'] = (string) Str::uuid();

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'user_id',
            $validator->errors()->toArray()
        );
    }

    public function test_notification_type_is_required(): void
    {
        $user = $this->makeUser();

        $data = $this->validData($user);
        unset($data['notification_type']);

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'notification_type',
            $validator->errors()->toArray()
        );
    }

    public function test_title_is_required(): void
    {
        $user = $this->makeUser();

        $data = $this->validData($user);
        unset($data['title']);

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'title',
            $validator->errors()->toArray()
        );
    }

    public function test_body_is_required(): void
    {
        $user = $this->makeUser();

        $data = $this->validData($user);
        unset($data['body']);

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'body',
            $validator->errors()->toArray()
        );
    }

    public function test_action_path_is_nullable(): void
    {
        $user = $this->makeUser();

        $data = $this->validData($user);
        $data['action_path'] = null;

        $validator = $this->validator($data);

        $this->assertFalse($validator->fails());
    }

    public function test_notification_type_must_be_string(): void
    {
        $user = $this->makeUser();

        $data = $this->validData($user);
        $data['notification_type'] = ['system'];

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'notification_type',
            $validator->errors()->toArray()
        );
    }

    public function test_title_must_be_string(): void
    {
        $user = $this->makeUser();

        $data = $this->validData($user);
        $data['title'] = ['title'];

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'title',
            $validator->errors()->toArray()
        );
    }

    public function test_body_must_be_string(): void
    {
        $user = $this->makeUser();

        $data = $this->validData($user);
        $data['body'] = ['body'];

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'body',
            $validator->errors()->toArray()
        );
    }

    public function test_action_path_must_be_string_when_provided(): void
    {
        $user = $this->makeUser();

        $data = $this->validData($user);
        $data['action_path'] = ['dashboard'];

        $validator = $this->validator($data);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey(
            'action_path',
            $validator->errors()->toArray()
        );
    }
}
