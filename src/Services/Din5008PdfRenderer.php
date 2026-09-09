<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Services;

use AlexKasselAgents\JobApplicationAgent\Data\CoverLetterData;
use AlexKasselAgents\JobApplicationAgent\Data\DensityTier;
use AlexKasselAgents\JobApplicationAgent\Data\RenderResult;
use RuntimeException;
use Symfony\Component\Process\Process;

final class Din5008PdfRenderer
{
    public function __construct(
        private readonly BrowserFinder $browserFinder,
        private readonly PdfPageCounter $pageCounter,
        private readonly ?string $defaultTemplatePath = null,
    ) {}

    public function render(
        CoverLetterData $data,
        string $outputPdfPath,
        ?DensityTier $forceTier = null,
        ?string $templatePath = null,
    ): RenderResult {
        $browser = $this->browserFinder->find();
        if ($browser === null) {
            throw new RuntimeException('No Chromium-compatible browser (Edge, Chrome, Brave, Chromium) found on system.');
        }

        $resolvedTemplatePath = $templatePath ?? $this->defaultTemplatePath ?? __DIR__.'/../../resources/templates/din5008_letter.html';
        if (! file_exists($resolvedTemplatePath)) {
            throw new RuntimeException(sprintf('DIN 5008 HTML template not found at: %s', $resolvedTemplatePath));
        }

        $template = (string) file_get_contents($resolvedTemplatePath);

        $outDir = dirname($outputPdfPath);
        if (! is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $realOutDir = realpath($outDir) ?: $outDir;
        $resolvedOutputPdfPath = rtrim($realOutDir, '/\\').DIRECTORY_SEPARATOR.basename($outputPdfPath);
        $tempHtmlPath = $resolvedOutputPdfPath.'.temp.html';

        $tiers = $forceTier !== null
            ? [$forceTier]
            : [DensityTier::Normal, DensityTier::Compact, DensityTier::UltraCompact];

        $bestTier = DensityTier::Normal;
        $bestPageCount = 999;
        $lastHtml = '';

        foreach ($tiers as $tier) {
            $bestTier = $tier;
            $html = $this->compileHtml($template, $data, $tier);
            $lastHtml = $html;

            file_put_contents($tempHtmlPath, $html);

            $this->executeHeadlessPrint($browser, $tempHtmlPath, $resolvedOutputPdfPath);

            if (! file_exists($resolvedOutputPdfPath) || (int) filesize($resolvedOutputPdfPath) < 500) {
                continue;
            }

            $pageCount = $this->pageCounter->countPages($resolvedOutputPdfPath);
            $bestPageCount = $pageCount;

            if ($pageCount === 1) {
                break;
            }
        }

        if (file_exists($tempHtmlPath)) {
            @unlink($tempHtmlPath);
        }

        if (! file_exists($resolvedOutputPdfPath) || (int) filesize($resolvedOutputPdfPath) < 500) {
            throw new RuntimeException(sprintf('Failed to render PDF to target path: %s', $resolvedOutputPdfPath));
        }

        return new RenderResult(
            pdfPath: $resolvedOutputPdfPath,
            pageCount: $bestPageCount,
            densityTier: $bestTier,
            isSinglePage: $bestPageCount === 1,
            htmlContent: $lastHtml,
        );
    }

    public function compileHtml(string $template, CoverLetterData $data, DensityTier $tier): string
    {
        $vars = $data->toTemplateVariables($tier);
        $html = $template;

        foreach ($vars as $key => $val) {
            $html = str_replace(sprintf('{{%s}}', $key), $val, $html);
        }

        return $html;
    }

    private function executeHeadlessPrint(string $browser, string $inputHtmlPath, string $outputPdfPath): void
    {
        $process = new Process([
            $browser,
            '--headless',
            '--disable-gpu',
            '--no-pdf-header-footer',
            '--run-all-compositor-stages-before-draw',
            sprintf('--print-to-pdf=%s', $outputPdfPath),
            $inputHtmlPath,
        ]);

        $process->setTimeout(60.0);
        $process->run();
    }
}
