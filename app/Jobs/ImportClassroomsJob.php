<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Classroom;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Async import job for Classroom records from a CSV file.
 *
 * Expected CSV format:
 *   name,capacity
 *   Room A,15
 *   Room B,20
 *
 * Usage:
 *   $path = $request->file('file')->store('imports');
 *   ImportClassroomsJob::dispatch($path, $request->user()->id, Str::uuid()->toString());
 *
 * Behavior:
 *   - Skips rows with invalid capacity (non-integer or < 1)
 *   - Updates existing classrooms by name (idempotent re-import)
 *   - Logs per-row errors but continues processing remaining rows
 *   - Returns counts: imported, skipped, errors
 */
class ImportClassroomsJob extends AbstractImportJob
{
    protected function process(): array
    {
        $content = Storage::get($this->filePath);
        if ($content === null) {
            throw new \RuntimeException("Cannot read import file at path: {$this->filePath}");
        }

        $lines = array_map('str_getcsv', explode("\n", trim($content)));
        if (count($lines) < 2) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['File has no data rows']];
        }

        // First row is the header
        $header = array_map('trim', $lines[0]);
        $rows   = array_slice($lines, 1);

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // 1-indexed, +1 for header

            if (count($row) < 2) {
                $errors[] = "Row {$rowNum}: insufficient columns (expected 2, got " . count($row) . ")";
                $skipped++;
                continue;
            }

            [$name, $capacityStr] = array_map('trim', $row);

            if ($name === '') {
                $errors[] = "Row {$rowNum}: name is required";
                $skipped++;
                continue;
            }

            $capacity = (int) $capacityStr;
            if ($capacity < 1) {
                $errors[] = "Row {$rowNum}: capacity must be a positive integer (got '{$capacityStr}')";
                $skipped++;
                continue;
            }

            try {
                // Idempotent: updateOrInsert by name so re-imports don't create duplicates
                Classroom::updateOrCreate(
                    ['name' => $name],
                    ['capacity' => $capacity],
                );
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowNum}: failed to import '{$name}' — {$e->getMessage()}";
                $skipped++;
            }
        }

        return [
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ];
    }
}
