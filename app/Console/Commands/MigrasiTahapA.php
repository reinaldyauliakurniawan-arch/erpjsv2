<?php

namespace App\Console\Commands;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Tutor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * MIGRASI FASE 3/4 — TAHAP A.
 *
 * Menghubungkan tiap enrollment ke class_session berdasarkan hasil
 * pencocokan sheet Absen (_migrasi/_dump_sumber/tahap_a_proposal.json,
 * dibangun oleh _migrasi/tahap_a_match.py).
 *
 * Yang dilakukan:
 *   1. Untuk tiap sheet Absen "real": pastikan ada class_session
 *      (pakai kandidat yang cocok, atau buat baru dengan nama sheet).
 *   2. Set/segarkan daftar tutor class_session dari kolom Tutor di sheet.
 *   3. Set enrollments.class_session_id untuk tiap siswa yang cocok yakin.
 *
 * TIDAK menyentuh buku besar / attendance — itu Tahap B.
 *
 *   php artisan migrasi:tahap-a           # dry-run
 *   php artisan migrasi:tahap-a --apply
 */
class MigrasiTahapA extends Command
{
    protected $signature = 'migrasi:tahap-a {--apply : Tulis perubahan}';

    protected $description = 'Fase 3/4 Tahap A — hubungkan enrollment ke class_session dari data Absen';

    private const PROPOSAL = 'C:\laragon\www\erpjsv2\_migrasi\_dump_sumber\tahap_a_proposal.json';

    public function handle(): int
    {
        if (! is_file(self::PROPOSAL)) {
            $this->error('Proposal belum dibuat. Jalankan: python _migrasi/tahap_a_match.py');

            return self::FAILURE;
        }

        $proposal = json_decode(file_get_contents(self::PROPOSAL), true);
        $apply = (bool) $this->option('apply');
        $tag = $apply ? '' : '[DRY-RUN] ';

        $tutorByName = $this->tutorLookup();
        $stats = ['cs_created' => 0, 'cs_reused' => 0, 'tutor_attached' => 0, 'enr_linked' => 0, 'enr_skipped' => 0, 'enr_conflict' => [], 'unresolved_cs' => []];
        $linkedThisRun = []; // enrollment_id => sheet name (sheet pertama menang)

        DB::beginTransaction();
        try {
            foreach ($proposal['sheets'] as $sheet) {
                $confidentStudents = array_values(array_filter($sheet['students'], fn ($s) => ! empty($s['enrollment_id'])));
                if (! $confidentStudents) {
                    continue; // tidak ada siswa yakin -> lewati sheet ini di Tahap A
                }

                $cs = $this->resolveClassSession($sheet, $confidentStudents, $stats);
                if (! $cs) {
                    $stats['unresolved_cs'][] = $sheet['sheet'];

                    continue;
                }

                // tutor
                foreach ($sheet['tutors'] as $tn) {
                    $tid = $this->matchTutor($tn, $tutorByName);
                    if ($tid && ! $cs->tutors()->where('tutors.id', $tid)->exists()) {
                        $cs->tutors()->attach($tid, ['status' => 'confirmed']);
                        $stats['tutor_attached']++;
                    }
                }

                // link enrollments
                foreach ($confidentStudents as $st) {
                    $e = Enrollment::find($st['enrollment_id']);
                    if (! $e) {
                        continue;
                    }
                    if (isset($linkedThisRun[$e->id])) {
                        $stats['enr_conflict'][] = "enr #{$e->id} diklaim '{$linkedThisRun[$e->id]}' & '{$sheet['sheet']}'";

                        continue;
                    }
                    $linkedThisRun[$e->id] = $sheet['sheet'];
                    if ($e->class_session_id === $cs->id) {
                        $stats['enr_skipped']++;

                        continue;
                    }
                    $before = $e->class_session_id;
                    $e->update(['class_session_id' => $cs->id]);
                    $stats['enr_linked']++;
                    $this->line(sprintf('  %senr #%d %-30s : cs %s -> #%d %s',
                        $tag, $e->id, Str::limit($st['sheet_name'], 30), $before ?? '(null)', $cs->id, $cs->name));
                }
            }

            $this->newLine();
            $this->info('== RINGKAS ==');
            foreach (['cs_created', 'cs_reused', 'tutor_attached', 'enr_linked', 'enr_skipped'] as $k) {
                $this->line("  {$k}: {$stats[$k]}");
            }
            if ($stats['unresolved_cs']) {
                $this->warn('  class_session tak terpecahkan: '.implode(', ', $stats['unresolved_cs']));
            }
            foreach ($stats['enr_conflict'] as $c) {
                $this->warn('  KONFLIK: '.$c);
            }

            $apply ? DB::commit() : DB::rollBack();
            $this->info($apply ? 'Tersimpan.' : 'Dry-run — jalankan dengan --apply.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('GAGAL (rollback): '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveClassSession(array $sheet, array $students, array &$stats): ?ClassSession
    {
        // program_id target diambil dari enrollment mayoritas (paling akurat).
        $progIds = Enrollment::whereIn('id', array_column($students, 'enrollment_id'))->pluck('program_id');
        $targetProgram = $progIds->countBy()->sortDesc()->keys()->first();

        $type = match (true) {
            $sheet['tag'] === 'reguler' => 'group',
            str_contains(strtolower($sheet['tipe'] ?? ''), 'semi') => 'semi-private',
            default => 'private',
        };

        // kandidat existing: ambil yang program-nya cocok, kalau tidak ada ambil kandidat pertama
        foreach ($sheet['cs_candidates'] as $cid) {
            $cs = ClassSession::find($cid);
            if ($cs && $cs->program_id == $targetProgram) {
                $stats['cs_reused']++;

                return $cs;
            }
        }
        if ($sheet['cs_how'] === 'exact' && $sheet['cs_candidates']) {
            $cs = ClassSession::find($sheet['cs_candidates'][0]);
            if ($cs) {
                // nama cocok persis tapi program beda -> betulkan program & tipe
                $cs->update(['program_id' => $targetProgram, 'class_type' => $type]);
                $stats['cs_reused']++;

                return $cs;
            }
        }

        // buat baru
        $name = $this->uniqueName($sheet['sheet']);
        $cs = ClassSession::create([
            'name' => $name,
            'program_id' => $targetProgram,
            'class_type' => $type,
            'status' => 'active',
        ]);
        $stats['cs_created']++;
        $this->line("  + class_session baru #{$cs->id} '{$name}' (program {$targetProgram}, {$type})");

        return $cs;
    }

    private function uniqueName(string $base): string
    {
        $name = trim($base);
        $i = 2;
        while (ClassSession::where('name', $name)->exists()) {
            $name = trim($base)." ({$i})";
            $i++;
        }

        return $name;
    }

    /** @return array<string,int> normalized tutor name => tutor id */
    private function tutorLookup(): array
    {
        $map = [];
        foreach (Tutor::with('user')->get() as $t) {
            $map[$this->norm($t->user->name)] = $t->id;
        }

        return $map;
    }

    private function matchTutor(string $name, array $lookup): ?int
    {
        $n = $this->norm($name);
        if (isset($lookup[$n])) {
            return $lookup[$n];
        }
        // prefix / token pertama
        foreach ($lookup as $k => $id) {
            if ($k === $n || str_starts_with($k, $n) || str_starts_with($n, $k)) {
                return $id;
            }
        }
        $nt = explode(' ', $n)[0];
        foreach ($lookup as $k => $id) {
            if (str_starts_with($k, $nt) && strlen($nt) >= 4) {
                return $id;
            }
        }

        return null;
    }

    private function norm(string $s): string
    {
        $s = strtolower(trim(preg_replace('/[^a-zA-Z ]/', ' ', $s)));

        return preg_replace('/\s+/', ' ', $s);
    }
}
