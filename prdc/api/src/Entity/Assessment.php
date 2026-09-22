<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'assessments')]
class Assessment
{
    #[ORM\Id]
    #[ORM\Column(name: 'assessment_id', type: 'uuid')]
    public Uuid $assessmentId;

    #[ORM\Column(name: 'treatment_id', type: 'string')]
    public string $treatmentId;

    #[ORM\Column(name: 'timepoint', type: 'string')]
    public string $timepoint;

    #[ORM\Column(name: 'assessed_at', type: 'date_immutable')]
    public \DateTimeImmutable $assessedAt;

    #[ORM\Column(name: 'globales_Wohlbefinden_VAS', type: 'smallint')]
    public int $globalesWohlbefindenVas;
}
