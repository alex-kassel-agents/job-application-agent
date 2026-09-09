<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Services;

use AlexKasselAgents\JobApplicationAgent\Data\CoverLetterData;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final class CoverLetterParser
{
    /**
     * @var array<string, string>|null
     */
    private ?array $defaultSender;

    /**
     * @param  array<string, string>|null  $defaultSender
     */
    public function __construct(
        ?array $defaultSender = null,
        private readonly ?string $defaultCity = null,
        private readonly string $defaultSignoff = 'Mit freundlichen Grüßen',
        private readonly ?string $profilePath = null,
    ) {
        $this->defaultSender = $defaultSender;

        if ($this->defaultSender === null) {
            $effectiveProfile = $this->profilePath;
            if ($effectiveProfile === null && function_exists('config')) {
                /** @var string|null $cfgProfile */
                $cfgProfile = config('job-application-agent.profile_path');
                $effectiveProfile = $cfgProfile;
            }

            if ($effectiveProfile !== null && $effectiveProfile !== '') {
                $this->defaultSender = $this->loadSenderFromProfile($effectiveProfile);
            }
        }
    }

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

        /** @var array<string, mixed> $senderRaw */
        $senderRaw = is_array($data['sender'] ?? null) ? $data['sender'] : [];
        $sender = $this->resolveSender($senderRaw);
        $senderName = $sender['name'];
        $senderAddress = $sender['address'];
        $senderPhone = $sender['phone'];
        $senderEmail = $sender['email'];

        /** @var array<string, mixed> $recipient */
        $recipient = is_array($data['recipient'] ?? null) ? $data['recipient'] : [];
        /** @var array<string, mixed> $meta */
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        /** @var array<string, mixed> $content */
        $content = is_array($data['content'] ?? null) ? $data['content'] : [];

        $recipientLines = [];
        foreach (['company', 'department', 'contact_person', 'street', 'city'] as $key) {
            if (isset($recipient[$key]) && is_string($recipient[$key]) && trim($recipient[$key]) !== '') {
                $recipientLines[] = trim($recipient[$key]);
            }
        }

        $city = isset($meta['city']) && is_string($meta['city']) && trim($meta['city']) !== ''
            ? trim($meta['city'])
            : $this->defaultCity;
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
        $senderRaw = [];
        $recipientLines = [];
        $dateLine = '';
        $subjectTitle = '';
        $referenceLine = null;
        $salutation = '';
        $bodyText = $markdown;
        $city = $this->defaultCity;

        // Extract YAML Frontmatter if present
        if (preg_match('/^---\s*\r?\n(.*?)\r?\n---\s*\r?\n(.*)$/s', $markdown, $matches)) {
            $frontmatter = $matches[1];
            $bodyText = $matches[2];

            /** @var mixed $parsedYaml */
            $parsedYaml = Yaml::parse($frontmatter);
            $fm = is_array($parsedYaml) ? $parsedYaml : [];

            if (isset($fm['sender']) && is_array($fm['sender'])) {
                /** @var array<string, mixed> $senderRaw */
                $senderRaw = $fm['sender'];
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
                if (isset($fm['meta']['city']) && is_string($fm['meta']['city']) && trim($fm['meta']['city']) !== '') {
                    $city = trim($fm['meta']['city']);
                }
                $dateVal = (string) ($fm['meta']['date'] ?? '');
                if ($dateVal !== '') {
                    $dateLine = $this->resolveDateLine($dateVal, $city);
                }
            }

            if (isset($fm['salutation']) && is_string($fm['salutation'])) {
                $salutation = trim($fm['salutation']);
            }
        }

        $sender = $this->resolveSender($senderRaw);

        $lines = preg_split('/\r\n|\r|\n/', trim($bodyText)) ?: [];
        $bodyParagraphs = [];
        $bulletPoints = [];
        $intro = '';
        $conditions = null;
        $outro = null;
        $signoff = $this->defaultSignoff;
        $signerName = $sender['name'];
        $attachments = 'Anlagen';

        $hasFrontmatterHeader = ($salutation !== '' || ($recipientLines !== [] && $subjectTitle !== ''));
        $state = $hasFrontmatterHeader ? 'BODY' : 'HEADER';
        $blocks = [];
        $currentBlock = [];
        $discoveredRecipient = [];

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);

            if ($line === '') {
                if ($currentBlock !== []) {
                    $blocks[] = $currentBlock;
                    $currentBlock = [];
                }

                if ($state === 'HEADER_SENDER' && $discoveredRecipient === []) {
                    $state = 'HEADER_RECIPIENT';
                }

                continue;
            }

            // Skip markdown title comment like "# Anschreiben-Entwurf (Version 1)"
            if (str_starts_with($line, '#') && (str_contains(strtolower($line), 'anschreiben') || str_contains(strtolower($line), 'entwurf') || str_contains(strtolower($line), 'version'))) {
                continue;
            }

            if ($state === 'HEADER' || $state === 'HEADER_SENDER' || $state === 'HEADER_RECIPIENT') {
                if (str_contains($line, 'Telefon:') || str_contains($line, 'E-Mail:')) {
                    if (str_contains($line, 'Telefon:')) {
                        $sender['phone'] = trim(explode('Telefon:', $line)[1], " \t•|,\n\r");
                    }
                    if (str_contains($line, 'E-Mail:')) {
                        $sender['email'] = trim(explode('E-Mail:', $line)[1], " \t•|,\n\r");
                    }
                    $state = 'HEADER_SENDER';

                    continue;
                }

                if (str_contains($line, '•')) {
                    $parts = array_map('trim', explode('•', $line));
                    if (count($parts) >= 2 && ! str_contains(strtolower($parts[0]), 'gmbh') && ! str_contains(strtolower($parts[0]), 'ag')) {
                        $sender['name'] = trim($parts[0], "*# \t");
                        $sender['address'] = trim($parts[1], "*# \t");
                        $state = 'HEADER_SENDER';

                        continue;
                    }
                }

                if ($this->isDateLine($line)) {
                    $dateLine = trim($line, "*# \t");
                    $state = 'AFTER_RECIPIENT';

                    continue;
                }

                if ($this->isSubjectLine($line)) {
                    $subjectTitle = trim($line, "*# \t");
                    $state = 'AFTER_RECIPIENT';

                    continue;
                }

                if (str_starts_with($line, 'Sehr geehrte') || str_starts_with($line, 'Guten Tag') || str_starts_with($line, 'Hallo')) {
                    $salutation = trim($line, "*# \t");
                    $state = 'BODY';

                    continue;
                }

                if ($state === 'HEADER') {
                    if (str_starts_with($line, '**') && str_ends_with($line, '**')) {
                        $sender['name'] = trim($line, "*# \t");
                        $state = 'HEADER_SENDER';

                        continue;
                    }

                    $discoveredRecipient[] = trim($line, "*# \t");
                } elseif ($state === 'HEADER_SENDER') {
                    if (preg_match('/^\d{5}\s+\S+/u', $line)) {
                        $sender['address'] = trim($line, "*# \t");
                    }
                } elseif ($state === 'HEADER_RECIPIENT') {
                    $discoveredRecipient[] = trim($line, "*# \t");
                }

                continue;
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

        if ($recipientLines === [] && $discoveredRecipient !== []) {
            $recipientLines = $discoveredRecipient;
        }

        if ($currentBlock !== []) {
            $blocks[] = $currentBlock;
        }

        // Categorize extracted blocks
        $totalBlocks = count($blocks);
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
            } elseif ($this->isConditionsBlock($joined, $blockIndex, $totalBlocks)) {
                $conditions = $joined;
            } elseif ($this->isOutroBlock($joined, $blockIndex, $totalBlocks)) {
                $outro = $joined;
            } else {
                $bodyParagraphs[] = $joined;
            }
        }

        if ($dateLine === '') {
            $dateLine = $this->resolveDateLine('', $city);
        }

        if ($subjectTitle === '') {
            $subjectTitle = 'Bewerbung';
        }

        if ($salutation === '') {
            $salutation = 'Sehr geehrte Damen und Herren,';
        }

        return new CoverLetterData(
            senderName: $sender['name'],
            senderAddress: $sender['address'],
            senderPhone: $sender['phone'],
            senderEmail: $sender['email'],
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

    private function resolveDateLine(string $dateStr, ?string $city = null): string
    {
        $effectiveCity = $city ?? $this->defaultCity;
        if ($effectiveCity === null && $this->defaultSender !== null && isset($this->defaultSender['address'])) {
            $effectiveCity = $this->extractCityFromAddress($this->defaultSender['address']);
        }

        if ($dateStr !== '') {
            if ($effectiveCity !== null && $effectiveCity !== '' && ! str_starts_with($dateStr, $effectiveCity)) {
                return sprintf('%s, den %s', $effectiveCity, $dateStr);
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

        return ($effectiveCity !== null && $effectiveCity !== '')
            ? sprintf('%s, den %s', $effectiveCity, $formatted)
            : $formatted;
    }

    private function isConditionsBlock(string $text, int $index, int $total): bool
    {
        if ($index < max(0, $total - 3)) {
            return false;
        }

        $lower = strtolower($text);

        return str_contains($lower, 'gehalt')
            || str_contains($lower, 'kündigungsfrist')
            || (str_contains($lower, 'verfügung') && (str_contains($lower, 'sofort') || str_contains($lower, 'ab dem') || str_contains($lower, 'eintritt') || str_contains($lower, 'arbeitsbeginn') || str_contains($lower, 'arbeitsaufnahme')));
    }

    private function isOutroBlock(string $text, int $index, int $total): bool
    {
        if ($index < max(0, $total - 2)) {
            return false;
        }

        $lower = strtolower($text);

        return str_contains($lower, 'gespräch')
            || str_contains($lower, 'freue mich')
            || str_contains($lower, 'sehe mit interesse')
            || str_contains($lower, 'einladung');
    }

    private function extractCityFromAddress(string $address): ?string
    {
        if (preg_match('/\b\d{5}\s+([A-ZÄÖÜa-zäöüß\-]+)/u', $address, $matches)) {
            return trim($matches[1]);
        }

        if (str_contains($address, ',')) {
            $parts = explode(',', $address);
            $lastPart = trim(end($parts));
            $cleaned = preg_replace('/^\d+\s*/', '', $lastPart);
            if (is_string($cleaned) && trim($cleaned) !== '') {
                return trim($cleaned);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $dataSender
     * @return array{name: string, address: string, phone: string, email: string}
     */
    private function resolveSender(array $dataSender): array
    {
        $name = isset($dataSender['name']) && is_string($dataSender['name']) && trim($dataSender['name']) !== ''
            ? trim($dataSender['name'])
            : ($this->defaultSender['name'] ?? null);

        $address = isset($dataSender['address']) && is_string($dataSender['address']) && trim($dataSender['address']) !== ''
            ? trim($dataSender['address'])
            : ($this->defaultSender['address'] ?? null);

        $phone = isset($dataSender['phone']) && is_string($dataSender['phone'])
            ? trim($dataSender['phone'])
            : ($this->defaultSender['phone'] ?? '');

        $email = isset($dataSender['email']) && is_string($dataSender['email'])
            ? trim($dataSender['email'])
            : ($this->defaultSender['email'] ?? '');

        if ($name === null || $address === null) {
            throw new InvalidArgumentException('Sender name and address are required. Provide them in document frontmatter, configure defaultSender, or set candidate profile_path.');
        }

        return [
            'name' => $name,
            'address' => $address,
            'phone' => (string) $phone,
            'email' => (string) $email,
        ];
    }

    /**
     * @return array{name: string, address: string, phone: string, email: string}|null
     */
    private function loadSenderFromProfile(string $profilePath): ?array
    {
        $resolved = realpath($profilePath) ?: $profilePath;
        $personalDataFile = is_dir($resolved)
            ? $resolved.DIRECTORY_SEPARATOR.'personal_data.md'
            : $resolved;

        if (! file_exists($personalDataFile)) {
            return null;
        }

        $content = (string) file_get_contents($personalDataFile);
        $name = null;
        $address = null;
        $phone = null;
        $email = null;

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (preg_match('/^[-*]?\s*\*{0,2}Name\*{0,2}\s*:\s*(.+)$/i', $trimmed, $m)) {
                $name = trim($m[1]);
            } elseif (preg_match('/^[-*]?\s*\*{0,2}(?:Adresse|Address)\*{0,2}\s*:\s*(.+)$/i', $trimmed, $m)) {
                $address = trim($m[1]);
            } elseif (preg_match('/^[-*]?\s*\*{0,2}(?:Telefon|Phone)\*{0,2}\s*:\s*(.+)$/i', $trimmed, $m)) {
                $phone = trim($m[1]);
            } elseif (preg_match('/^[-*]?\s*\*{0,2}(?:E-Mail|Email)\*{0,2}\s*:\s*(.+)$/i', $trimmed, $m)) {
                $email = trim($m[1]);
            }
        }

        if ($name === null || $address === null) {
            return null;
        }

        return [
            'name' => $name,
            'address' => $address,
            'phone' => $phone ?? '',
            'email' => $email ?? '',
        ];
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
}
