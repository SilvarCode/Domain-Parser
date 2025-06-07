<?php
declare(strict_types=1);

namespace SilvarCode\DomainParser\Parser;

use RuntimeException;

final class DomainParser
{
    protected const SUFFIX_LIST_FILE_PATH = __DIR__ . '/../../res/public_suffix_list.dat.txt';
    protected const SUFFIX_LIST_FILE_SOURCE_URL = 'https://publicsuffix.org/list/public_suffix_list.dat';
    protected const SUFFIX_LIST_FILE_PROCESSED_PATH = self::SUFFIX_LIST_FILE_PATH . '.processed';
    protected const UPDATE_INTERVAL_SECONDS = 259200; // 3 days

    /**
     * @var bool
     */
    protected bool $memoryCache;

    /**
     * Cache loaded suffixes for the current execution
     * @var array<string, int>
     */
    protected array $suffixSet = [];

    /**
     * @param bool $memoryCache
     * @param array $suffixSet
     */
    public function __construct(bool $memoryCache = false, array $suffixSet = [])
    {
        $this->memoryCache = $memoryCache;
        $this->suffixSet = $this->parseProvidedSuffixes($suffixSet);

        $this->checkUpdateSourceFile();
        $this->checkGenerateProcessedFile();

        if ($this->memoryCache) {
            $this->loadSuffixSet();
        }
    }

    /**
     * @param array $suffixSet
     * @return array
     */
    protected function parseProvidedSuffixes(array $suffixSet): array
    {
        $normalized = [];

        foreach (array_filter($suffixSet) as $suffix) {
            $suffix = strtolower(trim($suffix));
            if (!preg_match('/^(?:\*|[a-z0-9\-]+)(?:\.[a-z0-9\-]+)*$/i', $suffix)) {
                throw new RuntimeException("Invalid suffix format: {$suffix}");
            }

            $normalized[$suffix] = substr_count($suffix, '.');
        }

        return $normalized;
    }

    /**
     * @param string $isValidSuffix
     * @return bool
     */
    protected function isValidSuffix(string $isValidSuffix): bool
    {
        if (strlen($isValidSuffix) < 1) {
            return false;
        } elseif (isset($this->suffixSet[$isValidSuffix])) {
            return true;
        }

        $path = $this->getProcessedSuffixFilePath();

        if (!is_readable($path)) {
            throw new RuntimeException("Processed suffix file not found or not readable: $path");
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException(sprintf(
                "Failed to open suffix file: %s", $path
            ));
        }

        $result = false;
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '//')) {
                continue; // skip empty lines and comments
            }

            if ($isValidSuffix === $line) {
                $this->suffixSet[$line] = substr_count($line, '.');
                $result = true;
                break;
            }
        }

        fclose($handle);

        return $result;
    }

    /**
     * @param string $host
     * @return string|null
     */
    public function tld(string $host): ?string
    {
        $checkHostParts = explode('.', $host);
        $len = count($checkHostParts);
        $tld = null;

        for ($i = 0; $i < $len; $i++) {
            $suffixCandidateParts = array_slice($checkHostParts, $i);
            $suffixCandidate = implode('.', $suffixCandidateParts);

            if ($this->isValidSuffix($suffixCandidate)) {
                $tld = $suffixCandidate;
                break;
            }

            if (($len - $i) > 1) {
                $wildcardCandidateParts = $suffixCandidateParts;
                $wildcardCandidateParts[0] = '*';
                $wildcardCandidate = implode('.', $wildcardCandidateParts);

                if ($this->isValidSuffix($wildcardCandidate)) {
                    $tld = implode('.', array_slice($checkHostParts, 1));
                    break;
                }
            }
        }

        return $tld;
    }

    /**
     * Loads the entire processed suffix list into memory for quick lookups
     */
    protected function loadSuffixSet(): void
    {
        $path = $this->getProcessedSuffixFilePath();
        if (!is_readable($path)) {
            throw new RuntimeException("Processed suffix file not found or not readable: $path");
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException(sprintf(
                "Failed to open suffix file: %s", $path
            ));
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '//')) {
                continue; // skip empty lines and comments
            }
            // Store as key for faster lookup along with the number of dots
            $this->suffixSet[$line] = substr_count($line, '.');
        }

        fclose($handle);
    }

    /**
     * @return void
     */
    protected function checkUpdateSourceFile(): void
    {
        $sourcePath = self::SUFFIX_LIST_FILE_PATH;

        $needsUpdate = true;

        if (file_exists($sourcePath)) {
            $lastModified = filemtime($sourcePath);
            if ($lastModified !== false) {
                $elapsed = time() - $lastModified;
                if ($elapsed < self::UPDATE_INTERVAL_SECONDS) {
                    $needsUpdate = false;
                }
            }
        }

        if ($needsUpdate) {
            $this->downloadSourceFile();
        }
    }

    /**
     * Downloads the suffix list from the remote source and saves it locally.
     *
     * @return void
     * @throws RuntimeException on failure.
     */
    protected function downloadSourceFile(): void
    {
        $url = self::SUFFIX_LIST_FILE_SOURCE_URL;
        $destination = self::SUFFIX_LIST_FILE_PATH;

        $context = stream_context_create([
            'https' => [
                'timeout' => 10,
                'header' => "User-Agent: SilvarCodeDomainParser/1.0\r\n"
            ]
        ]);

        $content = @file_get_contents($url, false, $context);

        if ($content === false) {
            throw new RuntimeException("Failed to download suffix list from {$url}");
        }

        if (file_put_contents($destination, $content) === false) {
            throw new RuntimeException("Failed to write suffix list to {$destination}");
        }
    }

    /**
     * Checks if processed suffix file exists; if not, generates it by cleaning original suffix list.
     */
    protected function checkGenerateProcessedFile(): void
    {
        if (!file_exists(self::SUFFIX_LIST_FILE_PROCESSED_PATH)) {
            $originalPath = self::SUFFIX_LIST_FILE_PATH;
            $processedPath = self::SUFFIX_LIST_FILE_PROCESSED_PATH;

            if (!is_readable($originalPath)) {
                throw new RuntimeException(sprintf(
                    "Original suffix list file is not readable or does not exist: %s", $originalPath
                ));
            }

            $inputHandle = fopen($originalPath, 'rb');
            if ($inputHandle === false) {
                throw new RuntimeException(sprintf(
                    "Failed to open original suffix list file for reading: %s", $originalPath
                ));
            }

            $outputHandle = fopen($processedPath, 'wb');
            if ($outputHandle === false) {
                fclose($inputHandle);
                throw new RuntimeException(sprintf(
                    "Failed to open processed suffix list file for writing: %s", $originalPath
                ));
            }

            while (($line = fgets($inputHandle)) !== false) {
                $line = trim($line);

                // Skip empty lines and comments (lines starting with //)
                if ($line === '' || str_starts_with($line, '//')) {
                    continue;
                }

                // Write the clean line to the processed file with a newline
                fwrite($outputHandle, $line . "\n");
            }

            fclose($inputHandle);
            fclose($outputHandle);
        }
    }

    /**
     * Return the resolved path for the processed suffix file
     *
     * @return string
     */
    public function getProcessedSuffixFilePath(): string
    {
        $resolved = realpath(self::SUFFIX_LIST_FILE_PROCESSED_PATH);

        if ($resolved === false) {
            return self::SUFFIX_LIST_FILE_PROCESSED_PATH;
        }

        return $resolved;
    }

    /**
     * @param string $host
     * @return string|null
     */
    public function getSubdomain(string $host): ?string
    {
        $host = strtolower(trim($host));
        $domain = $this->getRegistrableDomain($host);

        if ($domain === null) {
            return null;
        }

        $hostParts = explode('.', $host);
        $domainParts = explode('.', $domain);

        $subdomainParts = array_slice($hostParts, 0, (count($hostParts) - count($domainParts)));

        if (empty($subdomainParts)) {
            return null;
        }

        return $subdomainParts[0];
    }

    /**
     * @param string $host
     * @return array<string>
     */
    public function getSubdomains(string $host): array
    {
        $host = strtolower(trim($host));
        $domain = $this->getRegistrableDomain($host);

        if ($domain === null) {
            return [];
        }

        $hostParts = explode('.', $host);
        $domainParts = explode('.', $domain);

        // Extract all subdomain parts (everything before the registrable domain)
        return array_slice($hostParts, 0, (count($hostParts) - count($domainParts)));
    }

    /**
     * @param array $info
     * @return void
     */
    public static function showResult(array $info): void
    {
        echo "Domain Parsing Result:\n";
        echo str_repeat('=', 22) . "\n";

        foreach ($info as $key => $value) {
            if (is_array($value)) {
                echo ucfirst($key) . ":\n";
                if (empty($value)) {
                    echo "  (none)\n";
                } else {
                    foreach ($value as $item) {
                        echo "  - $item\n";
                    }
                }
            } else {
                echo ucfirst($key) . ": " . ($value === '' || $value === null ? '(none)' : $value) . "\n";
            }
        }

        echo "\n";
    }

    /**
     * @param string $host
     * @return string|null
     */
    public function getRegistrableDomain(string $host): ?string
    {
        $host = strtolower(trim($host));
        $parts = explode('.', $host);
        $partsCount = count($parts);
        if ($partsCount < 1) {
            return null;
        }

        $tld = $this->tld($host);

        if ($tld === null) {
            return null;
        }

        $tldParts = explode('.', $tld);
        $tldCount = count($tldParts);

        if ((($partsCount - $tldCount) - 1) < 0) {
            return null;
        }

        return implode('.', array_slice($parts, -($tldCount + 1)));
    }
}