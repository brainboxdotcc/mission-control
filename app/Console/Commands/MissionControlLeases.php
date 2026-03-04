<?php

namespace App\Console\Commands;

use App\Models\VmLease;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class MissionControlLeases extends Command
{
    protected $signature = 'app:leases {--all : Include ended leases} {--json : Output as JSON}';

    protected $description = 'List VM leases (active by default).';

    public function handle(): int
    {
        $now = Carbon::now();

        $query = VmLease::query()->orderByDesc('id');

        if (!(bool) $this->option('all')) {
            $query->whereNull('ended_at');
        }

        $leases = $query->limit(200)->get();

        $rows = [];

        foreach ($leases as $lease) {
            $remaining = null;

            if ($lease->ended_at === null && $lease->hard_deadline_at !== null) {
                $remaining = (int) max(
                    0,
                    $lease->hard_deadline_at->getTimestamp() - $now->getTimestamp()
                );
            }

            $rows[] = [
                'id' => $lease->id,
                'slot_id' => $lease->vm_slot_id,
                'pid' => $lease->pid,
                'remaining' => $remaining,
                'ended_at' => $lease->ended_at?->toIso8601String(),
                'reason' => $lease->end_reason,
            ];
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode($rows, JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        }

        $this->table(
            ['id', 'slot_id', 'pid', 'remaining', 'ended_at', 'reason'],
            $rows
        );

        return self::SUCCESS;
    }
}
