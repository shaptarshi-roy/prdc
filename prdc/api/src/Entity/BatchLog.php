<?php
declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'batch_log')]
#[ApiResource(
    shortName: 'Batch',
    operations: [
        new Get(
            uriTemplate: '/batches/{id}/status',
            security: "is_granted('ROLE_FACILITY') and object.facilityId == user.getUserIdentifier()",
        ),
    ],
)]
class BatchLog
{
    public const STATUS_RECEIVED          = 'received';
    public const STATUS_VALIDATING        = 'validating';
    public const STATUS_COMPLETED         = 'completed';
    public const STATUS_PARTIAL           = 'partial_quarantine';
    public const STATUS_BLOCKED           = 'blocked_for_review';
    public const STATUS_FAILED            = 'failed';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    public Uuid $batchId;

    #[ORM\Column(name: 'facility_id', type: 'string')]
    public string $facilityId;

    #[ORM\Column(name: 'received_at', type: 'datetime_immutable')]
    public \DateTimeImmutable $receivedAt;

    #[ORM\Column(name: 'validated_at', type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $validatedAt = null;

    #[ORM\Column(name: 'completed_at', type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(name: 'raw_checksum', type: 'string', length: 64)]
    public string $rawChecksum;

    #[ORM\Column(name: 'row_count', type: 'integer', nullable: true)]
    public ?int $rowCount = null;

    #[ORM\Column(name: 'accepted_count', type: 'integer', nullable: true)]
    public ?int $acceptedCount = null;

    #[ORM\Column(name: 'rejected_count', type: 'integer', nullable: true)]
    public ?int $rejectedCount = null;

    #[ORM\Column(name: 'attempts', type: 'smallint')]
    public int $attempts = 0;

    #[ORM\Column(type: 'string')]
    public string $status = self::STATUS_RECEIVED;

    /**
     * Maximum number of times the worker will pick up the same batch
     * before giving up. Three is enough to absorb a flapping container
     * but few enough to surface genuinely poisonous payloads quickly.
     */
    public const MAX_ATTEMPTS = 3;

    /**
     * True for terminal states — the handler should never re-process
     * a batch in one of these states.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_PARTIAL,
            self::STATUS_BLOCKED,
            self::STATUS_FAILED,
        ], true);
    }

    /**
     * Called by the handler at the start of each processing attempt.
     * Returns false if the batch has exceeded MAX_ATTEMPTS, true otherwise.
     */
    public function beginAttempt(): bool
    {
        $this->attempts++;
        if ($this->attempts > self::MAX_ATTEMPTS) {
            $this->status      = self::STATUS_FAILED;
            $this->completedAt = new \DateTimeImmutable();
            return false;
        }
        $this->status      = self::STATUS_VALIDATING;
        $this->validatedAt = new \DateTimeImmutable();
        return true;
    }

    public function __construct(Uuid $batchId, string $facilityId, string $checksum)
    {
        $this->batchId     = $batchId;
        $this->facilityId  = $facilityId;
        $this->rawChecksum = $checksum;
        $this->receivedAt  = new \DateTimeImmutable();
    }

    public function markValidating(): void
    {
        $this->status       = self::STATUS_VALIDATING;
        $this->validatedAt  = new \DateTimeImmutable();
    }

    public function markCompleted(int $accepted, int $rejected): void
    {
        $this->status         = $rejected === 0
            ? self::STATUS_COMPLETED
            : self::STATUS_PARTIAL;
        $this->acceptedCount  = $accepted;
        $this->rejectedCount  = $rejected;
        $this->completedAt    = new \DateTimeImmutable();
    }

    public function markBlocked(): void
    {
        $this->status      = self::STATUS_BLOCKED;
        $this->completedAt = new \DateTimeImmutable();
    }
}
