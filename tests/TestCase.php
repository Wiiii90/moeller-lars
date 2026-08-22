<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $testingEnvironmentPath = dirname(__DIR__).'/.env.testing';

        if (! is_file($testingEnvironmentPath)) {
            file_put_contents($testingEnvironmentPath, '');
        }

        parent::setUp();

        $this->withoutVite();
    }
}
