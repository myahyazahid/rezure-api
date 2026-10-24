<?php

namespace App\Services\WebAnalytics;

use DeviceDetector\DeviceDetector;
use Illuminate\Support\Facades\Cache;

/**
 * Turns a website visitor's User-Agent into browser / OS / device fields,
 * and flags bots so ingest can drop them (matomo/device-detector, v4).
 *
 * Parsing walks a large set of regexes, and a site's visitors share a small
 * set of user agents, so results are cached per UA for a day.
 */
class UserAgentParser
{
    private const CACHE_TTL_SECONDS = 86400;

    /**
     * @return array{is_bot: bool, bot_name: ?string, bot_category: ?string, browser: ?string, browser_version: ?string, browser_engine: ?string, os: ?string, os_version: ?string, device_type: ?string, device_brand: ?string, device_model: ?string}
     */
    public function parse(?string $userAgent): array
    {
        $userAgent = trim((string) $userAgent);

        // Every real browser sends one; an empty UA is a script.
        if ($userAgent === '') {
            return [...$this->emptyResult(), 'is_bot' => true, 'bot_name' => 'Empty user agent', 'bot_category' => 'Unknown'];
        }

        return Cache::remember(
            'web-ua:'.sha1($userAgent),
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->detect($userAgent),
        );
    }

    /**
     * @return array{is_bot: bool, bot_name: ?string, bot_category: ?string, browser: ?string, browser_version: ?string, browser_engine: ?string, os: ?string, os_version: ?string, device_type: ?string, device_brand: ?string, device_model: ?string}
     */
    private function detect(string $userAgent): array
    {
        $detector = new DeviceDetector($userAgent);
        $detector->parse();

        if ($detector->isBot()) {
            $bot = $detector->getBot();

            return [
                ...$this->emptyResult(),
                'is_bot' => true,
                'bot_name' => $this->stringOrNull(is_array($bot) ? ($bot['name'] ?? null) : null) ?? 'Unknown bot',
                'bot_category' => $this->stringOrNull(is_array($bot) ? ($bot['category'] ?? null) : null),
            ];
        }

        $client = $detector->getClient();
        $os = $detector->getOs();

        return [
            'is_bot' => false,
            'bot_name' => null,
            'bot_category' => null,
            'browser' => $this->stringOrNull(is_array($client) ? ($client['name'] ?? null) : null),
            'browser_version' => $this->stringOrNull(is_array($client) ? ($client['version'] ?? null) : null),
            'browser_engine' => $this->stringOrNull(is_array($client) ? ($client['engine'] ?? null) : null),
            'os' => $this->stringOrNull(is_array($os) ? ($os['name'] ?? null) : null),
            'os_version' => $this->stringOrNull(is_array($os) ? ($os['version'] ?? null) : null),
            'device_type' => $this->stringOrNull($detector->getDeviceName()),
            'device_brand' => $this->stringOrNull($detector->getBrandName()),
            'device_model' => $this->stringOrNull($detector->getModel()),
        ];
    }

    /**
     * @return array{is_bot: bool, bot_name: null, bot_category: null, browser: null, browser_version: null, browser_engine: null, os: null, os_version: null, device_type: null, device_brand: null, device_model: null}
     */
    private function emptyResult(): array
    {
        return [
            'is_bot' => false,
            'bot_name' => null,
            'bot_category' => null,
            'browser' => null,
            'browser_version' => null,
            'browser_engine' => null,
            'os' => null,
            'os_version' => null,
            'device_type' => null,
            'device_brand' => null,
            'device_model' => null,
        ];
    }

    /**
     * device-detector reports "unknown" as an empty string or "UNK".
     */
    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || $value === DeviceDetector::UNKNOWN) {
            return null;
        }

        return mb_substr($value, 0, 64);
    }
}
