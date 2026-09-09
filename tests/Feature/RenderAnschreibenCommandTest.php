<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Tests\Feature;

use AlexKasselAgents\JobApplicationAgent\Tests\TestCase;

final class RenderAnschreibenCommandTest extends TestCase
{
    public function test_it_fails_when_input_file_does_not_exist(): void
    {
        $this->artisan('job:render', ['input' => 'non_existent_file.json'])
            ->expectsOutputToContain('Input file not found')
            ->assertExitCode(1);
    }

    public function test_it_renders_json_template_to_pdf(): void
    {
        $templatePath = __DIR__.'/../../resources/templates/anschreiben_template.json';
        $tempOutput = tempnam(sys_get_temp_dir(), 'test_job_render_').'.pdf';

        try {
            $this->artisan('job:render', [
                'input' => $templatePath,
                '--output' => $tempOutput,
            ])->assertExitCode(0);

            $this->assertFileExists($tempOutput);
            $this->assertGreaterThan(1000, filesize($tempOutput));
        } finally {
            if (file_exists($tempOutput)) {
                @unlink($tempOutput);
            }
        }
    }
}
