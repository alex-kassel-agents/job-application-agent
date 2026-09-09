<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Tests\Unit;

use AlexKasselAgents\JobApplicationAgent\Services\CoverLetterParser;
use AlexKasselAgents\JobApplicationAgent\Tests\TestCase;

final class CoverLetterParserTest extends TestCase
{
    public function test_it_parses_json_template(): void
    {
        $templatePath = __DIR__.'/../../resources/templates/cover_letter_template.json';
        $parser = new CoverLetterParser;

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
        $templatePath = __DIR__.'/../../resources/templates/cover_letter_template.md';
        $parser = new CoverLetterParser;

        $data = $parser->parseFile($templatePath);

        $this->assertSame('Max Mustermann', $data->senderName);
        $this->assertNotEmpty($data->recipientLines);
        $this->assertStringContainsString('Bewerbung', $data->subjectTitle);
        $this->assertNotEmpty($data->bodyParagraphs);
    }

    public function test_it_generates_correct_markdown_representation(): void
    {
        $templatePath = __DIR__.'/../../resources/templates/cover_letter_template.json';
        $parser = new CoverLetterParser;

        $data = $parser->parseFile($templatePath);
        $md = $data->toMarkdown();

        $this->assertStringContainsString('Max Mustermann', $md);
        $this->assertStringContainsString('Beispielfirma GmbH', $md);
        $this->assertStringContainsString('Mit freundlichen Grüßen', $md);
        $this->assertStringContainsString('**Anlagen**', $md);
    }
}
