<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Data;

final readonly class CoverLetterData
{
    /**
     * @param  array<int, string>  $recipientLines
     * @param  array<int, string>  $bodyParagraphs
     * @param  array<int, string>  $bulletPoints
     */
    public function __construct(
        public string $senderName,
        public string $senderAddress,
        public string $senderPhone,
        public string $senderEmail,
        public array $recipientLines,
        public string $dateLine,
        public string $subjectTitle,
        public ?string $referenceLine,
        public string $salutation,
        public string $intro,
        public array $bodyParagraphs,
        public array $bulletPoints,
        public ?string $conditions,
        public ?string $outro,
        public string $signoff = 'Mit freundlichen Grüßen',
        public ?string $signerName = null,
        public string $attachments = 'Anlagen',
    ) {}

    public function senderReturnLine(): string
    {
        return sprintf(
            '%s &bull; %s &bull; Tel: %s',
            $this->senderName,
            $this->senderAddress,
            $this->senderPhone
        );
    }

    public function recipientHtml(): string
    {
        $escaped = array_map(
            static fn (string $line): string => htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $this->recipientLines
        );

        return implode("<br>\n", $escaped);
    }

    public function buildBodyHtml(): string
    {
        $parts = [];

        if ($this->intro !== '') {
            $parts[] = '<p>'.$this->formatInlineMarkdown($this->intro).'</p>';
        }

        foreach ($this->bodyParagraphs as $paragraph) {
            $trimmed = trim($paragraph);
            if ($trimmed !== '') {
                $parts[] = '<p>'.$this->formatInlineMarkdown($trimmed).'</p>';
            }
        }

        if ($this->bulletPoints !== []) {
            $items = [];
            foreach ($this->bulletPoints as $point) {
                $clean = trim((string) preg_replace('/^[*\-]\s+/', '', $point));
                if ($clean !== '') {
                    $items[] = '  <li>'.$this->formatInlineMarkdown($clean).'</li>';
                }
            }
            if ($items !== []) {
                $parts[] = "<ul>\n".implode("\n", $items)."\n</ul>";
            }
        }

        if ($this->conditions !== null && trim($this->conditions) !== '') {
            $parts[] = '<p>'.$this->formatInlineMarkdown(trim($this->conditions)).'</p>';
        }

        if ($this->outro !== null && trim($this->outro) !== '') {
            $parts[] = '<p>'.$this->formatInlineMarkdown(trim($this->outro)).'</p>';
        }

        return implode("\n", $parts);
    }

    /**
     * @return array<string, string>
     */
    public function toTemplateVariables(DensityTier $tier): array
    {
        $effectiveSignerName = $this->signerName ?? $this->senderName;
        $refHtml = ($this->referenceLine !== null && $this->referenceLine !== '')
            ? sprintf('<div class="reference-line">%s</div>', htmlspecialchars($this->referenceLine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))
            : '';

        return [
            'DENSITY_CLASS' => $tier->value,
            'SENDER_NAME' => htmlspecialchars($this->senderName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'SENDER_ADDRESS' => htmlspecialchars($this->senderAddress, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'SENDER_PHONE' => htmlspecialchars($this->senderPhone, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'SENDER_EMAIL' => htmlspecialchars($this->senderEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'SENDER_RETURN_LINE' => $this->senderReturnLine(),
            'RECIPIENT_HTML' => $this->recipientHtml(),
            'DATE_LINE' => htmlspecialchars($this->dateLine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'SUBJECT_TITLE' => htmlspecialchars($this->subjectTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'REFERENCE_LINE_HTML' => $refHtml,
            'SALUTATION' => htmlspecialchars($this->salutation, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'BODY_HTML' => $this->buildBodyHtml(),
            'SIGNOFF' => htmlspecialchars($this->signoff, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'SIGNER_NAME' => htmlspecialchars($effectiveSignerName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            'ATTACHMENTS' => htmlspecialchars($this->attachments, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ];
    }

    public function toMarkdown(): string
    {
        $lines = [];
        $lines[] = sprintf('%s • %s', $this->senderName, $this->senderAddress);
        $lines[] = sprintf('Telefon: %s • E-Mail: %s', $this->senderPhone, $this->senderEmail);
        $lines[] = '';

        foreach ($this->recipientLines as $line) {
            $lines[] = $line;
        }
        $lines[] = '';

        if ($this->dateLine !== '') {
            $lines[] = $this->dateLine;
            $lines[] = '';
        }

        $lines[] = sprintf('**%s**', $this->subjectTitle);
        if ($this->referenceLine !== null && $this->referenceLine !== '') {
            $lines[] = sprintf('**%s**', $this->referenceLine);
        }
        $lines[] = '';

        $lines[] = $this->salutation;
        $lines[] = '';

        if ($this->intro !== '') {
            $lines[] = $this->intro;
            $lines[] = '';
        }

        foreach ($this->bodyParagraphs as $paragraph) {
            $trimmed = trim($paragraph);
            if ($trimmed !== '') {
                $lines[] = $trimmed;
                $lines[] = '';
            }
        }

        if ($this->bulletPoints !== []) {
            foreach ($this->bulletPoints as $point) {
                $clean = trim((string) preg_replace('/^[*\-]\s+/', '', $point));
                $lines[] = '- '.$clean;
            }
            $lines[] = '';
        }

        if ($this->conditions !== null && trim($this->conditions) !== '') {
            $lines[] = trim($this->conditions);
            $lines[] = '';
        }

        if ($this->outro !== null && trim($this->outro) !== '') {
            $lines[] = trim($this->outro);
            $lines[] = '';
        }

        $lines[] = $this->signoff;
        $lines[] = '';
        $lines[] = $this->signerName ?? $this->senderName;
        $lines[] = '';
        $lines[] = sprintf('**%s**', $this->attachments);

        return implode("\n", $lines)."\n";
    }

    private function formatInlineMarkdown(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return (string) preg_replace('/\*\*(.*?)\*\*/', '<strong>\1</strong>', $escaped);
    }
}
