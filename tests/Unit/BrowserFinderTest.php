<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Tests\Unit;

use AlexKasselAgents\JobApplicationAgent\Services\BrowserFinder;
use AlexKasselAgents\JobApplicationAgent\Tests\TestCase;

final class BrowserFinderTest extends TestCase
{
    public function test_it_returns_configured_binary_if_exists(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'browser_test_');
        $this->assertIsString($tempFile);

        try {
            $finder = new BrowserFinder($tempFile);
            $this->assertSame($tempFile, $finder->find());
        } finally {
            @unlink($tempFile);
        }
    }

    public function test_it_returns_null_when_nonexistent_binary_configured_and_no_browser(): void
    {
        $finder = new BrowserFinder('/non/existent/path/to/browser');
        // It should either find a real system browser or return null
        $result = $finder->find();
        if ($result !== null) {
            $this->assertFileExists($result);
        } else {
            $this->assertNull($result);
        }
    }
}
