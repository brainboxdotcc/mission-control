<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReleaseSessionRequest;
use App\Http\Requests\TouchSessionRequest;
use App\Models\VmLease;
use App\Models\VmSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

final class SessionApiController extends Controller
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function touch(TouchSessionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $token = $this->tokenForLease(asString($validated['lease_id']));
        if ($token === null) {
            return response()->json(['message' => 'Invalid session.'], 403);
        }

        $tokenHash = hash('sha256', $token);

        $lease = VmLease::query()
            ->where('id', $validated['lease_id'])
            ->where('token_hash', $tokenHash)
            ->first();

        if ($lease === null) {
            return response()->json(['message' => 'Invalid session.'], 403);
        }

        if (!$lease->is_active()) {
            return response()->json(['message' => 'Session ended.'], 410);
        }

        $now = Carbon::now();
        $idleSeconds = asInt(config('mission-control.limits.idle_seconds'));
        $mode = asString($validated['mode'] ?? 'input');

        $lease->last_activity_at = $now;

        if ($mode === 'input') {
            $lease->idle_deadline_at = $now->copy()->addSeconds($idleSeconds);
        }

        $lease->save();

        return response()->json([
            'ok' => true,
            'server_now' => $now->toIso8601String(),
            'remaining' => (int)max(0, $lease->hard_deadline_at->getTimestamp() - $now->getTimestamp()),
            'hard_limit' => asInt(config("mission-control.limits.hard_seconds")),
            'hard_deadline_at' => $lease->hard_deadline_at->toIso8601String(),
            'idle_deadline_at' => $lease->idle_deadline_at->toIso8601String(),
        ]);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function release(ReleaseSessionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $token = $this->tokenForLease(asString($validated['lease_id']));
        if ($token === null) {
            return response()->json(['message' => 'Invalid session.'], 403);
        }

        $tokenHash = hash('sha256', $token);

        return DB::transaction(function () use ($validated, $tokenHash): JsonResponse {
            $lease = VmLease::query()
                ->where('id', $validated['lease_id'])
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if ($lease === null) {
                return response()->json(['message' => 'Invalid session.'], 403);
            }

            if ($lease->ended_at !== null) {
                return response()->json(['ok' => true]);
            }

            $pid = $lease->pid;
            if (is_int($pid) && $pid > 0) {
                @posix_kill($pid, 9);

                // Give it a moment to exit cleanly
                for ($i = 0; $i < 20; $i++) {
                    if (!@posix_kill($pid, 0)) {
                        break;
                    }

                    usleep(50000);
                }

                // Still running? Kill it.
                if (@posix_kill($pid, 0)) {
                    @posix_kill($pid, 15);
                }
            }

            $overlayPath = $lease->overlay_path;
            if (is_string($overlayPath) && $overlayPath !== '' && is_file($overlayPath)) {
                @unlink($overlayPath);
            }

            $lease->ended_at = Carbon::now();
            $lease->end_reason = 'manual';
            $lease->save();

            VmSlot::query()
                ->where('id', $lease->vm_slot_id)
                ->update([
                    'in_use' => false,
                    'current_lease_id' => null,
                ]);

            return response()->json(['ok' => true]);
        });
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function tokenForLease(string $leaseId): ?string
    {
        $tokens = (array) session()->get('lease_tokens', []);

        $token = $tokens[$leaseId] ?? null;
        if (!is_string($token) || $token === '') {
            return null;
        }

        return $token;
    }
}
