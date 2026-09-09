<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Tests\Unit;

use AlexKasselAgents\JobApplicationAgent\Services\SessionBusManager;
use AlexKasselAgents\JobApplicationAgent\Tests\TestCase;

final class SessionBusManagerTest extends TestCase
{
    public function test_it_manages_application_session_and_bus_flow(): void
    {
        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'job_app_test_'.uniqid();
        mkdir($tempDir, 0755, true);

        try {
            $manager = new SessionBusManager;
            $session = $manager->createSession(
                appDir: $tempDir,
                company: 'Acme Corp',
                position: 'Lead Engineer',
                parentId: 'parent-1',
                writerId: 'writer-1',
                criticId: 'critic-1',
            );

            $this->assertSame('Acme Corp', $session->company);
            $this->assertSame('IN_PROGRESS', $session->status);
            $sessionFile = $tempDir.DIRECTORY_SEPARATOR.'session.json';
            $this->assertFileExists($sessionFile);

            // Step 1: Writer draft
            $step1 = $manager->recordStep(
                sessionPath: $sessionFile,
                stepName: '001_writer',
                content: 'First draft',
            );
            $this->assertSame('IN_PROGRESS', $step1->status);
            $this->assertStringContainsString('NEW_MAIL: 001_writer.md -> critic_id: critic-1', (string) $step1->signal);

            // Step 2: Critic approval
            $step2 = $manager->recordStep(
                sessionPath: $sessionFile,
                stepName: '002_critic',
                content: "Review: excellent\nSTATUS: APPROVED",
            );
            $this->assertStringContainsString('APPROVED - create final result', (string) $step2->signal);

            // Step 3: Writer finalization
            $step3 = $manager->recordStep(
                sessionPath: $sessionFile,
                stepName: '003_writer',
                content: 'Finalizing',
                explicitStatus: 'APPROVED',
                finalLetterContent: 'Clean Cover Letter Text',
                finalEmailContent: 'Clean Email Text',
            );
            $this->assertSame('COMPLETED', $step3->status);
            $this->assertStringContainsString('SIGNAL: DONE -> parent_id: parent-1', (string) $step3->signal);
            $this->assertFileExists($tempDir.DIRECTORY_SEPARATOR.'result'.DIRECTORY_SEPARATOR.'cover_letter_Acme_Corp.md');
            $this->assertFileExists($tempDir.DIRECTORY_SEPARATOR.'result'.DIRECTORY_SEPARATOR.'email_text.md');

            // Re-load session to check persisted completed state
            $loaded = $manager->loadSession($sessionFile);
            $this->assertSame('COMPLETED', $loaded->status);
            $this->assertNotNull($loaded->completedAt);
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
