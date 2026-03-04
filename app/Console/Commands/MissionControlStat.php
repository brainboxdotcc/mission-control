<?php

namespace App\Console\Commands;

use App\Models\VmLease;
use App\Models\VmSlot;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use JsonException;

final class MissionControlStat extends Command
{
    protected $signature = 'app:stat {--json : Output as JSON}';

    protected $description = 'Show VM slot and lease status summary.';

    /**
     * @throws JsonException
     */
    public function handle(): int
    {
        $now = Carbon::now();

        $slotCount = VmSlot::query()->count();
        $inUseCount = VmSlot::query()->where('in_use', true)->count();
        $freeCount = max(0, $slotCount - $inUseCount);

        $activeLeaseCount = VmLease::query()
            ->whereNull('ended_at')
            ->count();

        $activeSlots = VmSlot::query()
            ->where('in_use', true)
            ->orderBy('id')
            ->get();

        $rows = [];

        foreach ($activeSlots as $slot) {
            /** @var VmSlot $slot */
            $lease = null;

            if ($slot->current_lease_id !== null) {
                $lease = VmLease::query()->find($slot->current_lease_id);
            }

            $remaining = null;

            if ($lease !== null && $lease->hard_deadline_at !== null) {
                $remaining = (int) max(
                    0,
                    $lease->hard_deadline_at->getTimestamp() - $now->getTimestamp()
                );
            }

            $rows[] = [
                'slot' => $slot->slot_number ?? $slot->id,
                'lease_id' => $slot->current_lease_id,
                'remaining' => $remaining,
            ];
        }

        $payload = [
            'slots_total' => $slotCount,
            'slots_in_use' => $inUseCount,
            'slots_free' => $freeCount,
            'leases_active' => $activeLeaseCount,
            'active' => $rows,
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        }

        $this->line('Mission Control status');
        $this->line('----------------------');
        $this->line("Slots: {$slotCount} total, {$inUseCount} in use, {$freeCount} free");
        $this->line("Leases: {$activeLeaseCount} active");

        if (count($rows) === 0) {
            $this->line('No active slots.');
            return self::SUCCESS;
        }

        $this->table(
            ['slot', 'lease_id', 'remaining'],
            $rows
        );

        return self::SUCCESS;
    }
}
