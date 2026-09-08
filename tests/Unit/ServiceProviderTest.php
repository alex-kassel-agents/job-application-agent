<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Tests\Unit;

use AlexKasselAgents\JobApplicationAgent\JobApplicationAgentServiceProvider;
use AlexKasselAgents\JobApplicationAgent\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_registers_service_provider_cleanly(): void
    {
        $provider = $this->app->getProvider(JobApplicationAgentServiceProvider::class);
        $this->assertInstanceOf(JobApplicationAgentServiceProvider::class, $provider);
    }
}
