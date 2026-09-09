<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Tests\Unit;

use AlexKasselAgents\JobApplicationAgent\Services\AnschreibenParser;
use AlexKasselAgents\JobApplicationAgent\Tests\TestCase;

final class AnschreibenParserTest extends TestCase
{
    public function test_it_parses_json_template(): void
    {
        $templatePath = __DIR__.'/../../resources/templates/anschreiben_template.json';
        $parser = new AnschreibenParser;

        $data = $parser->parseFile($templatePath);

        $this->assertSame('Max Mustermann', $data->senderName);
        $this->assertNotEmpty($data->recipientLines);
        $this->assertStringContainsString('Beispielfirma GmbH', $data->recipientLines[0]);
        $this->assertStringContainsString('Bewerbung', $data->subjectTitle);
        $this->assertStringContainsString('Sehr geehrte', $data->salutation);
        $this->assertNotEmpty($data->bodyParagraphs);
        $this->assertNotEmpty($data->bulletPoints);
    }

    public function test_it_parses_markdown_template(): void
    {
        $templatePath = __DIR__.'/../../resources/templates/anschreiben_template.md';
        $parser = new AnschreibenParser;

        $data = $parser->parseFile($templatePath);

        $this->assertSame('Max Mustermann', $data->senderName);
        $this->assertNotEmpty($data->recipientLines);
        $this->assertStringContainsString('Bewerbung', $data->subjectTitle);
        $this->assertNotEmpty($data->bodyParagraphs);
    }

    public function test_it_generates_correct_markdown_representation(): void
    {
        $templatePath = __DIR__.'/../../resources/templates/anschreiben_template.json';
        $parser = new AnschreibenParser;

        $data = $parser->parseFile($templatePath);
        $md = $data->toMarkdown();

        $this->assertStringContainsString('Max Mustermann', $md);
        $this->assertStringContainsString('Beispielfirma GmbH', $md);
        $this->assertStringContainsString('Mit freundlichen Grüßen', $md);
        $this->assertStringContainsString('**Anlagen**', $md);
    }
}
