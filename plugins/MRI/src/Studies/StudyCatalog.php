<?php

declare(strict_types=1);

namespace LibreBooking\Studies;

/**
 * The studies that session names are checked against.
 *
 * The studies come from a JSON file: an object whose entries carry a "regex" string and optional metadata. The file
 * is downloaded and cached on disk for the configured duration; when a fresh download fails, a cached copy is used.
 * Each study regex must match the whole session name, and the first study that matches wins.
 */
final class StudyCatalog
{
    public const DEFAULT_CACHE_TTL_SECONDS = 86400;

    private const HTTP_OK = 200;
    private const CONNECT_TIMEOUT_SECONDS = 5;
    private const TRANSFER_TIMEOUT_SECONDS = 10;

    /**
     * Candidate PCRE delimiters. The first one absent from the regex is used, so no escaping is needed.
     */
    private const DELIMITERS = ['~', '#', '!', '%', '@', '+', '|'];

    /**
     * @var array<string, self> one catalogue per studies URL, loaded once per process
     */
    private static array $catalogs = [];

    /**
     * @param StudyDefinition[]|null $studies null when no usable studies are available
     * @param array<int, array{pattern: string, study: StudyDefinition}> $compiled studies in order, with their patterns
     */
    private function __construct(
        private readonly ?array $studies,
        private readonly array $compiled,
    ) {
    }

    /**
     * The catalogue of a studies URL, downloaded and cached in the temporary directory. A GitHub "blob" page is turned
     * into the raw file.
     */
    public static function forUrl(string $url, int $cacheTtlSeconds = self::DEFAULT_CACHE_TTL_SECONDS): self
    {
        $rawUrl = self::rawUrl($url);

        return self::$catalogs[$rawUrl] ??= self::fromDownload(
            $rawUrl,
            $cacheTtlSeconds,
            sys_get_temp_dir() . '/librebooking-studies-' . sha1($rawUrl) . '.json'
        );
    }

    /**
     * Downloads the studies unless a fresh copy is cached. A failed download falls back to an expired cached copy.
     *
     * @param (callable(string): ?string)|null $fetcher downloads the URL and returns the body, or null on failure
     */
    public static function fromDownload(string $url, int $cacheTtlSeconds, string $cacheFile, ?callable $fetcher = null): self
    {
        $fetcher ??= fn (string $url): ?string => self::fetch($url);

        if ($cacheTtlSeconds > 0 && is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtlSeconds) {
            $cached = self::readCache($cacheFile);
            if ($cached !== null) {
                return self::fromStudies($cached);
            }
        }

        $body = $fetcher($url);
        $studies = $body === null ? null : self::parse($body);
        if ($studies !== null) {
            if ($cacheTtlSeconds > 0) {
                file_put_contents($cacheFile, $body, LOCK_EX);
            }
            return self::fromStudies($studies);
        }

        $stale = self::readCache($cacheFile);
        if ($stale !== null) {
            \Log::Error('Could not download studies, using cached copy. Url=%s', $url);
            return self::fromStudies($stale);
        }

        \Log::Error('Could not load studies definition. Url=%s', $url);
        return self::unavailable();
    }

    /**
     * @param StudyDefinition[] $studies checked in order; the first match wins
     */
    public static function fromStudies(array $studies): self
    {
        $compiled = [];
        foreach ($studies as $study) {
            $pattern = self::compile($study->regex);
            if ($pattern === null) {
                \Log::Error('Ignoring invalid study name regex. Regex=%s', $study->regex);
                continue;
            }
            if ($study->multisession && !str_contains($study->regex, '<subject>')) {
                \Log::Error('Multi-session study regex has no named group "subject". Regex=%s', $study->regex);
            }
            $compiled[] = ['pattern' => $pattern, 'study' => $study];
        }

        return new self($studies, $compiled);
    }

    /**
     * A catalogue whose studies could not be loaded.
     */
    public static function unavailable(): self
    {
        return new self(null, []);
    }

    public function isAvailable(): bool
    {
        return $this->studies !== null;
    }

    /**
     * @return StudyDefinition[]|null
     */
    public function studies(): ?array
    {
        return $this->studies;
    }

    public function find(string $sessionName): ?StudyMatch
    {
        foreach ($this->compiled as $entry) {
            if (preg_match($entry['pattern'], $sessionName, $groups) === 1) {
                return new StudyMatch(
                    study: $entry['study'],
                    prefix: $groups['study'] ?? '',
                    subject: $groups['subject'] ?? '',
                    session: $groups['session'] ?? '',
                );
            }
        }

        return null;
    }

    public function matches(string $sessionName): bool
    {
        return $this->find($sessionName) !== null;
    }

    /**
     * Extracts the studies from a studies JSON document. Entries without a usable regex and repeated regexes are skipped.
     *
     * @return StudyDefinition[]|null null when the document is not JSON or contains no usable regex
     */
    public static function parse(string $json): ?array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }

        $studies = [];
        $regexes = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry) || !isset($entry['regex']) || !is_string($entry['regex']) || $entry['regex'] === '') {
                continue;
            }
            if (in_array($entry['regex'], $regexes, true)) {
                continue;
            }

            $regexes[] = $entry['regex'];
            $studies[] = new StudyDefinition(
                regex: $entry['regex'],
                multisession: (bool) ($entry['multisession'] ?? false),
                gidName: isset($entry['gid_name']) && is_string($entry['gid_name']) ? $entry['gid_name'] : null,
                ethic: isset($entry['ethic']) && is_string($entry['ethic']) ? $entry['ethic'] : null,
                caregiverScreening: (int) ($entry['caregiver_screening'] ?? 0),
            );
        }

        return empty($studies) ? null : $studies;
    }

    /**
     * Wraps the regex with delimiters and anchors so it must match the entire name.
     *
     * @return string|null the compiled pattern, or null if the regex is not usable
     */
    public static function compile(string $regex): ?string
    {
        foreach (self::DELIMITERS as $delimiter) {
            if (str_contains($regex, $delimiter)) {
                continue;
            }

            $pattern = $delimiter . '\A(?:' . $regex . ')\z' . $delimiter . 'u';

            // preg_match returns false (and warns) when the regex does not compile
            return @preg_match($pattern, '') === false ? null : $pattern;
        }

        return null;
    }

    /**
     * A GitHub "blob" page as the raw file that can be downloaded.
     */
    public static function rawUrl(string $url): string
    {
        return str_replace('github.com', 'raw.githubusercontent.com', str_replace('/blob', '', $url));
    }

    private static function readCache(string $cacheFile): ?array
    {
        if (!is_file($cacheFile)) {
            return null;
        }

        $contents = file_get_contents($cacheFile);
        return $contents === false ? null : self::parse($contents);
    }

    private static function fetch(string $url): ?string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TRANSFER_TIMEOUT_SECONDS,
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if (!is_string($body) || $status !== self::HTTP_OK) {
            \Log::Error('HTTP request for studies failed. Url=%s, Status=%s', $url, $status);
            return null;
        }

        return $body;
    }
}
