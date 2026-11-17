<?php

namespace App\Console\Commands;

use App\Services\RecycleBinService;
use Illuminate\Console\Command;

class PurgeRecycleBin extends Command
{
    protected $signature = 'recycle-bin:purge';

    protected $description = 'Purge expired recycle bin entries when dependencies allow it';

    public function handle(RecycleBinService $recycleBin): int
    {
        $result = $recycleBin->purgeExpired();

        $this->info("Purged: {$result['purged']}, blocked: {$result['blocked']}.");

        return self::SUCCESS;
    }
}
