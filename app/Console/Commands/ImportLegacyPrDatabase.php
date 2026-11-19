<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PurchaseRequestService;
use App\Support\Legacy\ImportReport;
use App\Support\Legacy\LegacyRowMapper;
use App\Support\Legacy\SqlDumpReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
        private readonly PurchaseRequestService $purchaseRequests,
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
        $imported = [];

        if ($this->option('apply')) {
            $user = $this->importUser();

            if ($user === null) {
                return self::FAILURE;
            }

            $seen = $this->option('force') ? [] : $this->seenSourceKeys();
            $imported = $this->apply($mapped, $user, $seen);
        }

        $path = 'imports/legacy-pr-'.now()->format('YmdHis').'-'.uniqid().'.csv';
        $this->report->write($path, $mapped, $imported);

        $planned = array_sum(array_map(
            fn (string $key): int => $key === 'exceptions' ? 0 : count($mapped[$key]),
            array_keys($mapped),
        ));
        $skipped = count($mapped['exceptions']);

        $this->info('Mapped: '.$planned.', skipped: '.$skipped.', imported: '.count($imported).'.');
        $this->info('Report path on local disk: '.$path);

        return self::SUCCESS;
    }

    private function importUser(): ?User
    {
        if (! $this->option('backup-confirmed')) {
            $this->error('Take an itms_admin_db backup first, then pass --backup-confirmed.');

            return null;
        }

        $id = $this->option('user');

        if (! is_numeric($id)) {
            $this->error('Pass the importing admin user ID with --user=<id>.');

            return null;
        }

        return User::findOrFail((int) $id);
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, bool>  $seen
     * @return array<string, array{id: int, number: string}>
     */
    private function apply(array &$mapped, User $user, array $seen): array
    {
        $imported = [];

        foreach ($mapped['departments'] as $name) {
            $key = 'departments:'.$name;

            if (isset($seen[$key])) {
                continue;
            }

            $department = DB::transaction(fn () => Department::firstOrCreate(
                ['name' => $name],
                ['is_active' => true],
            ));
            $imported[$key] = ['id' => $department->id, 'number' => $name];
        }

        foreach ($mapped['vendors'] as $vendor) {
            $sourceId = implode('|', $vendor['legacy_ids']);
            $key = 'vendors:'.$sourceId;

            if (isset($seen[$key])) {
                continue;
            }

            $created = DB::transaction(fn () => Vendor::firstOrCreate(
                ['company_name' => $vendor['company_name']],
                ['company_address' => '', 'is_active' => true],
            ));
            $imported[$key] = ['id' => $created->id, 'number' => $vendor['company_name']];
        }

        foreach ($mapped['purchase_requests'] as $row) {
            $key = 'purchase_request:'.$row['source_id'];

            if (isset($seen[$key])) {
                continue;
            }

            try {
                $department = Department::where('name', $row['department'])->firstOrFail();
                $pr = $this->purchaseRequests->create([
                    'recipient_department_id' => $department->id,
                    'subject' => $row['subject'],
                    'pr_date' => $row['pr_date'],
                    'category' => $row['category'],
                    'items' => $row['items'],
                ], $user);

                $imported[$key] = ['id' => $pr->id, 'number' => $pr->pr_number];
            } catch (ValidationException $exception) {
                $mapped['exceptions'][] = [
                    'table' => 'purchase_request',
                    'source_id' => $row['source_id'],
                    'reason' => implode(' ', array_merge(...array_values($exception->errors()))),
                ];
            }
        }

        foreach (['contracts' => 'kontrak', 'lhstps' => 'lhstp'] as $section => $table) {
            foreach ($mapped[$section] as $row) {
                $mapped['exceptions'][] = [
                    'table' => $table,
                    'source_id' => $row['source_id'],
                    'reason' => 'Payment rules unavailable for imported contracts',
                ];
            }
        }

        return $imported;
    }

    /**
     * @return array<string, bool>
     */
    private function seenSourceKeys(): array
    {
        $files = Storage::disk('local')->files('imports');

        if ($files === []) {
            return [];
        }

        sort($files);
        $latest = end($files);
        $lines = explode("\n", (string) Storage::disk('local')->get($latest));
        $keys = [];

        foreach (array_slice($lines, 1) as $line) {
            $columns = str_getcsv($line);

            if (($columns[2] ?? null) === 'imported') {
                $keys[$columns[0].':'.$columns[1]] = true;
            }
        }

        return $keys;
    }
}
