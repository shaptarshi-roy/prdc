<?php
declare(strict_types=1);

namespace App\Validation;

/**
 * Buffers earlier rows of the current batch keyed by pseudonym columns,
 * so {@see App\Validation\Gate\IntraBatchGate} can check cross-row
 * consistency without holding the whole batch in memory.
 *
 * For MDS-PT episode batches this holds at most 3 rows per patient_id +
 * a handful per therapist_id and facility_id — i.e. essentially nothing.
 * Memory stays flat regardless of batch size.
 */
final class BatchContext
{
    /** @var array<string, list<array<string,string>>> */
    private array $byPatient = [];

    /** @var array<string, list<array<string,string>>> */
    private array $byTherapist = [];

    /** @var array<string, list<array<string,string>>> */
    private array $byFacility = [];

    public function record(array $row): void
    {
        $pid = $row['patient_id']   ?? '';
        $tid = $row['therapist_id'] ?? '';
        $fid = $row['facility_id']  ?? '';
        if ($pid !== '') $this->byPatient[$pid][]   = $row;
        if ($tid !== '') $this->byTherapist[$tid][] = $row;
        if ($fid !== '') $this->byFacility[$fid][]  = $row;
    }

    /** @return list<array<string,string>> */
    public function priorRowsForPatient(string $pid): array
    {
        return $this->byPatient[$pid] ?? [];
    }

    /** @return list<array<string,string>> */
    public function priorRowsForTherapist(string $tid): array
    {
        return $this->byTherapist[$tid] ?? [];
    }

    /** @return list<array<string,string>> */
    public function priorRowsForFacility(string $fid): array
    {
        return $this->byFacility[$fid] ?? [];
    }
}
