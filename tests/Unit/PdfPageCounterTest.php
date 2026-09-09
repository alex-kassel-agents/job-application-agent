<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Tests\Unit;

use AlexKasselAgents\JobApplicationAgent\Services\PdfPageCounter;
use AlexKasselAgents\JobApplicationAgent\Tests\TestCase;
use RuntimeException;

final class PdfPageCounterTest extends TestCase
{
    public function test_it_throws_when_file_not_found(): void
    {
        $counter = new PdfPageCounter;

        $this->expectException(RuntimeException::class);
        $counter->countPages('/non/existent/file.pdf');
    }

    public function test_it_counts_pages_from_pdf_stream(): void
    {
        $counter = new PdfPageCounter;

        $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            ."2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
            ."3 0 obj\n<< /Type /Page >>\nendobj\n"
            ."4 0 obj\n<< /Type /Page >>\nendobj\n"
            ."trailer\n<< /Root 1 0 R >>\n%%EOF";

        $tempPdf = tempnam(sys_get_temp_dir(), 'pdf_count_test_');
        $this->assertIsString($tempPdf);

        try {
            file_put_contents($tempPdf, $pdfContent);
            $pages = $counter->countPages($tempPdf);
            $this->assertSame(2, $pages);
        } finally {
            @unlink($tempPdf);
        }
    }
}
