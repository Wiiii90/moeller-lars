<?php

namespace Tests;

use App\Support\LocalPreviewDatabaseGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        LocalPreviewDatabaseGuard::assertDisposableTestContext();

        parent::setUp();

        $this->withoutVite();
    }
}
