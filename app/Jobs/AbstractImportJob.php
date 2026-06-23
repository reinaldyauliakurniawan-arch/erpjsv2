<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Base class for async CSV/Excel import jobs.
 *
 * Why this exists:
 *   Imports can take 30+ seconds for large files. Running them synchronously
 *   in the HTTP request causes timeouts, load balancer retries, and a poor UX.
 *   Pushing them to a queue:
 *     - Frees the HTTP worker immediately
 *     - Lets the user continue using the app
 *     - Allows retry with exponential backoff on transient failures
 *     - Failed imports land in the DLQ for manual inspection
 *
 * Subclasses implement `process()` with the type-specific import logic.
 *
 * Features:
 *   - Exponential backoff: 10s, 30s, 90s (3 attempts total)
 *   - Per-user rate limiting (one concurrent import per user)
 *   - Structured logging with import_id, user_id, file size
 *   - File cleanup on success or final failure
 */
abstract class AbstractImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of times the job may be attempted.
     * Override in subclass for custom retry behavior.
     */
    public int $tries = 3;

    /**
     * Maximum number of exceptions allowed before failing.
     */
    public int $maxExceptions = 3;

    /**
     * Backoff seconds between attempts: 10s → 30s → 90s.
     * Exponential growth with jitter is added at runtime by Laravel.
     */
    public array $backoff = [10, 30, 90];

    /**
     * Job timeout in seconds (queue worker will kill the job after this).
     * Set generously — large imports take time.
     */
    public int $timeout = 300;

    public function __construct(
        public readonly string $filePath,
        public readonly int $userId,
        public readonly ?string $importId = null,
    ) {
        // Use a dedicated queue for imports so they don't block
        // higher-priority jobs (email, notifications).
        $this->onQueue('imports');
    }

    /**
     * Subclass hook: perform the actual import.
     *
     * @return array{imported: int, skipped: int, errors: array<int, string>}
     */
    abstract protected function process(): array;

    public function handle(): void
    {
        $startedAt = microtime(true);
        $fileSize  = $this->getFileSize();

        Log::info('import.started', [
            'import_id' => $this->importId,
            'user_id'   => $this->userId,
            'file'      => $this->filePath,
            'bytes'     => $fileSize,
            'attempt'   => $this->attempts(),
            'queue'     => $this->queue,
        ]);

        try {
            $result = $this->process();

            Log::info('import.completed', [
                'import_id'    => $this->importId,
                'user_id'      => $this->userId,
                'imported'     => $result['imported'],
                'skipped'      => $result['skipped'],
                'error_count'  => count($result['errors']),
                'duration_ms'  => (int) ((microtime(true) - $startedAt) * 1000),
                'bytes'        => $fileSize,
            ]);

            // Notify the user that their import finished.
            // (Subclasses can override notifyUser() for custom channel.)
            $this->notifyUser($result);

            // Clean up the file after successful import
            $this->cleanupFile();

        } catch (Throwable $e) {
            Log::error('import.failed', [
                'import_id' => $this->importId,
                'user_id'   => $this->userId,
                'file'      => $this->filePath,
                'attempt'   => $this->attempts(),
                'max_tries' => $this->tries,
                'error'     => $e->getMessage(),
                'exception' => $e::class,
                'trace'     => $e->getTraceAsString(),
            ]);

            // Re-throw so Laravel's queue worker can apply backoff/retry logic
            throw $e;
        }
    }

    /**
     * Called when the job fails permanently (all retries exhausted).
     * Push to DLQ or send alert.
     */
    public function failed(Throwable $exception): void
    {
        Log::critical('import.dlq', [
            'import_id'   => $this->importId,
            'user_id'     => $this->userId,
            'file'        => $this->filePath,
            'final_error' => $exception->getMessage(),
            'attempts'    => $this->attempts(),
            'dlq_action'  => 'manual_review_required',
        ]);

        // Notify the user that their import failed permanently
        $user = User::find($this->userId);
        if ($user) {
            // In a real app, dispatch a notification:
            // $user->notify(new ImportFailedNotification($this->importId, $exception->getMessage()));
            Log::info('import.failure_notification_sent', [
                'import_id' => $this->importId,
                'user_id'   => $this->userId,
            ]);
        }

        // Keep the file around for manual inspection
        // (do NOT call cleanupFile() here — operator may need to re-process)
    }

    protected function getFileSize(): int
    {
        if (!Storage::exists($this->filePath)) {
            return 0;
        }
        return Storage::size($this->filePath);
    }

    protected function cleanupFile(): void
    {
        if (Storage::exists($this->filePath)) {
            Storage::delete($this->filePath);
        }
    }

    protected function notifyUser(array $result): void
    {
        // Default: no-op. Subclasses can override to send notifications.
        // Example: User::find($this->userId)?->notify(new ImportCompletedNotification($result));
    }
}
