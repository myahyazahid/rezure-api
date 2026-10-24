<?php

namespace App\Services\WebAnalytics;

/**
 * Decides where a website session came from — the channel (Organic Search,
 * Social, ...) and a normalized source name (Google, Reddit, ...) — from
 * its landing referrer, UTM parameters and ad click ids. The host lists
 * live in config/web-analytics.php.
 */
class TrafficSourceClassifier
{
    public const DIRECT = 'Direct';

    public const ORGANIC_SEARCH = 'Organic Search';

    public const PAID_SEARCH = 'Paid Search';

    public const SOCIAL = 'Social';

    public const PAID_SOCIAL = 'Paid Social';

    public const EMAIL = 'Email';

    public const CAMPAIGN = 'Campaign';

    public const DEVELOPER_COMMUNITIES = 'Developer Communities';

    public const AI_ASSISTANTS = 'AI Assistants';

    public const REFERRAL = 'Referral';

    /**
     * Checked in this order: AI before search so `gemini.google.com` isn't
     * taken for Google Search.
     *
     * @var array<string, string>
     */
    private const CATEGORY_CHANNELS = [
        'ai_assistants' => self::AI_ASSISTANTS,
        'developer_communities' => self::DEVELOPER_COMMUNITIES,
        'social_networks' => self::SOCIAL,
        'search_engines' => self::ORGANIC_SEARCH,
    ];

    /**
     * Ad networks whose click id alone means the visit was paid search —
     * fbclid & co. are appended to organic shares too, so they don't count.
     */
    private const PAID_SEARCH_CLICK_IDS = ['google', 'microsoft'];

    /**
     * @param  array{utm_source?: ?string, utm_medium?: ?string, utm_campaign?: ?string}  $utm
     * @return array{referrer_host: ?string, channel: string, source: string}
     */
    public function classify(?string $referrerUrl, array $utm, ?string $clickIdType = null): array
    {
        $host = $this->externalHost($referrerUrl);
        $utmSource = $this->clean($utm['utm_source'] ?? null);
        $utmMedium = strtolower((string) $this->clean($utm['utm_medium'] ?? null));
        $hasUtm = $utmSource !== null || $utmMedium !== '' || $this->clean($utm['utm_campaign'] ?? null) !== null;

        $match = $host !== null ? $this->matchHost($host) : null;

        // utm_source=reddit with no referrer (apps, privacy browsers) still
        // tells us the platform.
        if ($match === null && $utmSource !== null) {
            $match = $this->matchSourceName($utmSource);
        }

        $isPaid = in_array($utmMedium, config('web-analytics.paid_mediums'), true)
            || in_array($clickIdType, self::PAID_SEARCH_CLICK_IDS, true);

        $source = $match['name'] ?? $utmSource ?? $host ?? self::DIRECT;

        if (in_array($utmMedium, config('web-analytics.email_mediums'), true)) {
            $channel = self::EMAIL;
        } elseif ($isPaid) {
            $channel = ($match['category'] ?? null) === 'social_networks' ? self::PAID_SOCIAL : self::PAID_SEARCH;
        } elseif ($match !== null) {
            $channel = self::CATEGORY_CHANNELS[$match['category']];
        } elseif ($hasUtm) {
            $channel = self::CAMPAIGN;
        } elseif ($host !== null) {
            $channel = self::REFERRAL;
        } else {
            $channel = self::DIRECT;
        }

        return [
            'referrer_host' => $host,
            'channel' => $channel,
            'source' => mb_substr($source, 0, 100),
        ];
    }

    /**
     * The referrer's host without `www.`, or null when there's no referrer or
     * it is the website itself (an internal navigation isn't a source).
     */
    public function externalHost(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        // Only web referrers: `javascript://host/…` also has a host, and the
        // URL ends up as a link on the Acquisition page.
        if (! is_string($host) || $host === '' || ! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = preg_replace('/^www\./', '', strtolower($host));

        foreach (config('web-analytics.allowed_hosts') as $ownHost) {
            if ($host === preg_replace('/^www\./', '', strtolower($ownHost))) {
                return null;
            }
        }

        return $host;
    }

    /**
     * @return array{category: string, name: string}|null
     */
    private function matchHost(string $host): ?array
    {
        $labels = explode('.', $host);

        foreach (array_keys(self::CATEGORY_CHANNELS) as $category) {
            foreach (config("web-analytics.{$category}") as $key => $name) {
                $isMatch = $host === $key
                    || str_ends_with($host, '.'.$key)
                    // A dot-less key ("google") matches any host with that
                    // label: google.com, google.co.id, news.google.com.
                    || (! str_contains($key, '.') && in_array($key, $labels, true));

                if ($isMatch) {
                    return ['category' => $category, 'name' => $name];
                }
            }
        }

        return null;
    }

    /**
     * @return array{category: string, name: string}|null
     */
    private function matchSourceName(string $utmSource): ?array
    {
        $needle = strtolower($utmSource);

        foreach (array_keys(self::CATEGORY_CHANNELS) as $category) {
            foreach (config("web-analytics.{$category}") as $key => $name) {
                $labels = explode('.', $key);

                // "reddit" for reddit.com, but never the "web" of
                // web.telegram.org — only two-label keys donate a label.
                $isMatch = $needle === strtolower($name)
                    || $needle === $key
                    || (count($labels) === 2 && $needle === $labels[0]);

                if ($isMatch) {
                    return ['category' => $category, 'name' => $name];
                }
            }
        }

        return null;
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
