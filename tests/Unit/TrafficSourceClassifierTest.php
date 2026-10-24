<?php

namespace Tests\Unit;

use App\Services\WebAnalytics\TrafficSourceClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrafficSourceClassifierTest extends TestCase
{
    /**
     * @param  array<string, ?string>  $utm
     */
    #[DataProvider('cases')]
    public function test_classifies_the_channel_and_source(?string $referrer, array $utm, ?string $clickIdType, string $channel, string $source): void
    {
        config(['web-analytics.allowed_hosts' => ['rezure.redscale.my.id']]);

        $result = app(TrafficSourceClassifier::class)->classify($referrer, $utm, $clickIdType);

        $this->assertSame($channel, $result['channel']);
        $this->assertSame($source, $result['source']);
    }

    /**
     * @return array<string, array{?string, array<string, ?string>, ?string, string, string}>
     */
    public static function cases(): array
    {
        return [
            'no referrer' => [null, [], null, 'Direct', 'Direct'],
            'internal navigation' => ['https://rezure.redscale.my.id/guide', [], null, 'Direct', 'Direct'],
            'google country domain' => ['https://www.google.co.id/', [], null, 'Organic Search', 'Google'],
            'bing' => ['https://www.bing.com/search?q=rezure', [], null, 'Organic Search', 'Bing'],
            'gemini is an assistant, not google search' => ['https://gemini.google.com/app', [], null, 'AI Assistants', 'Gemini'],
            'chatgpt' => ['https://chatgpt.com/', [], null, 'AI Assistants', 'ChatGPT'],
            'github' => ['https://github.com/myahyazahid/rezure', [], null, 'Developer Communities', 'GitHub'],
            'old reddit subdomain' => ['https://old.reddit.com/r/PHP/', [], null, 'Developer Communities', 'Reddit'],
            'twitter shortener' => ['https://t.co/abc', [], null, 'Social', 'X'],
            'unknown site' => ['https://blog.example.com/post', [], null, 'Referral', 'blog.example.com'],
            'utm source without referrer' => [null, ['utm_source' => 'reddit'], null, 'Developer Communities', 'Reddit'],
            'unknown utm campaign' => [null, ['utm_source' => 'partner', 'utm_campaign' => 'launch'], null, 'Campaign', 'partner'],
            'newsletter' => [null, ['utm_source' => 'buttondown', 'utm_medium' => 'email'], null, 'Email', 'buttondown'],
            'google ads click id' => ['https://www.google.com/', [], 'google', 'Paid Search', 'Google'],
            'paid social' => ['https://facebook.com/', ['utm_medium' => 'cpc'], null, 'Paid Social', 'Facebook'],
            'fbclid alone is not paid' => ['https://l.facebook.com/', [], 'facebook', 'Social', 'Facebook'],
            'web label is not telegram' => [null, ['utm_source' => 'web'], null, 'Campaign', 'web'],
        ];
    }
}
