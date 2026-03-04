<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TryIndexTest extends TestCase
{
    use RefreshDatabase;

    public function testTryIndexRenders(): void
    {
        $this->get(route('try.index'))
            ->assertOk()
            ->assertViewIs('try.index');
    }
}
