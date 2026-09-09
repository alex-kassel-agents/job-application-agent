<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Commands;

use AlexKasselAgents\JobApplicationAgent\Services\SessionBusManager;
use DateTimeImmutable;
use Illuminate\Console\Command;

final class CreateSessionCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'job:session:create
                            {company : Employer company name}
                            {position : Targeted job title}
                            {--dir= : Target folder path (defaults to auto-generated under applications_path)}
                            {--parent-id= : Orchestrator conversation ID}
                            {--writer-id= : Writer subagent conversation ID}
                            {--critic-id= : Critic subagent conversation ID}';

    /**
     * @var string
     */
    protected $description = 'Initialize a new job application folder with bus/ and session.json';

    public function handle(SessionBusManager $busManager): int
    {
        $company = is_string($this->argument('company')) ? (string) $this->argument('company') : '';
        $position = is_string($this->argument('position')) ? (string) $this->argument('position') : '';

        $dirOpt = $this->option('dir');
        $targetDir = is_string($dirOpt) && $dirOpt !== '' ? $dirOpt : null;

        if ($targetDir === null) {
            /** @var string|null $baseApps */
            $baseApps = config('job-application-agent.applications_path');
            $baseDir = $baseApps ?? base_path('applications');
            $timestamp = (new DateTimeImmutable)->format('Y-m-d_H-i');
            $safeCompany = preg_replace('/[^\w\-]/u', '_', $company) ?? 'company';
            $safePosition = preg_replace('/[^\w\-]/u', '_', $position) ?? 'position';
            $targetDir = sprintf('%s/%s_%s_%s', $baseDir, $timestamp, $safeCompany, $safePosition);
        }

        $parentIdOpt = $this->option('parent-id');
        $parentId = is_string($parentIdOpt) && $parentIdOpt !== '' ? $parentIdOpt : null;

        $writerIdOpt = $this->option('writer-id');
        $writerId = is_string($writerIdOpt) && $writerIdOpt !== '' ? $writerIdOpt : null;

        $criticIdOpt = $this->option('critic-id');
        $criticId = is_string($criticIdOpt) && $criticIdOpt !== '' ? $criticIdOpt : null;

        $session = $busManager->createSession(
            appDir: $targetDir,
            company: $company,
            position: $position,
            parentId: $parentId,
            writerId: $writerId,
            criticId: $criticId,
        );

        $this->info(sprintf('  <info>[OK]</info> Application session initialized at: %s', $targetDir));
        $this->line(sprintf('  Application ID: <comment>%s</comment>', $session->applicationId));

        return self::SUCCESS;
    }
}
