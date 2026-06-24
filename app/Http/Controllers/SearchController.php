<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ClassSession;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\FixedAsset;
use App\Models\Journal;
use App\Models\PayrollRun;
use App\Models\Program;
use App\Models\Rab;
use App\Models\Student;
use App\Models\Tutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Global search controller for admin & CFO roles.
 *
 * Searches across multiple entity types in parallel and returns grouped
 * results. Designed for a topbar search box with debounced input.
 *
 * Design decisions:
 *   - Minimum 2 characters before searching (1-char queries are too noisy).
 *   - LIMIT 5 per category to keep payload small and UI scannable.
 *   - LIKE '%query%' for substring matching (case-insensitive on MySQL
 *     by default for utf8mb4_unicode_ci collation).
 *   - Role-aware: admin sees operational entities (students, tutors, programs,
 *     enrollments, class sessions, classrooms). CFO sees finance entities
 *     (accounts, journals, payroll runs, fixed assets, RABs).
 *   - Each result includes a `url` field so the frontend can navigate
 *     directly without reconstructing routes.
 *   - No N+1: each entity query is a single SELECT with LIMIT 5.
 */
class SearchController extends Controller
{
    /**
     * Handle the incoming search request.
     *
     * GET /search?q=<query>
     * Returns: { query: string, results: { category: [{id, label, subtitle, url}] }, total: int }
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $query = trim($request->input('q'));
        $role  = $request->user()?->role;

        if (mb_strlen($query) < 2) {
            return response()->json(['query' => $query, 'results' => [], 'total' => 0]);
        }

        $results = match ($role) {
            'admin' => $this->searchAdmin($query),
            'cfo'   => $this->searchCfo($query),
            default => [],
        };

        $total = array_sum(array_map(fn($group) => count($group), $results));

        return response()->json([
            'query'   => $query,
            'results' => $results,
            'total'   => $total,
        ]);
    }

    /**
     * Admin search: operational entities.
     */
    private function searchAdmin(string $q): array
    {
        $like = "%{$q}%";

        // Students — search by user.name or user.email
        // Eager-load user to avoid N+1 in the map.
        $students = Student::with('user')
            ->whereHas('user', function ($query) use ($like) {
                $query->where('name', 'like', $like)
                      ->orWhere('email', 'like', $like);
            })
            ->limit(5)
            ->get()
            ->map(fn ($s) => [
                'id'       => $s->id,
                'label'    => $s->user?->name ?? '—',
                'subtitle' => $s->user?->email ?? '',
                'url'      => route('admin.students.show', $s->id),
            ])
            ->all();

        // Tutors — search by user.name or user.email
        $tutors = Tutor::with('user')
            ->whereHas('user', function ($query) use ($like) {
                $query->where('name', 'like', $like)
                      ->orWhere('email', 'like', $like);
            })
            ->orWhere('persona', 'like', $like)
            ->limit(5)
            ->get()
            ->map(fn ($t) => [
                'id'       => $t->id,
                'label'    => $t->user?->name ?? '—',
                'subtitle' => $t->persona ?? $t->user?->email ?? '',
                'url'      => route('admin.tutors.show', $t->id),
            ])
            ->all();

        // Programs — search by name
        $programs = Program::where('name', 'like', $like)
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'id'       => $p->id,
                'label'    => $p->name,
                'subtitle' => ucfirst($p->type) . ' • Rp ' . number_format((float) $p->price, 0, ',', '.'),
                'url'      => route('admin.programs.index') . '#program-' . $p->id,
            ])
            ->all();

        // Enrollments — search by student name or by enrollment id (numeric)
        $enrollments = Enrollment::with(['student.user', 'program'])
            ->whereHas('student.user', function ($query) use ($like) {
                $query->where('name', 'like', $like);
            })
            ->limit(5)
            ->get()
            ->map(fn ($e) => [
                'id'       => $e->id,
                'label'    => 'Enrollment #' . $e->id . ' — ' . ($e->student?->user?->name ?? '—'),
                'subtitle' => ($e->program?->name ?? '—') . ' • ' . ucfirst($e->status),
                'url'      => route('admin.enrollments.show', $e->id),
            ])
            ->all();

        // Class Sessions — search by name or program name
        $classSessions = ClassSession::with('program')
            ->where('name', 'like', $like)
            ->orWhereHas('program', function ($query) use ($like) {
                $query->where('name', 'like', $like);
            })
            ->limit(5)
            ->get()
            ->map(fn ($cs) => [
                'id'       => $cs->id,
                'label'    => $cs->name,
                'subtitle' => ($cs->program?->name ?? '—') . ' • ' . ucfirst($cs->class_type ?? ''),
                'url'      => route('admin.class-sessions.show', $cs->id),
            ])
            ->all();

        // Classrooms — search by name
        $classrooms = Classroom::where('name', 'like', $like)
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'id'       => $c->id,
                'label'    => $c->name,
                'subtitle' => 'Kapasitas ' . $c->capacity . ' siswa',
                'url'      => route('admin.classrooms.index') . '#classroom-' . $c->id,
            ])
            ->all();

        return $this->filterEmptyGroups([
            'Students'       => $students,
            'Tutors'         => $tutors,
            'Programs'       => $programs,
            'Enrollments'    => $enrollments,
            'Class Sessions' => $classSessions,
            'Classrooms'     => $classrooms,
        ]);
    }

    /**
     * CFO search: finance entities.
     */
    private function searchCfo(string $q): array
    {
        $like = "%{$q}%";

        // Accounts — search by code or name
        $accounts = Account::where('code', 'like', $like)
            ->orWhere('name', 'like', $like)
            ->orderBy('code')
            ->limit(5)
            ->get()
            ->map(fn ($a) => [
                'id'       => $a->id,
                'label'    => $a->code . ' — ' . $a->name,
                'subtitle' => $a->type,
                'url'      => route('finance.accounts.index') . '#account-' . $a->id,
            ])
            ->all();

        // Journals — search by reference or description
        $journals = Journal::where('reference', 'like', $like)
            ->orWhere('description', 'like', $like)
            ->orderByDesc('date')
            ->limit(5)
            ->get()
            ->map(fn ($j) => [
                'id'       => $j->id,
                'label'    => $j->reference,
                'subtitle' => $j->description . ' • Rp ' . number_format((float) $j->total_amount, 0, ',', '.'),
                'url'      => route('finance.journals.show', $j->id),
            ])
            ->all();

        // Payroll Runs — search by month (YYYY-MM or month name)
        $payrollRuns = PayrollRun::where('month', 'like', $like)
            ->orWhere('status', 'like', $like)
            ->orderByDesc('month')
            ->limit(5)
            ->get()
            ->map(fn ($pr) => [
                'id'       => $pr->id,
                'label'    => 'Payroll ' . \Carbon\Carbon::parse($pr->month)->isoFormat('MMMM YYYY'),
                'subtitle' => ucfirst($pr->status) . ' • ' . $pr->created_at->format('d M Y'),
                'url'      => route('finance.payroll.index') . '#payroll-' . $pr->id,
            ])
            ->all();

        // Fixed Assets — search by name or category
        $fixedAssets = FixedAsset::where('name', 'like', $like)
            ->orWhere('category', 'like', $like)
            ->limit(5)
            ->get()
            ->map(fn ($fa) => [
                'id'       => $fa->id,
                'label'    => $fa->name,
                'subtitle' => $fa->category . ' • Rp ' . number_format((float) $fa->cost, 0, ',', '.'),
                'url'      => route('finance.assets.index') . '#asset-' . $fa->id,
            ])
            ->all();

        // RABs — search by division, account_name, or activity
        $rabs = Rab::where('division', 'like', $like)
            ->orWhere('account_name', 'like', $like)
            ->orWhere('activity', 'like', $like)
            ->orWhere('year', 'like', $like)
            ->orderByDesc('year')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id'       => $r->id,
                'label'    => $r->division . ' — ' . $r->account_name . ' (' . $r->year . ')',
                'subtitle' => ($r->activity ?? '—') . ' • Rp ' . number_format((int) $r->total, 0, ',', '.'),
                'url'      => route('finance.rab.index') . '#rab-' . $r->id,
            ])
            ->all();

        return $this->filterEmptyGroups([
            'Accounts'      => $accounts,
            'Journals'      => $journals,
            'Payroll Runs'  => $payrollRuns,
            'Fixed Assets'  => $fixedAssets,
            'RAB'           => $rabs,
        ]);
    }

    /**
     * Strip out empty groups so the frontend doesn't render empty section headers.
     *
     * @param array<string, array> $groups
     * @return array<string, array>
     */
    private function filterEmptyGroups(array $groups): array
    {
        return array_filter($groups, fn ($items) => !empty($items));
    }
}
