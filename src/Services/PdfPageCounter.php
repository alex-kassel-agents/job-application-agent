<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Services;

use RuntimeException;

final class PdfPageCounter
{
    public function countPages(string $pdfPath): int
    {
        if (! file_exists($pdfPath)) {
            throw new RuntimeException(sprintf('PDF file not found: %s', $pdfPath));
        }

        $content = @file_get_contents($pdfPath);
        if ($content === false || $content === '') {
            throw new RuntimeException(sprintf('Cannot read PDF file: %s', $pdfPath));
        }

        // Strategy 1: Find Pages root dictionary with /Count
        if (preg_match_all('#/Type\s*/Pages\b[^>]*?/Count\s+(\d+)#s', $content, $matches)) {
            $counts = array_map('intval', $matches[1]);
            $maxCount = max($counts);
            if ($maxCount > 0) {
                return $maxCount;
            }
        }

        // Strategy 2: Count individual /Type /Page objects (distinct from /Pages)
        if (preg_match_all('#/Type\s*/Page\b#', $content, $matches)) {
            return count($matches[0]);
        }

        // Strategy 3: Try Python pypdf if available
        $pythonCount = $this->countViaPython($pdfPath);
        if ($pythonCount !== null && $pythonCount > 0) {
            return $pythonCount;
        }

        return 1;
    }

    private function countViaPython(string $pdfPath): ?int
    {
        $escaped = escapeshellarg($pdfPath);
        $command = sprintf('python -c "from pypdf import PdfReader; print(len(PdfReader(%s).pages))" 2>%s', $escaped, PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');

        $output = @shell_exec($command);
        if (is_string($output) && is_numeric(trim($output))) {
            return (int) trim($output);
        }

        return null;
    }
}
