<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent;

use AlexKasselAgents\JobApplicationAgent\Commands\CreateSessionCommand;
use AlexKasselAgents\JobApplicationAgent\Commands\RenderCoverLetterCommand;
use AlexKasselAgents\JobApplicationAgent\Commands\SessionBusStepCommand;
use AlexKasselAgents\JobApplicationAgent\Services\BrowserFinder;
use AlexKasselAgents\JobApplicationAgent\Services\CoverLetterParser;
use AlexKasselAgents\JobApplicationAgent\Services\Din5008PdfRenderer;
use AlexKasselAgents\JobApplicationAgent\Services\PdfPageCounter;
use AlexKasselAgents\JobApplicationAgent\Services\SessionBusManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class JobApplicationAgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/job-application-agent.php', 'job-application-agent');

        $this->app->singleton(BrowserFinder::class, static function (Application $app): BrowserFinder {
            /** @var Repository $config */
            $config = $app->make('config');
            /** @var string|null $configuredBinary */
            $configuredBinary = $config->get('job-application-agent.browser_binary');

            return new BrowserFinder($configuredBinary);
        });

        $this->app->singleton(PdfPageCounter::class, static fn (): PdfPageCounter => new PdfPageCounter);

        $this->app->singleton(CoverLetterParser::class, static function (Application $app): CoverLetterParser {
            /** @var Repository $config */
            $config = $app->make('config');
            /** @var string $defaultCity */
            $defaultCity = $config->get('job-application-agent.din5008.default_city', 'Berlin');
            /** @var string $defaultSignoff */
            $defaultSignoff = $config->get('job-application-agent.din5008.default_signoff', 'Mit freundlichen Grüßen');

            return new CoverLetterParser(
                defaultCity: $defaultCity,
                defaultSignoff: $defaultSignoff,
            );
        });

        $this->app->singleton(Din5008PdfRenderer::class, static function (Application $app): Din5008PdfRenderer {
            /** @var Repository $config */
            $config = $app->make('config');
            /** @var BrowserFinder $browserFinder */
            $browserFinder = $app->make(BrowserFinder::class);
            /** @var PdfPageCounter $pageCounter */
            $pageCounter = $app->make(PdfPageCounter::class);
            /** @var string|null $templatesPath */
            $templatesPath = $config->get('job-application-agent.templates_path');
            $templateFile = $templatesPath !== null
                ? rtrim($templatesPath, '/\\').'/din5008_letter.html'
                : __DIR__.'/../resources/templates/din5008_letter.html';

            return new Din5008PdfRenderer(
                browserFinder: $browserFinder,
                pageCounter: $pageCounter,
                defaultTemplatePath: $templateFile,
            );
        });

        $this->app->singleton(SessionBusManager::class, static fn (): SessionBusManager => new SessionBusManager);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/job-application-agent.php' => $this->app->configPath('job-application-agent.php'),
            ], 'job-application-config');

            $this->publishes([
                __DIR__.'/../resources/templates' => $this->app->resourcePath('job-application/templates'),
            ], 'job-application-templates');

            $this->commands([
                RenderCoverLetterCommand::class,
                CreateSessionCommand::class,
                SessionBusStepCommand::class,
            ]);
        }
    }
}
