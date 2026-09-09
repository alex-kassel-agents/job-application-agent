<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Commands;

use AlexKasselAgents\JobApplicationAgent\Services\SessionBusManager;
use Illuminate\Console\Command;
use Throwable;

final class SessionBusStepCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'job:bus:step
                            {session : Path to session.json}
                            {step : Step name (e.g. 001_writer, 002_critic)}
                            {--file= : Path to input file with step content}
                            {--content= : Direct text content of the step}
                            {--status= : Explicit step status (IN_PROGRESS, APPROVED, REJECTED_CRITICAL)}
                            {--final-letter= : Path to final cover letter markdown}
                            {--final-email= : Path to final email markdown}';

    /**
     * @var string
     */
    protected $description = 'Atomically record a multi-agent consensus bus step and update application session';

    public function handle(SessionBusManager $busManager): int
    {
        $rawSession = $this->argument('session');
        $sessionArg = is_string($rawSession) ? $rawSession : '';
        $sessionPath = $sessionArg !== '' ? (realpath($sessionArg) ?: $sessionArg) : '';

        if ($sessionPath === '' || ! file_exists($sessionPath)) {
            $this->error(sprintf('Session file not found: %s', $sessionArg));

            return self::FAILURE;
        }

        $stepName = is_string($this->argument('step')) ? (string) $this->argument('step') : '';
        if ($stepName === '') {
            $this->error('Step name must be specified.');

            return self::FAILURE;
        }

        $content = '';
        $fileOpt = $this->option('file');
        if (is_string($fileOpt) && $fileOpt !== '') {
            $filePath = realpath($fileOpt) ?: $fileOpt;
            if (file_exists($filePath)) {
                $content = (string) file_get_contents($filePath);
            }
        }

        if ($content === '') {
            $contentOpt = $this->option('content');
            if (is_string($contentOpt) && $contentOpt !== '') {
                $content = $contentOpt;
            }
        }

        if (trim($content) === '') {
            $this->error('Step content is empty. Provide --file or --content.');

            return self::FAILURE;
        }

        $statusOpt = $this->option('status');
        $status = is_string($statusOpt) && $statusOpt !== '' ? $statusOpt : null;

        $finalLetter = null;
        $finalLetterOpt = $this->option('final-letter');
        if (is_string($finalLetterOpt) && $finalLetterOpt !== '') {
            $flPath = realpath($finalLetterOpt) ?: $finalLetterOpt;
            if (file_exists($flPath)) {
                $finalLetter = (string) file_get_contents($flPath);
            }
        }

        $finalEmail = null;
        $finalEmailOpt = $this->option('final-email');
        if (is_string($finalEmailOpt) && $finalEmailOpt !== '') {
            $fePath = realpath($finalEmailOpt) ?: $finalEmailOpt;
            if (file_exists($fePath)) {
                $finalEmail = (string) file_get_contents($fePath);
            }
        }

        try {
            $result = $busManager->recordStep(
                sessionPath: $sessionPath,
                stepName: $stepName,
                content: $content,
                explicitStatus: $status,
                finalLetterContent: $finalLetter,
                finalEmailContent: $finalEmail,
            );
        } catch (Throwable $e) {
            $this->error(sprintf('Failed to record bus step: %s', $e->getMessage()));

            return self::FAILURE;
        }

        $this->info(sprintf('  <info>[OK]</info> Saved bus step: %s', $result->stepFilename));
        if ($result->finalLetterPath !== null) {
            $this->line(sprintf('  <comment>[OK]</comment> Saved final letter: %s', basename($result->finalLetterPath)));
        }
        if ($result->finalEmailPath !== null) {
            $this->line(sprintf('  <comment>[OK]</comment> Saved final email: %s', basename($result->finalEmailPath)));
        }
        if ($result->signal !== null) {
            $this->line($result->signal);
        }

        return self::SUCCESS;
    }
}
