<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Tests\Feature;

use AlexKasselAgents\JobApplicationAgent\Tests\TestCase;

final class SessionBusCommandsTest extends TestCase
{
    public function test_it_executes_session_lifecycle_via_artisan(): void
    {
        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'job_cmd_test_'.uniqid();

        try {
            $this->artisan('job:session:create', [
                'company' => 'Beta GmbH',
                'position' => 'Backend Specialist',
                '--dir' => $tempDir,
                '--parent-id' => 'orchestrator-1',
                '--writer-id' => 'writer-1',
                '--critic-id' => 'critic-1',
            ])->assertExitCode(0);

            $sessionFile = $tempDir.DIRECTORY_SEPARATOR.'session.json';
            $this->assertFileExists($sessionFile);

            $this->artisan('job:bus:step', [
                'session' => $sessionFile,
                'step' => '001_writer',
                '--content' => 'First draft content',
            ])->assertExitCode(0);

            $stepFile = $tempDir.DIRECTORY_SEPARATOR.'bus'.DIRECTORY_SEPARATOR.'001_writer.md';
            $this->assertFileExists($stepFile);
            $this->assertStringContainsString('First draft content', (string) file_get_contents($stepFile));
        } finally {
            $this->recursiveDelete($tempDir);
        }
    }

    private function recursiveDelete(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
