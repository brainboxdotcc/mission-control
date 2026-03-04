<?php

namespace Tests\Feature;

use App\Services\VmLauncher;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

final class TryStartLockContentionTest extends TestCase
{
    use RefreshDatabase;

    public function testStartReturns429WhenLockCannotBeAcquired(): void
    {
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('block')->once()->with(2)->andReturn(false);
        $lock->shouldReceive('release')->never();

        Cache::shouldReceive('lock')
            ->once()
            ->withArgs(function (string $name, int $seconds): bool {
                return str_starts_with($name, 'try_start|') && $seconds === 25;
            })
            ->andReturn($lock);

        $launcher = Mockery::mock(VmLauncher::class);
        $launcher->shouldNotReceive('ensureDirsExist');
        $launcher->shouldNotReceive('createOverlay');
        $launcher->shouldNotReceive('startQemu');
        $this->app->instance(VmLauncher::class, $launcher);

        $this->post(route('try.start'))
            ->assertStatus(429);
    }
}
