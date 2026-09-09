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

    public function test_it_loads_sender_from_markdown_profile_file(): void
    {
        $profileContent = <<<'MD'
# Personal Data

- **Name**: Alex Kassel
- **Address**: Teststraße 42, 34295 Edermünde
- **Phone**: +49 170 1234567
- **Email**: alex@example.com
MD;
        $tempProfile = tempnam(sys_get_temp_dir(), 'prof_');
        $this->assertIsString($tempProfile);
        file_put_contents($tempProfile, $profileContent);

        $markdownLetter = <<<'MD'
# Max Mustermann
## Musterstraße 1, 12345 Musterstadt

Firma XYZ
Abteilung
Musterstraße 2
12345 Musterstadt

Bewerbung als PHP Entwickler

Sehr geehrte Damen und Herren,

hiermit bewerbe ich mich.

Mit freundlichen Grüßen

Alex Kassel
MD;
        $tempLetter = tempnam(sys_get_temp_dir(), 'letter_');
        $this->assertIsString($tempLetter);
        file_put_contents($tempLetter, $markdownLetter);

        try {
            $parser = new CoverLetterParser(profilePath: $tempProfile);
            $data = $parser->parseFile($tempLetter);

            $this->assertSame('Alex Kassel', $data->senderName);
            $this->assertSame('Teststraße 42, 34295 Edermünde', $data->senderAddress);
            $this->assertSame('+49 170 1234567', $data->senderPhone);
            $this->assertSame('alex@example.com', $data->senderEmail);
            $this->assertStringContainsString('Edermünde', $data->dateLine);
            $this->assertStringNotContainsString('Berlin', $data->dateLine);
        } finally {
            @unlink($tempProfile);
            @unlink($tempLetter);
        }
    }

    public function test_it_resolves_city_from_frontmatter_meta(): void
    {
        $yamlLetter = <<<'MD'
---
sender:
  name: "Custom Name"
  address: "Hauptstr. 1, 50667 Köln"
  phone: "+49 221 000000"
  email: "custom@example.com"
recipient:
  company: "Ziel GmbH"
  street: "Zielstr. 10"
  city: "50667 Köln"
meta:
  subject: "Bewerbung"
  city: "Köln"
  date: "1. September 2026"
salutation: "Hallo,"
---

Mein Bewerbungstext.

Mit besten Grüßen

Custom Name
MD;
        $tempLetter = tempnam(sys_get_temp_dir(), 'letter_');
        $this->assertIsString($tempLetter);
        file_put_contents($tempLetter, $yamlLetter);

        try {
            $parser = new CoverLetterParser;
            $data = $parser->parseFile($tempLetter);

            $this->assertSame('Custom Name', $data->senderName);
            $this->assertSame('Köln, den 1. September 2026', $data->dateLine);
            $this->assertStringNotContainsString('Berlin', $data->dateLine);
        } finally {
            @unlink($tempLetter);
        }
    }

    public function test_it_does_not_inject_berlin_when_city_is_null_and_not_in_address(): void
    {
        $yamlLetter = <<<'MD'
---
sender:
  name: "No City Sender"
  address: "Postfach 123"
  phone: "+49 000"
  email: "none@example.com"
recipient:
  company: "Ziel GmbH"
  street: "Zielstr. 10"
  city: "Musterstadt"
meta:
  subject: "Bewerbung"
  date: "10. September 2026"
salutation: "Hallo,"
---

Mein Bewerbungstext.

Mit freundlichen Grüßen

No City Sender
MD;
        $tempLetter = tempnam(sys_get_temp_dir(), 'letter_');
        $this->assertIsString($tempLetter);
        file_put_contents($tempLetter, $yamlLetter);

        try {
            $parser = new CoverLetterParser(defaultCity: null);
            $data = $parser->parseFile($tempLetter);

            $this->assertSame('10. September 2026', $data->dateLine);
            $this->assertStringNotContainsString('Berlin', $data->dateLine);
        } finally {
            @unlink($tempLetter);
        }
    }
}
