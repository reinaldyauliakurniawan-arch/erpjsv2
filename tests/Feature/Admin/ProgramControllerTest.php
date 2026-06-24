<?php

namespace Tests\Feature\Admin;

use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProgramControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->admin->role = 'admin';
        $this->admin->save();
    }

    #[Test]
    public function guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.programs.index'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function admin_can_view_program_list(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.programs.index'))
            ->assertOk()
            ->assertViewIs('admin.programs.index');
    }

    #[Test]
    public function admin_can_create_program(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.programs.store'), [
                'name' => 'Test Program',
                'type' => 'private',
                'price' => 500000,
                'total_meetings' => 8,
                'min_quota' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('programs', [
            'name' => 'Test Program',
            'type' => 'private',
        ]);
    }

    #[Test]
    public function store_validates_required_fields(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.programs.store'), [])
            ->assertSessionHasErrors(['name', 'type', 'price', 'total_meetings']);
    }

    #[Test]
    public function admin_can_update_program(): void
    {
        $program = Program::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->admin)
            ->put(route('admin.programs.update', $program), [
                'name' => 'New Name',
                'type' => 'group',
                'price' => 750000,
                'total_meetings' => 10,
                'min_quota' => 3,
            ])
            ->assertRedirect();

        $program->refresh();
        $this->assertEquals('New Name', $program->name);
        $this->assertEquals('group', $program->type);
    }

    #[Test]
    public function admin_can_delete_program_without_enrollments(): void
    {
        $program = Program::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.programs.destroy', $program))
            ->assertRedirect();

        $this->assertDatabaseMissing('programs', ['id' => $program->id]);
    }

    #[Test]
    public function non_admin_is_forbidden(): void
    {
        $student = User::factory()->student()->create();
        $student->role = 'student';
        $student->save();

        $this->actingAs($student)
            ->get(route('admin.programs.index'))
            ->assertForbidden();
    }
}
