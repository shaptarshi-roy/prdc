<?php
declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\BatchLog;
use App\Message\ValidateBatch;
use App\Validation\ValidationPipeline;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Validation job consumer.
 *
 * Idempotency design: the only states we skip on are *terminal* —
 * `completed`, `partial_quarantine`, `blocked_for_review`, `failed`.
 * Anything else (including `validating`) means the previous attempt
 * died mid-flight and recovery is needed.
 *
 * The pipeline is written so a re-run is safe:
 *   - Inserts run inside a single transaction; a crash mid-flight rolls
 *     them back, so a retry sees a clean DB.
 *   - Quarantine entries from the previous attempt are deleted at the
 *     start of each run so the report doesn't accumulate duplicates.
 *
 * To stop genuinely poisonous batches from looping forever, batch_log
 * carries an `attempts` counter. After MAX_ATTEMPTS the batch is
 * marked `failed` and Messenger no longer redelivers.
 */
#[AsMessageHandler]
final class ValidateBatchHandler
{
    public function __construct(
        private readonly ValidationPipeline $pipeline,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $storagePath,
    ) {}

    public function __invoke(ValidateBatch $msg): void
    {
        $batch = $this->em->find(BatchLog::class, Uuid::fromString($msg->batchId));
        if ($batch === null) {
            $this->logger->warning('batch not found', ['batch_id' => $msg->batchId]);
            return;
        }

        // Skip only on terminal states. A batch in `validating` means
        // the previous attempt crashed; we WILL retry it.
        if ($batch->isTerminal()) {
            $this->logger->info('batch already in terminal state; skipping', [
                'batch_id' => $msg->batchId,
                'status'   => $batch->status,
            ]);
            return;
        }

        // Bump the attempt counter. If we've exceeded the cap, give up
        // gracefully so Messenger stops redelivering.
        if (!$batch->beginAttempt()) {
            $this->em->flush();
            $this->logger->error('batch exceeded MAX_ATTEMPTS; marking failed', [
                'batch_id' => $msg->batchId,
                'attempts' => $batch->attempts,
            ]);
            return;
        }
        $this->em->flush();

        if ($batch->attempts > 1) {
            $this->logger->warning('retrying batch after previous crash', [
                'batch_id' => $msg->batchId,
                'attempts' => $batch->attempts,
            ]);
        }

        $csvPath = sprintf('%s/raw/%s.csv', $this->storagePath, $batch->batchId);
        if (!is_readable($csvPath)) {
            $batch->status      = BatchLog::STATUS_FAILED;
            $batch->completedAt = new \DateTimeImmutable();
            $this->em->flush();
            $this->logger->error('raw CSV missing', ['path' => $csvPath]);
            return;
        }

        try {
            $this->pipeline->run($batch, $csvPath);
        } catch (\Throwable $e) {
            // Don't mark failed here — let Messenger retry. The DB
            // transaction has already rolled back, so partial data
            // isn't visible. Status stays `validating` until the next
            // attempt or until MAX_ATTEMPTS is hit.
            $this->logger->error('pipeline crashed', [
                'batch_id' => $msg->batchId,
                'attempts' => $batch->attempts,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
