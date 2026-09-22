<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'therapists')]
class Therapist
{
    #[ORM\Id]
    #[ORM\Column(name: 'therapist_id', type: 'string')]
    public string $therapistId;

    #[ORM\Column(name: 'facility_id', type: 'string')]
    public string $facilityId;

    #[ORM\Column(name: 'Geburtsjahr_T', type: 'smallint')]
    public int $geburtsjahr;

    #[ORM\Column(name: 'Geschlecht_T', type: 'string')]
    public string $geschlecht;
}
