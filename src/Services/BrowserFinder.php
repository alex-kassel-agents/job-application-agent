<?php

declare(strict_types=1);

namespace AlexKasselAgents\JobApplicationAgent\Services;

final class BrowserFinder
{
    public function __construct(
        private readonly ?string $configuredBinary = null
    ) {}

    public function find(): ?string
    {
        if ($this->configuredBinary !== null && $this->isExecutableFile($this->configuredBinary)) {
            return $this->configuredBinary;
        }

        foreach (['JOB_APPLICATION_BROWSER_BIN', 'CHROME_BIN', 'BROWSER_BIN'] as $envKey) {
            $envVal = getenv($envKey);
            if (is_string($envVal) && $envVal !== '' && $this->isExecutableFile($envVal)) {
                return $envVal;
            }
        }

        foreach ($this->candidatePaths() as $candidate) {
            if ($this->isExecutableFile($candidate)) {
                return $candidate;
            }
        }

        foreach (['msedge', 'chrome', 'google-chrome', 'chromium', 'chromium-browser', 'brave'] as $command) {
            $path = $this->findInPath($command);
            if ($path !== null) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function candidatePaths(): array
    {
        $localAppData = (string) (getenv('LOCALAPPDATA') ?: '');
        $programFiles = (string) (getenv('ProgramFiles') ?: 'C:\\Program Files');
        $programFilesX86 = (string) (getenv('ProgramFiles(x86)') ?: 'C:\\Program Files (x86)');

        return [
            // Windows standard paths
            $programFiles.'\\Microsoft\\Edge\\Application\\msedge.exe',
            $programFilesX86.'\\Microsoft\\Edge\\Application\\msedge.exe',
            $programFiles.'\\Google\\Chrome\\Application\\chrome.exe',
            $programFilesX86.'\\Google\\Chrome\\Application\\chrome.exe',
            $programFiles.'\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
            $programFilesX86.'\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
            $localAppData.'\\Microsoft\\Edge\\Application\\msedge.exe',
            $localAppData.'\\Google\\Chrome\\Application\\chrome.exe',

            // macOS standard paths
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
            '/Applications/Brave Browser.app/Contents/MacOS/Brave Browser',
            '/Applications/Chromium.app/Contents/MacOS/Chromium',

            // Linux standard paths
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/usr/bin/microsoft-edge',
            '/usr/bin/brave-browser',
            '/snap/bin/chromium',
        ];
    }

    private function findInPath(string $binary): ?string
    {
        $locator = PHP_OS_FAMILY === 'Windows' ? 'where.exe' : 'which';
        $output = @shell_exec(sprintf('%s %s 2>%s', escapeshellcmd($locator), escapeshellarg($binary), PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'));

        if (! is_string($output)) {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($output));
        if ($lines === false || $lines === []) {
            return null;
        }

        $firstLine = trim($lines[0]);

        return $this->isExecutableFile($firstLine) ? $firstLine : null;
    }

    private function isExecutableFile(string $path): bool
    {
        return file_exists($path) && ! is_dir($path);
    }
}
