<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Lightweight mapping — only the columns the API serves over GET are
 * listed here. Write-side inserts go through raw DBAL in the pipeline,
 * so we don't need every CSV column reflected as a PHP property.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'facilities')]
class Facility
{
    #[ORM\Id]
    #[ORM\Column(name: 'facility_id', type: 'string')]
    public string $facilityId;

    #[ORM\Column(name: 'plz_einrichtung', type: 'integer')]
    public int $plz;

    #[ORM\Column(name: 'art_und_setting_einrichtung', type: 'string')]
    public string $setting;

    #[ORM\Column(name: 'traeger', type: 'string')]
    public string $traeger;

    #[ORM\Column(name: 'groesse_einzugsgebiet', type: 'integer', nullable: true)]
    public ?int $groesseEinzugsgebiet = null;
}
