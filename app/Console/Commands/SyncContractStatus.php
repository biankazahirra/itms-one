<?php

namespace App\Console\Commands;

use App\Services\ContractStatusService;
use Illuminate\Console\Command;

class SyncContractStatus extends Command
{
    protected $signature = 'contracts:sync-status';

    protected $description = 'Reconcile contract status with the end date, keeping archived contracts';

    public function handle(ContractStatusService $status): int
    {
        $changed = $status->syncAll();

        $this->info("Status updated: {$changed}.");

        return self::SUCCESS;
    }
}
