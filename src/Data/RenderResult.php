<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Data;

final readonly class RenderResult
{
    public function __construct(
        public string $pdfPath,
        public int $pageCount,
        public DensityTier $densityTier,
        public bool $isSinglePage,
        public string $htmlContent,
    ) {}
}
