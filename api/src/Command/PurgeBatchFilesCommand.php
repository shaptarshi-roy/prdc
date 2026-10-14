<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\BatchLog;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Purge raw CSV files for batches in terminal state past retention window.
 *
 * The DB row stays — `batch_log` is the audit trail and never gets
 * deleted. Only the raw CSV file on the `batches` volume is removed.
 *
 * Run via cron, daily. Idempotent: a second run on the same day finds
 * nothing because the files are already gone.
 */
#[AsCommand(
    name: 'app:purge-batch-files',
    description: 'Delete raw CSV files for batches in terminal state past retention window.',
)]
final class PurgeBatchFilesCommand extends Command
{
    private const TERMINAL_STATES = [
        BatchLog::STATUS_COMPLETED,
        BatchLog::STATUS_PARTIAL,
        BatchLog::STATUS_BLOCKED,
        BatchLog::STATUS_FAILED,
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly string $storagePath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'retention-days',
                null,
                InputOption::VALUE_REQUIRED,
                'Files older than this (in days, by completed_at) are purged.',
                '30',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Log what would be deleted without actually deleting.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $retention = (int) $input->getOption('retention-days');
        $dryRun    = (bool) $input->getOption('dry-run');

        if ($retention < 0) {
            $io->error('retention-days must be >= 0');
            return Command::INVALID;
        }

        $cutoff = new \DateTimeImmutable("-{$retention} days");

        $placeholders = implode(', ', array_fill(0, count(self::TERMINAL_STATES), '?'));
        $sql = "SELECT batch_id, status, completed_at
                  FROM batch_log
                 WHERE status IN ($placeholders)
                   AND completed_at IS NOT NULL
                   AND completed_at < ?
              ORDER BY completed_at";

        $params = [...self::TERMINAL_STATES, $cutoff->format('c')];
        $rows = $this->db->fetchAllAssociative($sql, $params);

        if ($rows === []) {
            $io->success(sprintf(
                'No batches eligible for purge (retention %dd, cutoff %s).',
                $retention, $cutoff->format('Y-m-d H:i'),
            ));
            return Command::SUCCESS;
        }

        $io->info(sprintf(
            '%d batch%s eligible (retention %dd, cutoff %s).%s',
            count($rows),
            count($rows) === 1 ? '' : 'es',
            $retention,
            $cutoff->format('Y-m-d H:i'),
            $dryRun ? ' [DRY-RUN]' : '',
        ));

        $deleted = 0;
        $missing = 0;
        $errors  = 0;

        foreach ($rows as $row) {
            $path = sprintf('%s/raw/%s.csv', $this->storagePath, $row['batch_id']);

            if (!file_exists($path)) {
                $missing++;
                continue;
            }

            if ($dryRun) {
                $io->writeln(sprintf('  would delete: %s', $path));
                $deleted++;
                continue;
            }

            if (@unlink($path)) {
                $deleted++;
            } else {
                $errors++;
                $err = error_get_last();
                $io->warning(sprintf(
                    'failed to delete %s: %s',
                    $path,
                    $err['message'] ?? 'unknown',
                ));
            }
        }

        $io->success(sprintf(
            'Done. deleted=%d missing=%d errors=%d',
            $deleted, $missing, $errors,
        ));

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
