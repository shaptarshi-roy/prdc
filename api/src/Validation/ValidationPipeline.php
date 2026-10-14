<?php
declare(strict_types=1);

namespace App\Validation;

use App\Entity\BatchLog;
use App\Entity\Quarantine;
use App\Validation\Gate\GateFailure;
use App\Validation\Gate\ValidationGateInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Streaming validation + write pipeline.
 *
 * The CSV is parsed row-by-row via a generator. The whole batch is
 * never held in memory. Two separate persistence paths:
 *
 *   1. Data tables go through DBAL Connection::insert() wrapped in one
 *      Postgres transaction. If anything fails — gate-5 collision,
 *      constraint violation, worker crash — the entire data write rolls
 *      back. We never commit partial batches.
 *
 *   2. Quarantine entries go through the ORM in autocommit mode (no
 *      explicit transaction). They are persisted in batches of
 *      FLUSH_INTERVAL rows so memory stays bounded, AND they survive
 *      a data-side rollback so the DTS sees what went wrong.
 *
 * The two paths share a Connection but use it differently. Doctrine
 * DBAL allows nested transactions logically — we're careful to keep
 * the quarantine writes outside the explicit beginTransaction/commit
 * pair.
 */
final class ValidationPipeline
{
    use PipelineInserts;

    /** Flush+clear the ORM every N quarantine rows. */
    private const FLUSH_INTERVAL = 500;

    /** @var list<ValidationGateInterface> */
    private array $gates;

    /**
     * @param iterable<ValidationGateInterface> $gates tagged 'app.validation_gate'
     */
    public function __construct(
        iterable $gates,
        private readonly EntityManagerInterface $em,
        private readonly Connection $db,
        private readonly LoggerInterface $logger,
    ) {
        $arr = $gates instanceof \Traversable
            ? iterator_to_array($gates, false)
            : $gates;
        usort($arr, fn($a, $b) => $a->number() <=> $b->number());
        $this->gates = $arr;
    }

    public function run(BatchLog $batch, string $csvPath): void
    {
        // Retry hygiene: drop any quarantine left by a previous crash.
        $this->db->executeStatement(
            'DELETE FROM quarantine WHERE batch_id = ?',
            [(string) $batch->batchId],
        );

        $batchId = $batch->batchId;
        $ctx     = new BatchContext();

        $accepted = 0;
        $rejected = 0;
        $hasCollision = false;
        $sinceFlush = 0;

        // Per-batch dedupe: parent rows are written once even if many
        // rows in the batch share the same facility/therapist/patient/treatment.
        $seenFacility  = [];
        $seenTherapist = [];
        $seenPatient   = [];
        $seenTreatment = [];

        $this->db->beginTransaction();

        try {
            foreach ($this->streamCsv($csvPath) as $rowIdx => $row) {

                $failure = $this->checkAllGates($row, $rowIdx, $ctx);

                if ($failure !== null) {
                    // Quarantine writes go through the ORM with NO explicit
                    // transaction — they autocommit per flush() and survive
                    // a data-side rollback.
                    $this->persistQuarantine($batchId, $rowIdx, $failure);
                    $rejected++;
                    if ($failure->gateNum === 5) {
                        $hasCollision = true;
                    }
                } else {
                    $this->writeRow(
                        $row, $batchId,
                        $seenFacility, $seenTherapist, $seenPatient, $seenTreatment,
                    );
                    $accepted++;
                }

                // Record AFTER gates so a row's own values don't appear as
                // priors when gate 4 checks itself.
                $ctx->record($row);

                $sinceFlush++;
                if ($sinceFlush >= self::FLUSH_INTERVAL) {
                    $this->flushAndClearOrm($batchId, $batch);
                    $sinceFlush = 0;
                }
            }

            // Final flush of pending quarantine entries before deciding
            // whether to commit or roll back data.
            $this->em->flush();

            $batch->rowCount = $accepted + $rejected;

            if ($hasCollision) {
                // Roll back ALL data-table writes. Quarantine survives
                // because it was flushed outside this transaction.
                $this->db->rollBack();

                $batch->markBlocked();
                $this->em->flush();

                $this->logger->warning('batch blocked for review', [
                    'batch_id' => (string) $batchId,
                    'rejected' => $rejected,
                ]);
                return;
            }

            $batch->markCompleted($accepted, $rejected);
            $this->em->flush();
            $this->db->commit();

            $this->logger->info('batch processed', [
                'batch_id' => (string) $batchId,
                'accepted' => $accepted,
                'rejected' => $rejected,
            ]);

        } catch (\Throwable $e) {
            if ($this->db->isTransactionActive()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Yields one parsed row at a time. The CSV is never loaded as a whole.
     *
     * @return \Generator<int, array<string,string>>
     */
    private function streamCsv(string $path): \Generator
    {
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            throw new \RuntimeException("cannot open CSV $path");
        }

        try {
            $header = fgetcsv($fh, 0, ',', '"', '');
            if ($header === false) {
                throw new \RuntimeException("empty CSV $path");
            }

            $idx = 0;
            while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
                $idx++;
                if (count($row) !== count($header)) {
                    $row = array_pad($row, count($header), '');
                }
                yield $idx => array_combine($header, $row);
            }
        } finally {
            fclose($fh);
        }
    }

    private function checkAllGates(
        array $row,
        int $rowIdx,
        BatchContext $ctx,
    ): ?GateFailure {
        foreach ($this->gates as $gate) {
            $failure = $gate->check($row, $rowIdx, $ctx);
            if ($failure !== null) {
                return $failure;
            }
        }
        return null;
    }

    private function persistQuarantine(
        Uuid $batchId,
        int $rowIdx,
        GateFailure $failure,
    ): void {
        $q = new Quarantine(
            batchId:    $batchId,
            rowIdx:     $rowIdx,
            gateNum:    $failure->gateNum,
            errorCode:  $failure->errorCode,
            errorField: $failure->errorField,
        );
        $q->collidingValue   = $failure->collidingValue;
        $q->existingBatchId  = $failure->existingBatchId
            ? Uuid::fromString($failure->existingBatchId) : null;
        $this->em->persist($q);
    }

    /**
     * Insert one accepted row across the six data tables. The first
     * occurrence of each facility/therapist/patient/treatment in the
     * batch is inserted; later rows skip those parent inserts.
     */
    private function writeRow(
        array $row,
        Uuid $batchId,
        array &$seenFacility,
        array &$seenTherapist,
        array &$seenPatient,
        array &$seenTreatment,
    ): void {
        if (!isset($seenFacility[$row['facility_id']])) {
            $this->insertFacility($row, $batchId);
            $seenFacility[$row['facility_id']] = true;
        }
        if (!isset($seenTherapist[$row['therapist_id']])) {
            $this->insertTherapist($row, $batchId);
            $seenTherapist[$row['therapist_id']] = true;
        }
        if (!isset($seenPatient[$row['patient_id']])) {
            $this->insertPatient($row, $batchId);
            $seenPatient[$row['patient_id']] = true;
        }
        if (!isset($seenTreatment[$row['treatment_id']])) {
            $this->insertTreatment($row, $batchId);
            $seenTreatment[$row['treatment_id']] = true;
        }
        $this->insertAssessment($row, $batchId);
        if (($row['timepoint'] ?? '') !== 'pre') {
            $this->insertProcessRating($row, $batchId);
        }
    }

    /**
     * Flush pending ORM operations (quarantine entries, batch_log status)
     * and clear the identity map so memory doesn't grow with batch size.
     *
     * BatchLog gets cleared by clear(); we reload it so the caller can
     * keep using the same variable after the flush.
     */
    private function flushAndClearOrm(Uuid $batchId, BatchLog &$batch): void
    {
        $this->em->flush();
        $this->em->clear();
        $batch = $this->em->find(BatchLog::class, $batchId);
    }
}
