<?php

namespace App\Console\Commands;

use App\Models\VmLease;
use App\Models\VmSlot;
use App\Traits\TerminatesSessions;
use Illuminate\Console\Command;

final class MissionControlSlotKill extends Command
{
    use TerminatesSessions;

    protected $signature = 'app:slotkill
        {slot : Slot number or id}
        {--reason=manual : End reason}
        {--force : Use SIGKILL}
        {--y : Skip confirmation}';

    protected $description = 'Terminate the active lease in a slot.';

    public function handle(): int
    {
        $slotArg = (int) $this->argument('slot');
        $reason = (string) $this->option('reason');
        $force = (bool) $this->option('force');

        $slot = VmSlot::query()
            ->where('slot_number', $slotArg)
            ->orWhere('id', $slotArg)
            ->first();

        if ($slot === null) {
            $this->error("Slot not found: {$slotArg}");
            return self::FAILURE;
        }

        $lease = null;

        if ($slot->current_lease_id !== null) {
            $lease = VmLease::query()->find($slot->current_lease_id);
        }

        if ($lease === null) {
            $lease = VmLease::query()
                ->where('vm_slot_id', $slot->id)
                ->whereNull('ended_at')
                ->orderByDesc('id')
                ->first();
        }

        if ($lease === null) {
            $this->line('No active lease found.');
            return self::SUCCESS;
        }

        if (!(bool) $this->option('y')) {
            if (!$this->confirm("Terminate lease {$lease->id}?")) {
                return self::SUCCESS;
            }
        }

        $this->terminateLease($lease, $reason, $force);

        $this->info("Terminated lease {$lease->id}.");

        return self::SUCCESS;
    }
}
