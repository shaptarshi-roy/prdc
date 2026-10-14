<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\BatchLog;
use App\Message\ValidateBatch;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * POST /batches
 *
 * Accepts one CSV representing a completed treatment episode, persists it
 * to the batch storage volume, then writes a batch_log row and dispatches an
 * async validation job in one transaction. Returns 202 with a tracking id.
 *
 * The heavy lifting — parsing, gating, writing the six tables — happens
 * in ValidateBatchHandler, triggered by the Messenger worker.
 */
#[IsGranted('ROLE_FACILITY')]
final class BatchSubmitController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly string $storagePath,
    ) {}

    #[Route('/batches', name: 'batch_submit', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $file = $request->files->get('batch');
        if ($file === null) {
            throw new BadRequestHttpException('missing file field "batch"');
        }
        if ($file->getClientMimeType() !== 'text/csv'
            && !str_ends_with(strtolower($file->getClientOriginalName()), '.csv')) {
            throw new BadRequestHttpException('batch must be CSV');
        }

        $batchId  = Uuid::v7();
        $path     = sprintf('%s/raw/%s.csv', $this->storagePath, $batchId);
        @mkdir(dirname($path), 0o750, recursive: true);
        $file->move(dirname($path), basename($path));

        $checksum = hash_file('sha256', $path);

        $facilityId = (string) $this->getUser()->getUserIdentifier();
        // Strip any 'facility_' prefix from the dev user provider so the
        // identifier matches the F-codes used in the CSV. In production,
        // map the client-cert subject directly to a facility_id.
        $facilityId = preg_replace('/^facility_/', '', $facilityId);

        $batch = new BatchLog($batchId, $facilityId, $checksum);

        // The Messenger transport is a table on the same connection, so the
        // batch_log row and the job commit together. If enqueuing fails, the
        // row rolls back instead of sitting in 'received' forever with no
        // job to process it. The worker can't see the job before the row.
        try {
            $this->em->wrapInTransaction(function () use ($batch, $batchId): void {
                $this->em->persist($batch);
                $this->em->flush();
                $this->bus->dispatch(new ValidateBatch((string) $batchId));
            });
        } catch (\Throwable $e) {
            // The file isn't covered by the transaction; don't leave it behind.
            @unlink($path);
            throw $e;
        }

        return new JsonResponse(
            [
                'batch_id' => (string) $batchId,
                'status'   => $batch->status,
            ],
            Response::HTTP_ACCEPTED,
        );
    }
}
