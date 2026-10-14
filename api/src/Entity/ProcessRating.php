<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'process_ratings')]
class ProcessRating
{
    #[ORM\Id]
    #[ORM\Column(name: 'process_id', type: 'uuid')]
    public Uuid $processId;

    #[ORM\Column(name: 'treatment_id', type: 'string')]
    public string $treatmentId;

    #[ORM\Column(name: 'timepoint', type: 'string')]
    public string $timepoint;
}
