<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
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
        $this->get(route('admin.settings.index'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function admin_can_view_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertViewIs('admin.settings.index');
    }

    #[Test]
    public function admin_can_view_color_settings(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings.colors'))
            ->assertOk()
            ->assertViewIs('admin.settings.colors');
    }

    #[Test]
    public function non_admin_is_forbidden(): void
    {
        $tutor = User::factory()->tutor()->create();
        $tutor->role = 'tutor';
        $tutor->save();

        $this->actingAs($tutor)
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }
}
