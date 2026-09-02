<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\ImportController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Jalankan import CSV migrasi lewat ImportController yang sama dg dipakai UI.
 * File dibaca dari _migrasi/output/. Sekali jalan, satu langkah per file.
 *
 *   php artisan migrasi:import            -> jalankan semua langkah berikutnya
 *   php artisan migrasi:import --only=students
 *   php artisan migrasi:import --pretend  -> hitung baris CSV saja, tidak import
 */
class RunMigrasiImport extends Command
{
    protected $signature = 'migrasi:import {--only=* : students|enrollments|installments|journals} {--pretend}';
    protected $description = 'Import CSV migrasi (_migrasi/output/*) via ImportController';

    private array $steps = [
        ['key' => 'students',     'file' => 'students.csv',     'method' => 'importStudents',     'as' => 1],
        ['key' => 'enrollments',  'file' => 'enrollments.csv',  'method' => 'importEnrollments',  'as' => 1],
        ['key' => 'installments', 'file' => 'installments.csv', 'method' => 'importInstallments', 'as' => 1],
        ['key' => 'journals',     'file' => 'journals.csv',     'method' => 'importJournals',     'as' => 2],
    ];

    public function handle(): int
    {
        $dir = base_path('_migrasi/output');
        $only = $this->option('only');
        $controller = app(ImportController::class);

        foreach ($this->steps as $step) {
            if ($only && !in_array($step['key'], $only, true)) {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $step['file'];
            if (!is_file($path)) {
                $this->error("  MISSING: {$path}");
                return self::FAILURE;
            }
            $rows = max(0, count(file($path, FILE_SKIP_EMPTY_LINES)) - 1);
            $this->line("== {$step['key']}  ({$rows} baris CSV) ==");

            if ($this->option('pretend')) {
                $this->line("   [pretend] lewati.");
                continue;
            }

            $before = $this->snapshot();

            Auth::loginUsingId($step['as']);

            $upload  = new UploadedFile($path, $step['file'], 'text/csv', null, true);
            $request = Request::create('/'.$step['key'], 'POST', [], [], ['file' => $upload], [
                'HTTP_REFERER' => url('/'),
            ]);
            $request->setLaravelSession(app('session.store'));
            $request->setUserResolver(fn () => Auth::user());
            app()->instance('request', $request);

            try {
                $response = $controller->{$step['method']}($request);
            } catch (\Throwable $e) {
                $this->error("   GAGAL: " . $e->getMessage());
                return self::FAILURE;
            }

            $session = $request->session();
            foreach (['success', 'error', 'warning'] as $lvl) {
                if ($session->has($lvl)) {
                    $line = "   [{$lvl}] " . $session->get($lvl);
                    $lvl === 'error' ? $this->error($line) : $this->line($line);
                }
            }
            $this->table(['tabel', 'sebelum', 'sesudah', 'delta'], $this->diff($before, $this->snapshot()));
            $this->newLine();
        }

        $this->info('selesai.');
        return self::SUCCESS;
    }

    private function snapshot(): array
    {
        return [
            'users'        => DB::table('users')->count(),
            'students'     => DB::table('students')->count(),
            'enrollments'  => DB::table('enrollments')->count(),
            'installments' => DB::table('installments')->count(),
            'journals'     => DB::table('journals')->count(),
            'journal_items'=> DB::table('journal_items')->count(),
        ];
    }

    private function diff(array $a, array $b): array
    {
        $out = [];
        foreach ($a as $k => $v) {
            $out[] = [$k, $v, $b[$k], sprintf('%+d', $b[$k] - $v)];
        }
        return $out;
    }
}
