<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Data;

enum DensityTier: string
{
    case Normal = '';
    case Compact = 'compact';
    case UltraCompact = 'ultra-compact';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Compact => 'Compact',
            self::UltraCompact => 'Ultra-Compact',
        };
    }
}
