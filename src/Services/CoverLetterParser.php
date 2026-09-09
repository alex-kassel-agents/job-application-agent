<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Services;

use AlexKasselAgents\JobApplicationAgent\Data\CoverLetterData;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

final class CoverLetterParser
{
    /**
     * @param  array<string, string>  $defaultSender
     */
    public function __construct(
        private readonly array $defaultSender = [
            'name' => 'Max Mustermann',
            'address' => 'Musterstraße 12, 10115 Berlin',
            'phone' => '+49 30 12345678',
            'email' => 'max.mustermann@example.com',
        ],
        private readonly string $defaultCity = 'Berlin',
        private readonly string $defaultSignoff = 'Mit freundlichen Grüßen',
    ) {}

    public function parseFile(string $filePath): CoverLetterData
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException(sprintf('File not found: %s', $filePath));
        }

        $content = (string) file_get_contents($filePath);
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($ext === 'json') {
            return $this->parseJson($content);
        }

        return $this->parseMarkdown($content);
    }

    public function parseJson(string $json): CoverLetterData
    {
        /** @var mixed $data */
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new InvalidArgumentException('Invalid JSON payload provided.');
        }

        /** @var array<string, mixed> $sender */
        $sender = is_array($data['sender'] ?? null) ? $data['sender'] : [];
        /** @var array<string, mixed> $recipient */
        $recipient = is_array($data['recipient'] ?? null) ? $data['recipient'] : [];
        /** @var array<string, mixed> $meta */
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        /** @var array<string, mixed> $content */
        $content = is_array($data['content'] ?? null) ? $data['content'] : [];

        $senderName = (string) ($sender['name'] ?? $this->defaultSender['name']);
        $senderAddress = (string) ($sender['address'] ?? $this->defaultSender['address']);
        $senderPhone = (string) ($sender['phone'] ?? $this->defaultSender['phone']);
        $senderEmail = (string) ($sender['email'] ?? $this->defaultSender['email']);

        $recipientLines = [];
        foreach (['company', 'department', 'contact_person', 'street', 'city'] as $key) {
            if (isset($recipient[$key]) && is_string($recipient[$key]) && trim($recipient[$key]) !== '') {
                $recipientLines[] = trim($recipient[$key]);
            }
        }

        $city = (string) ($meta['city'] ?? $this->defaultCity);
        $rawDate = (string) ($meta['date'] ?? '');
        $dateLine = $this->resolveDateLine($rawDate, $city);

        $subjectTitle = (string) ($meta['subject'] ?? 'Bewerbung');
        $referenceLine = isset($meta['reference_nr']) && is_string($meta['reference_nr']) && trim($meta['reference_nr']) !== ''
            ? trim($meta['reference_nr'])
            : null;

        $salutation = (string) ($data['salutation'] ?? 'Sehr geehrte Damen und Herren,');
        $intro = (string) ($content['intro'] ?? '');

        $bodyParagraphs = [];
        if (isset($content['body_paragraphs']) && is_array($content['body_paragraphs'])) {
            foreach ($content['body_paragraphs'] as $p) {
                if (is_string($p) && trim($p) !== '') {
                    $bodyParagraphs[] = trim($p);
                }
            }
        }

        $bulletPoints = [];
        if (isset($content['bullet_points']) && is_array($content['bullet_points'])) {
            foreach ($content['bullet_points'] as $bp) {
                if (is_string($bp) && trim($bp) !== '') {
                    $bulletPoints[] = trim($bp);
                }
            }
        }

        $conditions = isset($content['conditions']) && is_string($content['conditions']) && trim($content['conditions']) !== ''
            ? trim($content['conditions'])
            : null;

        $outro = isset($content['outro']) && is_string($content['outro']) && trim($content['outro']) !== ''
            ? trim($content['outro'])
            : null;

        $signoff = (string) ($data['signoff'] ?? $this->defaultSignoff);
        $signerName = (string) ($data['signature_name'] ?? $senderName);
        $attachments = (string) ($data['attachments'] ?? 'Anlagen');

        return new CoverLetterData(
            senderName: $senderName,
            senderAddress: $senderAddress,
            senderPhone: $senderPhone,
            senderEmail: $senderEmail,
            recipientLines: $recipientLines,
            dateLine: $dateLine,
            subjectTitle: $subjectTitle,
            referenceLine: $referenceLine,
            salutation: $salutation,
            intro: $intro,
            bodyParagraphs: $bodyParagraphs,
            bulletPoints: $bulletPoints,
            conditions: $conditions,
            outro: $outro,
            signoff: $signoff,
            signerName: $signerName,
            attachments: $attachments,
        );
    }

    public function parseMarkdown(string $markdown): CoverLetterData
    {
        $senderName = $this->defaultSender['name'];
        $senderAddress = $this->defaultSender['address'];
        $senderPhone = $this->defaultSender['phone'];
        $senderEmail = $this->defaultSender['email'];
        $recipientLines = [];
        $dateLine = '';
        $subjectTitle = '';
        $referenceLine = null;
        $salutation = '';
        $bodyText = $markdown;

        // Extract YAML Frontmatter if present
        if (preg_match('/^---\s*\r?\n(.*?)\r?\n---\s*\r?\n(.*)$/s', $markdown, $matches)) {
            $frontmatter = $matches[1];
            $bodyText = $matches[2];

            $fm = $this->parseSimpleYaml($frontmatter);
            if (isset($fm['sender']) && is_array($fm['sender'])) {
                $senderName = (string) ($fm['sender']['name'] ?? $senderName);
                $senderAddress = (string) ($fm['sender']['address'] ?? $senderAddress);
                $senderPhone = (string) ($fm['sender']['phone'] ?? $senderPhone);
                $senderEmail = (string) ($fm['sender']['email'] ?? $senderEmail);
            }

            if (isset($fm['recipient']) && is_array($fm['recipient'])) {
                foreach (['company', 'department', 'contact_person', 'street', 'city'] as $k) {
                    if (isset($fm['recipient'][$k]) && is_string($fm['recipient'][$k]) && trim($fm['recipient'][$k]) !== '') {
                        $recipientLines[] = trim($fm['recipient'][$k]);
                    }
                }
            }

            if (isset($fm['meta']) && is_array($fm['meta'])) {
                $subjectTitle = (string) ($fm['meta']['subject'] ?? $subjectTitle);
                if (isset($fm['meta']['reference_nr']) && is_string($fm['meta']['reference_nr'])) {
                    $referenceLine = trim($fm['meta']['reference_nr']);
                }
                $city = (string) ($fm['meta']['city'] ?? $this->defaultCity);
                $dateVal = (string) ($fm['meta']['date'] ?? '');
                if ($dateVal !== '') {
                    $dateLine = $this->resolveDateLine($dateVal, $city);
                }
            }

            if (isset($fm['salutation']) && is_string($fm['salutation'])) {
                $salutation = trim($fm['salutation']);
            }
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($bodyText)) ?: [];
        $bodyParagraphs = [];
        $bulletPoints = [];
        $intro = '';
        $conditions = null;
        $outro = null;
        $signoff = $this->defaultSignoff;
        $signerName = $senderName;
        $attachments = 'Anlagen';

        $state = ($salutation !== '' || ($recipientLines !== [] && $subjectTitle !== '')) ? 'BODY' : 'HEADER';
        $blocks = [];
        $currentBlock = [];

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);

            if ($line === '') {
                if ($currentBlock !== []) {
                    $blocks[] = $currentBlock;
                    $currentBlock = [];
                }

                continue;
            }

            if ($state === 'HEADER') {
                if (str_contains($line, 'Telefon:') || str_contains($line, 'E-Mail:')) {
                    if (str_contains($line, 'Telefon:')) {
                        $senderPhone = trim(explode('Telefon:', $line)[1], " \t•|,\n\r");
                    }
                    if (str_contains($line, 'E-Mail:')) {
                        $senderEmail = trim(explode('E-Mail:', $line)[1], " \t•|,\n\r");
                    }

                    continue;
                }

                if (str_contains($line, '•')) {
                    $parts = array_map('trim', explode('•', $line));
                    if (count($parts) >= 2) {
                        $senderName = $parts[0];
                        $senderAddress = $parts[1];

                        continue;
                    }
                }

                $state = 'RECIPIENT';
            }

            if ($state === 'RECIPIENT') {
                if ($this->isDateLine($line)) {
                    $dateLine = trim($line, "*# \t");
                    $state = 'AFTER_RECIPIENT';

                    continue;
                }

                if ($this->isSubjectLine($line)) {
                    $state = 'AFTER_RECIPIENT';
                } else {
                    $recipientLines[] = trim($line, "*# \t");

                    continue;
                }
            }

            if ($state === 'AFTER_RECIPIENT') {
                if ($dateLine === '' && $this->isDateLine($line)) {
                    $dateLine = trim($line, "*# \t");

                    continue;
                }

                if ($subjectTitle === '' && $this->isSubjectLine($line)) {
                    $subjectTitle = trim($line, "*# \t");

                    continue;
                }

                if ($referenceLine === null && (str_starts_with(strtolower($line), 'referenz') || str_starts_with(strtolower($line), 'ref.-nr') || str_starts_with(strtolower($line), 'kennziffer'))) {
                    $referenceLine = trim($line, "*# \t");

                    continue;
                }

                if (str_starts_with($line, 'Sehr geehrte') || str_starts_with($line, 'Guten Tag') || str_starts_with($line, 'Hallo')) {
                    $salutation = trim($line, "*# \t");
                    $state = 'BODY';

                    continue;
                }
            }

            if ($state === 'BODY') {
                if (str_starts_with($line, 'Mit freundlichen Grüßen') || str_starts_with($line, 'Freundliche Grüße') || str_starts_with($line, 'Herzliche Grüße')) {
                    $signoff = $line;
                    if ($currentBlock !== []) {
                        $blocks[] = $currentBlock;
                        $currentBlock = [];
                    }
                    $state = 'FOOTER';

                    continue;
                }

                if (strtolower(trim($line, "*# \t")) === 'anlagen') {
                    $attachments = trim($line, "*# \t");
                    if ($currentBlock !== []) {
                        $blocks[] = $currentBlock;
                        $currentBlock = [];
                    }
                    $state = 'FOOTER';

                    continue;
                }

                $currentBlock[] = $line;
            }
        }

        if ($currentBlock !== []) {
            $blocks[] = $currentBlock;
        }

        // Categorize extracted blocks
        foreach ($blocks as $blockIndex => $block) {
            $isList = false;
            foreach ($block as $bLine) {
                if (str_starts_with($bLine, '* ') || str_starts_with($bLine, '- ')) {
                    $isList = true;
                    break;
                }
            }

            if ($isList) {
                foreach ($block as $bLine) {
                    $cleanBp = (string) preg_replace('/^[*\-]\s+/', '', $bLine);
                    $bulletPoints[] = trim($cleanBp);
                }

                continue;
            }

            $joined = implode(' ', $block);
            if ($blockIndex === 0 && $intro === '') {
                $intro = $joined;
            } elseif (str_contains($joined, 'Gehalt') || str_contains($joined, 'Verfügung') || str_contains($joined, 'Kündigungsfrist')) {
                $conditions = $joined;
            } elseif ($blockIndex === count($blocks) - 1 && (str_contains($joined, 'Gespräch') || str_contains($joined, 'freue mich') || str_contains($joined, 'sehe mit Interesse'))) {
                $outro = $joined;
            } else {
                $bodyParagraphs[] = $joined;
            }
        }

        if ($dateLine === '') {
            $dateLine = $this->resolveDateLine('', $this->defaultCity);
        }

        if ($subjectTitle === '') {
            $subjectTitle = 'Bewerbung';
        }

        if ($salutation === '') {
            $salutation = 'Sehr geehrte Damen und Herren,';
        }

        return new CoverLetterData(
            senderName: $senderName,
            senderAddress: $senderAddress,
            senderPhone: $senderPhone,
            senderEmail: $senderEmail,
            recipientLines: $recipientLines,
            dateLine: $dateLine,
            subjectTitle: $subjectTitle,
            referenceLine: $referenceLine,
            salutation: $salutation,
            intro: $intro,
            bodyParagraphs: $bodyParagraphs,
            bulletPoints: $bulletPoints,
            conditions: $conditions,
            outro: $outro,
            signoff: $signoff,
            signerName: $signerName,
            attachments: $attachments,
        );
    }

    private function resolveDateLine(string $dateStr, string $city): string
    {
        if ($dateStr !== '') {
            if ($city !== '' && ! str_starts_with($dateStr, $city)) {
                return sprintf('%s, den %s', $city, $dateStr);
            }

            return $dateStr;
        }

        $now = new DateTimeImmutable;
        $months = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
        ];

        $formatted = sprintf('%d. %s %d', (int) $now->format('j'), $months[(int) $now->format('n')], (int) $now->format('Y'));

        return $city !== '' ? sprintf('%s, den %s', $city, $formatted) : $formatted;
    }

    private function isDateLine(string $line): bool
    {
        $pattern = '/(?:\b[A-ZÄÖÜa-zäöüß]+,\s*(?:den\s*)?)?'
            .'(?:\d{1,2}\.\s*(?:Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember|\d{1,2}\.)\s*\d{4}'
            .'|\d{1,2}[.\/-]\d{1,2}[.\/-]\d{4}'
            .'|\d{4}-\d{2}-\d{2})\b/u';

        return (bool) preg_match($pattern, $line);
    }

    private function isSubjectLine(string $line): bool
    {
        $lower = strtolower($line);

        return str_starts_with($line, '#')
            || str_starts_with($line, '**Bewerbung')
            || str_starts_with($line, 'Bewerbung als')
            || str_contains($lower, 'bewerbung');
    }

    /**
     * @return array<string, mixed>
     */
    private function parseSimpleYaml(string $yaml): array
    {
        $result = [];
        $currentSection = null;

        $lines = preg_split('/\r\n|\r|\n/', $yaml) ?: [];
        foreach ($lines as $raw) {
            if (trim($raw) === '' || str_starts_with(trim($raw), '#')) {
                continue;
            }

            // Top-level key: e.g. "salutation: '...'" or "sender:"
            if (preg_match('/^([a-zA-Z0-9_]+)\s*:\s*(.*)$/', $raw, $m)) {
                $key = trim($m[1]);
                $val = trim($m[2]);

                if ($val === '') {
                    $currentSection = $key;
                    $result[$currentSection] = [];
                } else {
                    $currentSection = null;
                    $result[$key] = trim($val, " '\"\t\n\r");
                }

                continue;
            }

            // Indented key: e.g. "  name: '...'"
            if ($currentSection !== null && preg_match('/^\s+([a-zA-Z0-9_]+)\s*:\s*(.*)$/', $raw, $sub)) {
                $subKey = trim($sub[1]);
                $subVal = trim($sub[2], " '\"\t\n\r");
                /** @var array<string, mixed> $sectionData */
                $sectionData = is_array($result[$currentSection]) ? $result[$currentSection] : [];
                $sectionData[$subKey] = $subVal;
                $result[$currentSection] = $sectionData;
            }
        }

        return $result;
    }
}
