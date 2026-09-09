<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Services;

use AlexKasselAgents\JobApplicationAgent\Data\ApplicationSession;
use AlexKasselAgents\JobApplicationAgent\Data\BusStepResult;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

final class SessionBusManager
{
    public function createSession(
        string $appDir,
        string $company,
        string $position,
        ?string $parentId = null,
        ?string $writerId = null,
        ?string $criticId = null,
    ): ApplicationSession {
        if (! is_dir($appDir)) {
            mkdir($appDir, 0755, true);
        }

        $busDir = $appDir.DIRECTORY_SEPARATOR.'bus';
        $resultDir = $appDir.DIRECTORY_SEPARATOR.'result';

        if (! is_dir($busDir)) {
            mkdir($busDir, 0755, true);
        }
        if (! is_dir($resultDir)) {
            mkdir($resultDir, 0755, true);
        }

        $session = new ApplicationSession(
            applicationId: basename($appDir),
            company: $company,
            position: $position,
            status: 'IN_PROGRESS',
            busDir: 'bus',
            resultDir: 'result',
            parentId: $parentId,
            writerId: $writerId,
            criticId: $criticId,
        );

        $this->saveSession($appDir.DIRECTORY_SEPARATOR.'session.json', $session);

        return $session;
    }

    public function loadSession(string $sessionPath): ApplicationSession
    {
        if (! file_exists($sessionPath)) {
            throw new RuntimeException(sprintf('Session file not found: %s', $sessionPath));
        }

        $content = (string) file_get_contents($sessionPath);
        /** @var mixed $data */
        $data = json_decode($content, true);
        if (! is_array($data)) {
            throw new InvalidArgumentException(sprintf('Invalid JSON in session file: %s', $sessionPath));
        }

        /** @var array<string, mixed> $data */
        return ApplicationSession::fromArray($data);
    }

    public function saveSession(string $sessionPath, ApplicationSession $session): void
    {
        $encoded = json_encode($session->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents($sessionPath, (string) $encoded);
    }

    public function recordStep(
        string $sessionPath,
        string $stepName,
        string $content,
        ?string $explicitStatus = null,
        ?string $finalLetterContent = null,
        ?string $finalEmailContent = null,
    ): BusStepResult {
        $session = $this->loadSession($sessionPath);
        $appDir = dirname($sessionPath);
        $busDir = $appDir.DIRECTORY_SEPARATOR.$session->busDir;
        $resultDir = $appDir.DIRECTORY_SEPARATOR.$session->resultDir;

        if (! is_dir($busDir)) {
            mkdir($busDir, 0755, true);
        }
        if (! is_dir($resultDir)) {
            mkdir($resultDir, 0755, true);
        }

        $stepFilename = str_ends_with(strtolower($stepName), '.md') ? $stepName : ($stepName.'.md');
        $stepPath = $busDir.DIRECTORY_SEPARATOR.$stepFilename;

        file_put_contents($stepPath, trim($content)."\n");

        $isEscalated = $explicitStatus === 'REJECTED_CRITICAL' || str_contains($content, 'STATUS: REJECTED_CRITICAL');
        if ($isEscalated) {
            $session->status = 'ESCALATED';
            $this->saveSession($sessionPath, $session);

            return new BusStepResult(
                stepFilename: $stepFilename,
                stepPath: $stepPath,
                status: 'ESCALATED',
                signal: sprintf('SIGNAL: ESCALATE -> parent_id: %s', $session->parentId ?? 'orchestrator'),
                nextRecipientId: $session->parentId,
            );
        }

        $isCriticApproval = str_contains(strtolower($stepFilename), 'critic')
            && (str_contains($content, 'STATUS: APPROVED') || $explicitStatus === 'APPROVED');

        $isWriterFinal = ($explicitStatus === 'APPROVED' && ! str_contains(strtolower($stepFilename), 'critic'))
            || $finalLetterContent !== null;

        if ($isWriterFinal) {
            $letterText = $finalLetterContent ?? $content;
            $safeCompany = preg_replace('/[^\w\-]/u', '_', $session->company) ?? 'company';
            $finalLetterPath = $resultDir.DIRECTORY_SEPARATOR.sprintf('cover_letter_%s.md', $safeCompany);
            file_put_contents($finalLetterPath, trim($letterText)."\n");

            // Backward-compatible alias for German file searchers
            $deAlias = $resultDir.DIRECTORY_SEPARATOR.sprintf('Anschreiben_%s.md', $safeCompany);
            file_put_contents($deAlias, trim($letterText)."\n");

            $finalEmailPath = null;
            if ($finalEmailContent !== null) {
                $finalEmailPath = $resultDir.DIRECTORY_SEPARATOR.'email_text.md';
                file_put_contents($finalEmailPath, trim($finalEmailContent)."\n");
            }

            $session->status = 'COMPLETED';
            $session->completedAt = (new DateTimeImmutable)->format(DateTimeImmutable::ATOM);
            $this->saveSession($sessionPath, $session);

            return new BusStepResult(
                stepFilename: $stepFilename,
                stepPath: $stepPath,
                status: 'COMPLETED',
                signal: sprintf('SIGNAL: DONE -> parent_id: %s', $session->parentId ?? 'orchestrator'),
                nextRecipientId: $session->parentId,
                finalLetterPath: $finalLetterPath,
                finalEmailPath: $finalEmailPath,
            );
        }

        if (str_contains(strtolower($stepFilename), 'writer')) {
            $recipientId = $session->criticId ?? 'critic';
            $signal = sprintf('SIGNAL: NEW_MAIL: %s -> critic_id: %s', $stepFilename, $recipientId);
        } else {
            $recipientId = $session->writerId ?? 'writer';
            $note = $isCriticApproval ? ' (APPROVED - create final result)' : '';
            $signal = sprintf('SIGNAL: NEW_MAIL: %s%s -> writer_id: %s', $stepFilename, $note, $recipientId);
        }

        return new BusStepResult(
            stepFilename: $stepFilename,
            stepPath: $stepPath,
            status: 'IN_PROGRESS',
            signal: $signal,
            nextRecipientId: $recipientId,
        );
    }
}
