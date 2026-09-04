<?php

namespace Tests\Feature\Console;

use App\Models\Journal;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SetTutorPermanentTest extends TestCase
{
    use RefreshDatabase;

    private function tutor(string $name): Tutor
    {
        return Tutor::factory()->forUser(User::factory()->create(['name' => $name]))->create();
    }

    #[Test]
    public function it_marks_a_tutor_as_permanent(): void
    {
        $tutor = $this->tutor('Sandrina Wahyuning Dias');

        $this->artisan('tutor:set-permanent', [
            'name' => 'Sandrina Wahyuning',
            'salary' => '3500000',
            'since' => '2026-09-01',
        ])->assertSuccessful();

        $tutor->refresh();
        $this->assertSame('permanent', $tutor->employment_type);
        $this->assertEquals(3_500_000, $tutor->monthly_salary);
        $this->assertSame('2026-09-01', $tutor->salaried_since->toDateString());
        $this->assertNull($tutor->salaried_until);
    }

    #[Test]
    public function end_closes_the_salaried_period_and_keeps_history(): void
    {
        $tutor = $this->tutor('Budi Tetap');
        $tutor->update(['employment_type' => 'permanent', 'monthly_salary' => 2_000_000, 'salaried_since' => '2026-01-01']);

        $this->artisan('tutor:set-permanent', ['name' => 'Budi Tetap', '--end' => '2026-07-01'])
            ->assertSuccessful();

        $tutor->refresh();
        $this->assertSame('freelance', $tutor->employment_type);
        $this->assertEquals(2_000_000, $tutor->monthly_salary);          // riwayat tetap ada
        $this->assertSame('2026-06-30', $tutor->salaried_until->toDateString()); // hari terakhir = end - 1
        $this->assertSame('active', $tutor->status);
        $this->assertTrue($tutor->isSalariedOn('2026-06-30'));
        $this->assertFalse($tutor->isSalariedOn('2026-07-01'));
    }

    #[Test]
    public function end_with_fire_also_deactivates_the_tutor(): void
    {
        $tutor = $this->tutor('Cita Dipecat');
        $tutor->update(['employment_type' => 'permanent', 'monthly_salary' => 2_000_000, 'salaried_since' => '2026-01-01']);

        $this->artisan('tutor:set-permanent', ['name' => 'Cita Dipecat', '--end' => '2026-07-01', '--fire' => true])
            ->assertSuccessful();

        $this->assertSame('inactive', $tutor->refresh()->status);
    }

    #[Test]
    public function undo_is_blocked_once_a_salary_journal_exists(): void
    {
        $tutor = $this->tutor('Dewi Sudah Digaji');
        $tutor->update(['employment_type' => 'permanent', 'monthly_salary' => 2_000_000, 'salaried_since' => '2026-01-01']);
        Journal::factory()->create(['reference' => "PAYROLL-1-TUTOR-{$tutor->id}-SALARY"]);

        $this->artisan('tutor:set-permanent', ['name' => 'Dewi Sudah Digaji', '--undo' => true])
            ->assertFailed();

        $this->assertSame('permanent', $tutor->refresh()->employment_type);
    }

    #[Test]
    public function undo_fully_clears_when_no_salary_was_ever_paid(): void
    {
        $tutor = $this->tutor('Eka Salah Input');
        $tutor->update(['employment_type' => 'permanent', 'monthly_salary' => 2_000_000, 'salaried_since' => '2026-01-01']);

        $this->artisan('tutor:set-permanent', ['name' => 'Eka Salah Input', '--undo' => true])
            ->assertSuccessful();

        $tutor->refresh();
        $this->assertSame('freelance', $tutor->employment_type);
        $this->assertNull($tutor->monthly_salary);
        $this->assertNull($tutor->salaried_since);
    }

    #[Test]
    public function it_fails_when_the_name_is_ambiguous(): void
    {
        $this->tutor('Dewi Satu');
        $this->tutor('Dewi Dua');

        $this->artisan('tutor:set-permanent', ['name' => 'Dewi', 'salary' => '1000000', 'since' => '2026-09-01'])
            ->assertFailed();
    }
}
