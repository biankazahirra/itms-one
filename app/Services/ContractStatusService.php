<?php

namespace App\Services;

use App\Models\Contract;

class ContractStatusService
{
    /**
     * Compute the effective status from the end date, keeping Archived manual.
     */
    public function effectiveStatus(Contract $contract): string
    {
        return $contract->effectiveStatus();
    }

    /**
     * Persist the effective status without touching updated_at, so automatic
     * expiry does not look like a user edit.
     */
    public function sync(Contract $contract): bool
    {
        if ($contract->isArchived()) {
            return false;
        }

        $desired = $contract->effectiveStatus();

        if ($contract->status === $desired) {
            return false;
        }

        $timestamps = $contract->timestamps;
        $contract->timestamps = false;
        $contract->forceFill(['status' => $desired])->save();
        $contract->timestamps = $timestamps;

        return true;
    }

    /**
     * Reconcile every non-archived contract status with its end date.
     */
    public function syncAll(): int
    {
        $changed = 0;

        Contract::query()
            ->notArchived()
            ->chunkById(200, function ($contracts) use (&$changed): void {
                foreach ($contracts as $contract) {
                    if ($this->sync($contract)) {
                        $changed++;
                    }
                }
            });

        return $changed;
    }
}
