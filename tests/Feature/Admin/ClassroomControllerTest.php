<?php

namespace Tests\Feature\Admin;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClassroomControllerTest extends TestCase
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
        $this->get(route('admin.classrooms.index'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function admin_can_view_classroom_list(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.classrooms.index'))
            ->assertOk()
            ->assertViewIs('admin.classrooms.index');
    }

    #[Test]
    public function admin_can_create_classroom(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.classrooms.store'), [
                'name' => 'Studio Test',
                'capacity' => 10,
                'is_at_just_speak' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('classrooms', [
            'name' => 'Studio Test',
            'capacity' => 10,
        ]);
    }

    #[Test]
    public function store_validates_required_fields(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.classrooms.store'), [])
            ->assertSessionHasErrors(['name', 'capacity']);
    }

    #[Test]
    public function admin_can_update_classroom(): void
    {
        $classroom = Classroom::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->admin)
            ->put(route('admin.classrooms.update', $classroom), [
                'name' => 'New Name',
                'capacity' => 20,
                'is_at_just_speak' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('classrooms', [
            'id' => $classroom->id,
            'name' => 'New Name',
            'capacity' => 20,
        ]);
    }

    #[Test]
    public function admin_can_delete_classroom_without_schedules(): void
    {
        $classroom = Classroom::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.classrooms.destroy', $classroom))
            ->assertRedirect();

        $this->assertDatabaseMissing('classrooms', ['id' => $classroom->id]);
    }

    #[Test]
    public function non_admin_is_forbidden(): void
    {
        $tutor = User::factory()->tutor()->create();
        $tutor->role = 'tutor';
        $tutor->save();

        $this->actingAs($tutor)
            ->get(route('admin.classrooms.index'))
            ->assertForbidden();
    }
}
