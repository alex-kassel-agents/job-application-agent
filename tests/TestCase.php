<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Tests;

use AlexKasselAgents\JobApplicationAgent\JobApplicationAgentServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            JobApplicationAgentServiceProvider::class,
        ];
    }
}
