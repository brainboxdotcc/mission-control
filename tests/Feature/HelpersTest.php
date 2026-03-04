<?php

namespace Tests\Feature;

use InvalidArgumentException;
use Tests\TestCase;

final class HelpersTest extends TestCase
{
    public function testAsIntConvertsCommonInputs(): void
    {
        $this->assertSame(0, asInt(null));
        $this->assertSame(0, asInt(''));
        $this->assertSame(12, asInt('12'));
        $this->assertSame(-3, asInt('-3'));
        $this->assertSame(9, asInt(9));
        $this->assertSame(7, asInt(7.9));
    }

    public function testAsIntRejectsNonScalarTypes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        asInt(['nope']);
    }

    public function testAsStringConvertsCommonInputs(): void
    {
        $this->assertSame('', asString(null));
        $this->assertSame('0', asString(0));
        $this->assertSame('12', asString(12));
        $this->assertSame('1.5', asString(1.5));
        $this->assertSame('hello', asString('hello'));
        $this->assertSame('0', asString(false));
        $this->assertSame('1', asString(true));
    }

    public function testAsStringRejectsNonScalarTypes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        asString((object) []);
    }
}
