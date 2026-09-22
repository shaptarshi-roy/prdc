<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'treatments')]
class Treatment
{
    #[ORM\Id]
    #[ORM\Column(name: 'treatment_id', type: 'string')]
    public string $treatmentId;

    #[ORM\Column(name: 'patient_id', type: 'string')]
    public string $patientId;

    #[ORM\Column(name: 'therapist_id', type: 'string')]
    public string $therapistId;

    #[ORM\Column(name: 'facility_id', type: 'string')]
    public string $facilityId;

    #[ORM\Column(name: 'Behandlungsbeginn', type: 'date_immutable')]
    public \DateTimeImmutable $beginn;

    #[ORM\Column(name: 'Behandlungsende_Datum', type: 'date_immutable')]
    public \DateTimeImmutable $ende;

    #[ORM\Column(name: 'batch_id', type: 'uuid')]
    public Uuid $batchId;
}
