<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Tests\Unit;

use AlexKasselAgents\JobApplicationAgent\Data\DensityTier;
use AlexKasselAgents\JobApplicationAgent\Services\BrowserFinder;
use AlexKasselAgents\JobApplicationAgent\Services\CoverLetterParser;
use AlexKasselAgents\JobApplicationAgent\Services\Din5008PdfRenderer;
use AlexKasselAgents\JobApplicationAgent\Services\PdfPageCounter;
use AlexKasselAgents\JobApplicationAgent\Tests\TestCase;

final class Din5008PdfRendererTest extends TestCase
{
    public function test_it_compiles_html_with_all_variables(): void
    {
        $parser = new CoverLetterParser;
        $data = $parser->parseFile(__DIR__.'/../../resources/templates/cover_letter_template.json');

        $renderer = new Din5008PdfRenderer(
            new BrowserFinder,
            new PdfPageCounter,
            __DIR__.'/../../resources/templates/din5008_letter.html'
        );

        $template = (string) file_get_contents(__DIR__.'/../../resources/templates/din5008_letter.html');
        $html = $renderer->compileHtml($template, $data, DensityTier::Compact);

        $this->assertStringContainsString('class="sheet compact"', $html);
        $this->assertStringContainsString('Max Mustermann', $html);
        $this->assertStringContainsString('Beispielfirma GmbH', $html);
        $this->assertStringContainsString('Anlagen', $html);
    }
}
