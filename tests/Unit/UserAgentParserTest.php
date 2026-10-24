<?php

namespace Tests\Unit;

use App\Services\WebAnalytics\UserAgentParser;
use Tests\TestCase;

class UserAgentParserTest extends TestCase
{
    public function test_parses_a_desktop_browser(): void
    {
        $result = app(UserAgentParser::class)->parse('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

        $this->assertFalse($result['is_bot']);
        $this->assertSame('Chrome', $result['browser']);
        $this->assertSame('120.0', $result['browser_version']);
        $this->assertSame('Blink', $result['browser_engine']);
        $this->assertSame('Windows', $result['os']);
        $this->assertSame('desktop', $result['device_type']);
    }

    public function test_parses_a_phone_with_its_brand(): void
    {
        $result = app(UserAgentParser::class)->parse('Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1');

        $this->assertSame('smartphone', $result['device_type']);
        $this->assertSame('Apple', $result['device_brand']);
        $this->assertSame('iOS', $result['os']);
    }

    public function test_flags_a_crawler_as_a_bot(): void
    {
        $result = app(UserAgentParser::class)->parse('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

        $this->assertTrue($result['is_bot']);
        $this->assertSame('Googlebot', $result['bot_name']);
        $this->assertNull($result['browser']);
    }

    public function test_an_empty_user_agent_is_treated_as_a_bot(): void
    {
        $result = app(UserAgentParser::class)->parse(null);

        $this->assertTrue($result['is_bot']);
    }
}
