<?php

namespace App\Console\Commands;

use App\Models\VmLease;
use App\Traits\TerminatesSessions;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class MissionControlReap extends Command
{
    use TerminatesSessions;

    protected $signature = 'app:reap';

    protected $description = 'Clean up expired VM leases and detect dead processes.';

    public function handle(): int
    {
        $now = Carbon::now();

        $leases = VmLease::query()
            ->whereNull('ended_at')
            ->where(function ($q) use ($now): void {
                $q->where('hard_deadline_at', '<', $now)
                    ->orWhere('idle_deadline_at', '<', $now);
            })
            ->get();

        foreach ($leases as $lease) {
            $this->terminateLease($lease, 'expired', true);
        }

        // Also detect dead PIDs
        $active = VmLease::query()
            ->whereNull('ended_at')
            ->whereNotNull('pid')
            ->get();

        foreach ($active as $lease) {
            if (!$this->pidExists($lease->pid)) {
                $this->terminateLease($lease, 'process_missing', true);
            }
        }

        return self::SUCCESS;
    }

    private function pidExists(?int $pid): bool
    {
        if ($pid === null || $pid <= 0) {
            return false;
        }

        return posix_kill($pid, 0);
    }
}
