<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\BatchLog;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Two GET endpoints used by the facility client to poll after submission:
 *
 *   GET /batches/{id}/status  → lifecycle state + counts
 *   GET /batches/{id}/errors  → per-row quarantine report (metadata only)
 *
 * Both enforce that the caller's facility owns the batch — no cross-facility
 * leakage possible.
 */
#[IsGranted('ROLE_FACILITY')]
final class BatchQueryController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $db,
    ) {}

    #[Route('/batches/{id}/status', methods: ['GET'])]
    public function status(string $id): JsonResponse
    {
        $batch = $this->findOwnedBatch($id);
        return new JsonResponse([
            'batch_id'     => (string) $batch->batchId,
            'status'       => $batch->status,
            'received_at'  => $batch->receivedAt->format(\DateTimeInterface::ATOM),
            'completed_at' => $batch->completedAt?->format(\DateTimeInterface::ATOM),
            'row_count'    => $batch->rowCount,
            'accepted'     => $batch->acceptedCount,
            'rejected'     => $batch->rejectedCount,
            'errors_url'   => ($batch->rejectedCount ?? 0) > 0
                ? "/batches/{$id}/errors"
                : null,
        ]);
    }

    #[Route('/batches/{id}/errors', methods: ['GET'])]
    public function errors(string $id): JsonResponse
    {
        $batch = $this->findOwnedBatch($id);

        $rows = $this->db->fetchAllAssociative(
            'SELECT row_idx, gate_num, error_code, error_field,
                    colliding_value, existing_batch_id
               FROM quarantine
              WHERE batch_id = ?
              ORDER  BY row_idx, gate_num',
            [(string) $batch->batchId],
        );

        return new JsonResponse([
            'batch_id' => (string) $batch->batchId,
            'errors'   => $rows,
        ]);
    }

    private function findOwnedBatch(string $id): BatchLog
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            throw new NotFoundHttpException();
        }

        $batch = $this->em->find(BatchLog::class, $uuid);
        if ($batch === null) {
            throw new NotFoundHttpException();
        }

        $facilityId = preg_replace(
            '/^facility_/', '',
            (string) $this->getUser()->getUserIdentifier(),
        );
        if ($batch->facilityId !== $facilityId) {
            throw new NotFoundHttpException();   // 404 not 403 — don't leak existence
        }

        return $batch;
    }
}
