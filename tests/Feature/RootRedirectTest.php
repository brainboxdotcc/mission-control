<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RootRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function testRootRedirectsToTryIndex(): void
    {
        $this->get('/')
            ->assertRedirect(route('try.index'));
    }
}
