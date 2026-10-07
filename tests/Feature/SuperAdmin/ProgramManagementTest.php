<?php

namespace Tests\Feature\SuperAdmin;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Branch;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProgramManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperadmin(
        bool $active = true
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'superadmin-' . Str::random(8),
            'email' => 'superadmin-' . Str::random(8) . '@test.local',
            'password_hash' => bcrypt('password'),
            'full_name' => 'SuperAdmin Test',
            'role_code' => 'superadmin',
            'is_active' => $active,
        ]);
    }

    private function createAdmin(
        bool $active = true
    ): User {
        return User::query()->create([
            'id' => (string) Str::uuid(),
            'username' => 'admin-' . Str::random(8),
            'email' => 'admin-' . Str::random(8) . '@test.local',
            'password_hash' => bcrypt('password'),
            'full_name' => 'Admin Test',
            'role_code' => 'admin',
            'is_active' => $active,
        ]);
    }

    private function createProgram(
        bool $active = true
    ): Program {
        return Program::query()->create([
            'id' => (string) Str::uuid(),
            'code' => 'PROG-' . Str::upper(Str::random(6)),
            'name' => 'English Program',
            'class_type' => 'Regular',
            'monthly_video_target_override' => 4,
            'is_active' => $active,
        ]);
    }

    public function test_superadmin_can_open_program_index(): void
    {
        $superadmin = $this->createSuperadmin();

        $this->actingAs($superadmin)
            ->get(route('superadmin.programs.index'))
            ->assertOk()
            ->assertViewIs('superadmin.programs.index');
    }

    public function test_non_superadmin_cannot_open_program_management(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(route('superadmin.programs.index'))
            ->assertForbidden();
    }

    public function test_inactive_superadmin_cannot_access_program_management(): void
    {
        $superadmin = $this->createSuperadmin(false);

        $this->actingAs($superadmin)
            ->get(route('superadmin.programs.index'))
            ->assertForbidden();
    }

    public function test_superadmin_can_open_program_create_page(): void
    {
        $superadmin = $this->createSuperadmin();

        $this->actingAs($superadmin)
            ->get(route('superadmin.programs.create'))
            ->assertOk()
            ->assertViewIs('superadmin.programs.create');
    }

    public function test_superadmin_can_create_program(): void
    {
        $superadmin = $this->createSuperadmin();

        $response = $this->actingAs($superadmin)
            ->post(route('superadmin.programs.store'), [
                'code' => 'ENG-REG',
                'name' => 'English Regular',
                'class_type' => 'Regular',
                'monthly_video_target_override' => 4,
                'is_active' => true,
            ]);

        $program = Program::query()
            ->where('code', 'ENG-REG')
            ->first();

        $this->assertNotNull($program);

        $response
            ->assertRedirect(
                route('superadmin.programs.show', $program)
            )
            ->assertSessionHas(
                'success',
                'Program berhasil dibuat.'
            );

        $this->assertDatabaseHas('programs', [
            'id' => $program->id,
            'code' => 'ENG-REG',
            'name' => 'English Regular',
            'class_type' => 'Regular',
            'monthly_video_target_override' => 4,
            'is_active' => 1,
        ]);
    }

    public function test_program_is_active_by_default(): void
    {
        $superadmin = $this->createSuperadmin();

        $this->actingAs($superadmin)
            ->post(route('superadmin.programs.store'), [
                'code' => 'ENG-DEFAULT',
                'name' => 'English Default',
                'class_type' => 'Regular',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('programs', [
            'code' => 'ENG-DEFAULT',
            'is_active' => 1,
        ]);
    }

    public function test_duplicate_program_code_is_rejected(): void
    {
        $superadmin = $this->createSuperadmin();

        $this->createProgram();

        $this->actingAs($superadmin)
            ->post(route('superadmin.programs.store'), [
                'code' => Program::query()->first()->code,
                'name' => 'Another Program',
                'class_type' => 'Intensive',
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_required_program_fields_are_validated(): void
    {
        $superadmin = $this->createSuperadmin();

        $this->actingAs($superadmin)
            ->post(route('superadmin.programs.store'), [])
            ->assertSessionHasErrors([
                'code',
                'name',
                'class_type',
            ]);
    }

    public function test_monthly_video_target_must_be_non_negative_integer(): void
    {
        $superadmin = $this->createSuperadmin();

        $this->actingAs($superadmin)
            ->post(route('superadmin.programs.store'), [
                'code' => 'ENG-INVALID',
                'name' => 'Invalid Program',
                'class_type' => 'Regular',
                'monthly_video_target_override' => -1,
            ])
            ->assertSessionHasErrors(
                'monthly_video_target_override'
            );
    }

    public function test_superadmin_can_open_program_show_page(): void
    {
        $superadmin = $this->createSuperadmin();
        $program = $this->createProgram();

        $this->actingAs($superadmin)
            ->get(
                route(
                    'superadmin.programs.show',
                    $program
                )
            )
            ->assertOk()
            ->assertViewIs('superadmin.programs.show');
    }

    public function test_superadmin_can_open_program_edit_page(): void
    {
        $superadmin = $this->createSuperadmin();
        $program = $this->createProgram();

        $this->actingAs($superadmin)
            ->get(
                route(
                    'superadmin.programs.edit',
                    $program
                )
            )
            ->assertOk()
            ->assertViewIs('superadmin.programs.edit');
    }

    public function test_superadmin_can_update_program(): void
    {
        $superadmin = $this->createSuperadmin();
        $program = $this->createProgram();

        $response = $this->actingAs($superadmin)
            ->put(
                route(
                    'superadmin.programs.update',
                    $program
                ),
                [
                    'code' => 'ENG-UPDATED',
                    'name' => 'English Updated',
                    'class_type' => 'Intensive',
                    'monthly_video_target_override' => 8,
                    'is_active' => true,
                ]
            );

        $response
            ->assertRedirect(
                route(
                    'superadmin.programs.show',
                    $program
                )
            )
            ->assertSessionHas(
                'success',
                'Program berhasil diperbarui.'
            );

        $this->assertDatabaseHas('programs', [
            'id' => $program->id,
            'code' => 'ENG-UPDATED',
            'name' => 'English Updated',
            'class_type' => 'Intensive',
            'monthly_video_target_override' => 8,
            'is_active' => 1,
        ]);
    }

    public function test_duplicate_program_code_is_rejected_on_update(): void
    {
        $superadmin = $this->createSuperadmin();

        $programA = $this->createProgram();
        $programB = $this->createProgram();

        $this->actingAs($superadmin)
            ->put(
                route(
                    'superadmin.programs.update',
                    $programB
                ),
                [
                    'code' => $programA->code,
                    'name' => 'Updated Program',
                    'class_type' => 'Regular',
                    'monthly_video_target_override' => 4,
                    'is_active' => true,
                ]
            )
            ->assertSessionHasErrors('code');
    }

    public function test_program_can_be_deactivated(): void
    {
        $superadmin = $this->createSuperadmin();
        $program = $this->createProgram(true);

        $this->actingAs($superadmin)
            ->patch(
                route(
                    'superadmin.programs.status',
                    $program
                )
            )
            ->assertRedirect(
                route(
                    'superadmin.programs.show',
                    $program
                )
            )
            ->assertSessionHas(
                'success',
                'Program berhasil dinonaktifkan.'
            );

        $this->assertDatabaseHas('programs', [
            'id' => $program->id,
            'is_active' => 0,
        ]);
    }

    public function test_program_can_be_reactivated(): void
    {
        $superadmin = $this->createSuperadmin();
        $program = $this->createProgram(false);

        $this->actingAs($superadmin)
            ->patch(
                route(
                    'superadmin.programs.status',
                    $program
                )
            )
            ->assertRedirect(
                route(
                    'superadmin.programs.show',
                    $program
                )
            )
            ->assertSessionHas(
                'success',
                'Program berhasil diaktifkan.'
            );

        $this->assertDatabaseHas('programs', [
            'id' => $program->id,
            'is_active' => 1,
        ]);
    }

    public function test_non_superadmin_cannot_toggle_program_status(): void
    {
        $admin = $this->createAdmin();
        $program = $this->createProgram(true);

        $this->actingAs($admin)
            ->patch(
                route(
                    'superadmin.programs.status',
                    $program
                )
            )
            ->assertForbidden();

        $this->assertDatabaseHas('programs', [
            'id' => $program->id,
            'is_active' => 1,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_program_management(): void
    {
        $this->get(
            route('superadmin.programs.index')
        )->assertRedirect(
            route('login')
        );
    }
}