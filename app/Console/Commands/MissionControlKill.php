<?php

namespace App\Console\Commands;

use App\Models\VmLease;
use App\Traits\TerminatesSessions;
use Illuminate\Console\Command;

final class MissionControlKill extends Command
{
    use TerminatesSessions;

    protected $signature = 'app:kill
        {leaseId : Lease id}
        {--reason=manual : End reason}
        {--force : Use SIGKILL}
        {--y : Skip confirmation}';

    protected $description = 'Terminate a VM lease by lease id.';

    public function handle(): int
    {
        $leaseId = (int) $this->argument('leaseId');
        $reason = (string) $this->option('reason');
        $force = (bool) $this->option('force');

        $lease = VmLease::query()->find($leaseId);

        if ($lease === null) {
            $this->error("Lease not found: {$leaseId}");
            return self::FAILURE;
        }

        if ($lease->ended_at !== null) {
            $this->line("Lease already ended: {$leaseId}");
            return self::SUCCESS;
        }

        if (!(bool) $this->option('y')) {
            if (!$this->confirm("Terminate lease {$leaseId}?")) {
                return self::SUCCESS;
            }
        }

        $this->terminateLease($lease, $reason, $force);

        $this->info("Terminated lease {$leaseId} ({$reason}).");

        return self::SUCCESS;
    }
}
