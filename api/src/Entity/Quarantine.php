<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'quarantine')]
class Quarantine
{
    #[ORM\Id]
    #[ORM\Column(name: 'quarantine_id', type: 'bigint')]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    public ?int $quarantineId = null;

    #[ORM\Column(name: 'batch_id', type: 'uuid')]
    public Uuid $batchId;

    #[ORM\Column(name: 'row_idx', type: 'integer')]
    public int $rowIdx;

    #[ORM\Column(name: 'gate_num', type: 'smallint')]
    public int $gateNum;

    #[ORM\Column(name: 'error_code', type: 'string')]
    public string $errorCode;

    #[ORM\Column(name: 'error_field', type: 'string', nullable: true)]
    public ?string $errorField = null;

    #[ORM\Column(name: 'colliding_value', type: 'string', nullable: true)]
    public ?string $collidingValue = null;

    #[ORM\Column(name: 'existing_batch_id', type: 'uuid', nullable: true)]
    public ?Uuid $existingBatchId = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;

    public function __construct(
        Uuid $batchId,
        int $rowIdx,
        int $gateNum,
        string $errorCode,
        ?string $errorField = null,
    ) {
        $this->batchId    = $batchId;
        $this->rowIdx     = $rowIdx;
        $this->gateNum    = $gateNum;
        $this->errorCode  = $errorCode;
        $this->errorField = $errorField;
        $this->createdAt  = new \DateTimeImmutable();
    }
}
