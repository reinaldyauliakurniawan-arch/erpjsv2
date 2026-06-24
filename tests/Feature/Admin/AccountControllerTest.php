<?php

namespace Tests\Feature\Admin;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $cfo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cfo = User::factory()->cfo()->create();
        $this->cfo->role = 'cfo';
        $this->cfo->save();
    }

    #[Test]
    public function guest_is_redirected_to_login(): void
    {
        $this->get(route('finance.accounts.index'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function cfo_can_view_account_list(): void
    {
        $this->actingAs($this->cfo)
            ->get(route('finance.accounts.index'))
            ->assertOk()
            ->assertViewIs('admin.accounts.index');
    }

    #[Test]
    public function cfo_can_create_account(): void
    {
        $this->actingAs($this->cfo)
            ->post(route('finance.accounts.store'), [
                'code' => '9999',
                'name' => 'Test Account',
                'type' => 'Asset',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('accounts', [
            'code' => '9999',
            'name' => 'Test Account',
        ]);
    }

    #[Test]
    public function store_validates_required_fields(): void
    {
        $this->actingAs($this->cfo)
            ->post(route('finance.accounts.store'), [])
            ->assertSessionHasErrors(['code', 'name', 'type']);
    }

    #[Test]
    public function cfo_can_update_account(): void
    {
        $account = Account::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->cfo)
            ->put(route('finance.accounts.update', $account), [
                'code' => $account->code,
                'name' => 'New Name',
                'type' => $account->type,
            ])
            ->assertRedirect();

        $account->refresh();
        $this->assertEquals('New Name', $account->name);
    }

    #[Test]
    public function non_cfo_is_forbidden(): void
    {
        $tutor = User::factory()->tutor()->create();
        $tutor->role = 'tutor';
        $tutor->save();

        $this->actingAs($tutor)
            ->get(route('finance.accounts.index'))
            ->assertForbidden();
    }
}
