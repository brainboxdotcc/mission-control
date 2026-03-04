<?php

namespace Tests\Feature;

use App\Models\VmLease;
use App\Models\VmSlot;
use App\Services\VmLauncher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class TryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testStartAllocatesLeaseAndRedirects(): void
    {
        VmSlot::query()->create([
            'slot_index' => 1,
            'in_use' => false,
            'current_lease_id' => null,
            'bind_host' => '127.0.0.1',
            'display' => 1,
            'ws_port' => 5701,
        ]);

        $launcher = Mockery::mock(VmLauncher::class);
        $launcher->shouldReceive('ensureDirsExist')->once();
        $launcher->shouldReceive('createOverlay')->once()->andReturn('/tmp/overlay.qcow2');
        $launcher->shouldReceive('startQemu')->once()->andReturn(12345);
        $this->app->instance(VmLauncher::class, $launcher);

        $response = $this->post(route('try.start'));

        $lease = VmLease::query()->first();
        $this->assertNotNull($lease);

        $response->assertRedirect(route('try.session', ['lease' => $lease->id]));

        $lease->refresh();
        $this->assertSame(12345, $lease->pid);
        $this->assertSame('/tmp/overlay.qcow2', $lease->overlay_path);

        $slot = VmSlot::query()->where('slot_index', 1)->first();
        $this->assertNotNull($slot);
        $this->assertTrue((bool) $slot->in_use);
        $this->assertSame($lease->id, $slot->current_lease_id);

        $tokens = (array) session()->get('lease_tokens', []);
        $this->assertArrayHasKey($lease->id, $tokens);
        $this->assertIsString($tokens[$lease->id]);
        $this->assertNotSame('', $tokens[$lease->id]);
    }
}
