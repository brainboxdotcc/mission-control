<?php

namespace App\Traits;

use App\Models\VmLease;
use App\Models\VmSlot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait TerminatesSessions
{
    private function terminateLease(VmLease $lease, string $reason, bool $force): void
    {
        DB::transaction(function () use ($lease, $reason, $force): void {

            if ($lease->pid !== null) {
                $signal = $force ? 9 : 15;
                @posix_kill($lease->pid, $signal);
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
}
