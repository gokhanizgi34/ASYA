<?php

namespace Tests\Unit;

use Tests\TestCase;

class DatabaseIsolationTest extends TestCase
{
    public function test_phpunit_always_uses_an_isolated_in_memory_database(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('array', config('cache.default'));
        $this->assertSame('sync', config('queue.default'));
    }
}
