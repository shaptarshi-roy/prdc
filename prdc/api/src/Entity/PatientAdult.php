<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'patients_adult')]
class PatientAdult
{
    #[ORM\Id]
    #[ORM\Column(name: 'patient_id', type: 'string')]
    public string $patientId;

    #[ORM\Column(name: 'Geb_jahr', type: 'smallint')]
    public int $gebJahr;

    #[ORM\Column(name: 'Sex', type: 'string')]
    public string $sex;
}
