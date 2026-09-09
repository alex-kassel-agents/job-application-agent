<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Commands;

use AlexKasselAgents\JobApplicationAgent\Data\DensityTier;
use AlexKasselAgents\JobApplicationAgent\Services\AnschreibenParser;
use AlexKasselAgents\JobApplicationAgent\Services\Din5008PdfRenderer;
use Illuminate\Console\Command;
use Throwable;

final class RenderAnschreibenCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'job:render
                            {input : Path to JSON or Markdown Anschreiben file}
                            {--output= : Target PDF output path}
                            {--density= : Force density tier (normal, compact, ultra-compact)}
                            {--keep-companion-md : Generate or update companion Markdown when input is JSON}';

    /**
     * @var string
     */
    protected $description = 'Render a DIN 5008 compliant single-page PDF cover letter from JSON or Markdown';

    public function handle(AnschreibenParser $parser, Din5008PdfRenderer $renderer): int
    {
        $rawInput = $this->argument('input');
        $inputArg = is_string($rawInput) ? $rawInput : '';
        $inputPath = realpath($inputArg) ?: $inputArg;

        if ($inputArg === '' || ! file_exists($inputPath)) {
            $this->error(sprintf('Input file not found: %s', $inputArg));

            return self::FAILURE;
        }

        $rawOutput = $this->option('output');
        $outputPath = is_string($rawOutput) ? $rawOutput : '';
        if ($outputPath === '') {
            $pathInfo = pathinfo($inputPath);
            $dir = $pathInfo['dirname'] ?? '.';
            $outputPath = sprintf('%s/%s.pdf', $dir, $pathInfo['filename']);
        }

        $rawDensity = $this->option('density');
        $densityOption = is_string($rawDensity) ? $rawDensity : '';
        $forceTier = null;
        if ($densityOption !== '') {
            $forceTier = match (strtolower($densityOption)) {
                'compact' => DensityTier::Compact,
                'ultra-compact', 'ultracompact' => DensityTier::UltraCompact,
                'normal' => DensityTier::Normal,
                default => null,
            };

            if ($forceTier === null) {
                $this->warn(sprintf('Unknown density tier "%s". Using auto-escalation.', $densityOption));
            }
        }

        $this->info(sprintf('Parsing Anschreiben from: %s', $inputPath));

        try {
            $data = $parser->parseFile($inputPath);
        } catch (Throwable $e) {
            $this->error(sprintf('Failed to parse input file: %s', $e->getMessage()));

            return self::FAILURE;
        }

        // Generate companion markdown if JSON input
        if (str_ends_with(strtolower($inputPath), '.json') && $this->option('keep-companion-md')) {
            $companionMdPath = preg_replace('/\.json$/i', '.md', $inputPath) ?? ($inputPath.'.md');
            @file_put_contents($companionMdPath, $data->toMarkdown());
            $this->line(sprintf('  <comment>[OK]</comment> Generated companion Markdown: %s', $companionMdPath));
        }

        $this->info('Rendering DIN 5008 PDF with 1-page auto-fit guarantee...');

        try {
            $result = $renderer->render($data, $outputPath, $forceTier);
        } catch (Throwable $e) {
            $this->error(sprintf('Rendering failed: %s', $e->getMessage()));

            return self::FAILURE;
        }

        if ($result->isSinglePage) {
            $this->info(sprintf('  <info>[SUCCESS]</info> DIN 5008 PDF successfully compiled (1 page, tier: %s): %s', $result->densityTier->label(), $result->pdfPath));

            return self::SUCCESS;
        }

        $this->warn(sprintf('  <comment>[WARNING]</comment> PDF compiled with %d pages (tier: %s). For strict DIN 5008 compliance, shorten letter body text.', $result->pageCount, $result->densityTier->label()));

        return self::SUCCESS;
    }
}
