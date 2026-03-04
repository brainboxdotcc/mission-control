<?php

namespace App\Console\Commands;

use App\Models\VmSlot;
use Illuminate\Console\Command;

final class MissionControlSlotsSet extends Command
{
    protected $signature = 'app:slots:set {count : Total slots to ensure (grow-only)}';

    protected $description = 'Ensure vm_slots contains slots 1..count (does not delete existing).';

    public function handle(): int
    {
        $count = (int) $this->argument('count');

        if ($count <= 0) {
            $this->error('Count must be greater than zero.');
            return self::FAILURE;
        }

        $maxExisting = asInt(VmSlot::query()->max('slot_index'));

        if ($count < $maxExisting) {
            $this->error("Refusing to shrink slots from {$maxExisting} to {$count}.");
            return self::FAILURE;
        }

        $created = 0;

        for ($n = 1; $n <= $count; $n++) {

            $exists = VmSlot::query()
                ->where('slot_index', $n)
                ->exists();

            if ($exists) {
                continue;
            }

            $slot = new VmSlot();
            $slot->slot_index = $n;
            $slot->in_use = false;
            $slot->current_lease_id = null;
            $slot->save();

            $created++;
        }

        $this->info("Ensured slots 1..{$count}. Created {$created} new slot(s).");

        return self::SUCCESS;
    }
}
