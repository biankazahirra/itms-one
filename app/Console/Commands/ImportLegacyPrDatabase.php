<?php

namespace App\Console\Commands;

use App\Support\Legacy\ImportReport;
use App\Support\Legacy\LegacyRowMapper;
use App\Support\Legacy\SqlDumpReader;
use Illuminate\Console\Command;

class ImportLegacyPrDatabase extends Command
{
    protected $signature = 'data:import-legacy-pr-db
        {--file= : Path to the legacy SQL dump}
        {--apply : Write imported rows to the application database}
        {--force : Repeat source keys already present in an earlier report}
        {--user= : User ID recorded as creator and updater in apply mode}
        {--backup-confirmed : Confirm that an itms_admin_db backup was taken}';

    protected $description = 'Import clean rows from the legacy PR database dump';

    public function __construct(
        private readonly SqlDumpReader $reader,
        private readonly LegacyRowMapper $mapper,
        private readonly ImportReport $report,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $file = (string) $this->option('file');

        if ($file === '' || ! is_file($file)) {
            $this->error('Provide an existing SQL dump with --file=<path>.');

            return self::FAILURE;
        }

        $mapped = $this->mapper->map($this->reader->read($file));
        $path = 'imports/legacy-pr-'.now()->format('YmdHis').'-'.uniqid().'.csv';

        $this->report->write($path, $mapped, []);

        $planned = array_sum(array_map(
            fn (string $key): int => $key === 'exceptions' ? 0 : count($mapped[$key]),
            array_keys($mapped),
        ));
        $skipped = count($mapped['exceptions']);

        $this->info("Planned: {$planned}, skipped: {$skipped}.");
        $this->info("Report path on local disk: {$path}");

        return self::SUCCESS;
    }
}
