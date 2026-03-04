<?php

namespace App\Http\Controllers;

use App\Models\VmLease;
use App\Models\VmSlot;
use App\Services\LeaseAllocator;
use App\Services\VmLauncher;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

final class TryController extends Controller
{
    public function index(): View
    {
        $slotsAvailable = VmSlot::query()
            ->where('in_use', false)
            ->exists();
        $slotsMax = VmSlot::count();

        return view('try.index', ['slots_available' => $slotsAvailable, 'slots_max' => $slotsMax]);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws LockTimeoutException
     */
    public function start(LeaseAllocator $allocator, VmLauncher $launcher): RedirectResponse
    {
        $session_id = session()->getId();

        // TTL: how long the lock can live if the request dies unexpectedly.
        $lock = Cache::lock('try_start|' . $session_id, 25);

        // If a second request arrives, wait up to 2 seconds for the first to finish.
        // If it doesn't, we return 429.
        if (!$lock->block(2)) {
            abort(429, 'Start already in progress.');
        }

        try {
            $result = $allocator->allocate();

            $lease = $result['lease'];
            $slot = $result['slot'];
            $token = $result['token'];

            $tokens = (array) session()->get('lease_tokens', []);
            $tokens[$lease->id] = $token;
            session()->put('lease_tokens', $tokens);

            $launcher->ensureDirsExist();

            $overlay_path = $launcher->createOverlay($lease);
            $pid = $launcher->startQemu($lease, $slot, $overlay_path);

            $lease->pid = $pid;
            $lease->overlay_path = $overlay_path;
            $lease->save();

            return redirect()->route('try.session', ['lease' => $lease->id]);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param VmLease&object{slot:VmSlot} $lease
     */
    public function session(VmLease $lease): View
    {
        $lease->loadMissing('slot');

        return view('try.session', [
            'slot' => $lease->slot->slot_index,
            'ws_path' => '/vnc/' . $lease->slot->slot_index,
            'lease_id' => $lease->id,
            'hard_deadline' => $lease->hard_deadline_at,
            'idle_deadline' => $lease->idle_deadline_at,
        ]);
    }
}
