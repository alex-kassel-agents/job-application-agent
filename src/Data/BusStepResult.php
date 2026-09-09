<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Data;

final readonly class BusStepResult
{
    public function __construct(
        public string $stepFilename,
        public string $stepPath,
        public string $status,
        public ?string $signal,
        public ?string $nextRecipientId,
        public ?string $finalLetterPath = null,
        public ?string $finalEmailPath = null,
    ) {}
}
