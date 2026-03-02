<?php

namespace App\Console\Commands;

use App\Models\VmLease;
use App\Models\VmSlot;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class MissionControlReap extends Command
{
    protected $signature = 'mission-control:reap';

    protected $description = 'Reap expired or dead VM leases.';

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
            $this->terminateLease($lease, 'expired');
        }

        // Also detect dead PIDs
        $active = VmLease::query()
            ->whereNull('ended_at')
            ->whereNotNull('pid')
            ->get();

        foreach ($active as $lease) {
            if (!$this->pidExists($lease->pid)) {
                $this->terminateLease($lease, 'process_missing');
            }
        }

        return self::SUCCESS;
    }

    private function terminateLease(VmLease $lease, string $reason): void
    {
        DB::transaction(function () use ($lease, $reason): void {
            if ($lease->pid !== null) {
                @posix_kill($lease->pid, 15);
            }

            if ($lease->overlay_path !== null && is_file($lease->overlay_path)) {
                @unlink($lease->overlay_path);
            }

            $lease->ended_at = Carbon::now();
            $lease->end_reason = $reason;
            $lease->save();

            VmSlot::query()
                ->where('id', $lease->vm_slot_id)
                ->update([
                    'in_use' => false,
                    'current_lease_id' => null,
                ]);
        });
    }

    private function pidExists(?int $pid): bool
    {
        if ($pid === null || $pid <= 0) {
            return false;
        }

        return posix_kill($pid, 0);
    }
}
