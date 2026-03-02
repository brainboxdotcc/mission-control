<?php

namespace App\Services;

use App\Models\VmLease;
use App\Models\VmSlot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class LeaseAllocator
{
    /**
     * @return array{lease:VmLease, token:string, slot:VmSlot}
     */
    public function allocate(): array
    {
        $lock = Cache::lock('vm_slots_allocate', 10);

        return $lock->block(5, function (): array {
            $slot = VmSlot::query()
                ->where('in_use', false)
                ->orderBy('slot_index')
                ->first();

            if ($slot === null) {
                abort(429, 'All demo slots are busy. Try again shortly.');
            }

            $token = Str::random(64);
            $token_hash = hash('sha256', $token);

            $now = Carbon::now();
            $hard_seconds = (int) config('mission-control.limits.hard_seconds');
            $idle_seconds = (int) config('mission-control.limits.idle_seconds');

            $lease = new VmLease();
            $lease->id = (string) Str::uuid();
            $lease->vm_slot_id = $slot->id;
            $lease->token_hash = $token_hash;
            $lease->started_at = $now;
            $lease->last_activity_at = $now;
            $lease->hard_deadline_at = $now->copy()->addSeconds($hard_seconds);
            $lease->idle_deadline_at = $now->copy()->addSeconds($idle_seconds);
            $lease->save();

            $slot->in_use = true;
            $slot->current_lease_id = $lease->id;
            $slot->save();

            return ['lease' => $lease, 'token' => $token, 'slot' => $slot];
        });
    }
}
