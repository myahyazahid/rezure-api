<?php

/*
|--------------------------------------------------------------------------
| Website Analytics (v4)
|--------------------------------------------------------------------------
|
| First-party analytics for the public website (rezure_websites). See
| docs/v4/rezure-dashboard-v4-phases-tasks.md for the roadmap and
| api-documentation/website-analytics-api.md for the tracker contract.
|
*/

return [

    /*
    | Hits whose page URL host isn't listed here are dropped at ingest, so a
    | fork of the website (or someone replaying the endpoint) can't write
    | into the dashboard. Comma separated in the env.
    */
    'allowed_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('WEB_ANALYTICS_ALLOWED_HOSTS', 'rezure.redscale.my.id')),
    ))),

    /*
    | Raw web_* rows older than this are pruned by `model:prune`; the daily
    | summary tables are kept forever. 400 days ≈ 13 months, enough for a
    | full year-over-year comparison with filters.
    */
    'retention_days' => (int) env('WEB_ANALYTICS_RETENTION_DAYS', 400),

    'max_hits_per_batch' => 50,

    /*
    | Hits whose (clock-corrected) time is older than this are dropped — a
    | browser that sat offline for weeks shouldn't rewrite settled days.
    */
    'max_hit_age_days' => 7,

    /*
    | GA4's definition: a session is "engaged" with at least this much
    | engaged time, 2+ pageviews, or a conversion. Bounce = not engaged.
    */
    'engaged_session_seconds' => 10,

    'queue' => env('WEB_ANALYTICS_QUEUE', 'web'),

    /*
    | Where GET /site/download sends people when no release in the
    | `releases` table has a download_url.
    */
    'download_fallback_url' => 'https://github.com/myahyazahid/rezure/releases/latest',

    /*
    | Repository whose release asset download_count is snapshotted daily
    | (Fase 4.12) — downloads that bypass the website.
    */
    'github_repository' => env('WEB_ANALYTICS_GITHUB_REPO', 'myahyazahid/rezure'),

    /*
    | A new desktop install is attributed to a website visitor whose
    | download came from the same IP within this many hours before it.
    */
    'attribution_window_hours' => 72,

    /*
    | Viewport width (px) upper bounds for the buckets the click heatmap and
    | the Technology page group by.
    */
    'viewport_buckets' => [
        'mobile' => 767,
        'tablet' => 1023,
        'desktop' => 1439,
        'wide' => PHP_INT_MAX,
    ],

    /*
    |--------------------------------------------------------------------------
    | Traffic source classification
    |--------------------------------------------------------------------------
    |
    | Referrer host → source name, grouped by channel. A host matches when it
    | equals the key or ends with ".key" (so `www.google.co.id` matches
    | `google.co.id`, and `google.` prefixes are handled by the classifier).
    | Developer Communities and AI Assistants are their own channels because
    | that is where a local-dev tool's audience actually comes from.
    |
    */
    'search_engines' => [
        'google' => 'Google',
        'bing.com' => 'Bing',
        'duckduckgo.com' => 'DuckDuckGo',
        'yahoo.com' => 'Yahoo',
        'yandex.ru' => 'Yandex',
        'yandex.com' => 'Yandex',
        'baidu.com' => 'Baidu',
        'ecosia.org' => 'Ecosia',
        'search.brave.com' => 'Brave Search',
        'startpage.com' => 'Startpage',
        'qwant.com' => 'Qwant',
        'naver.com' => 'Naver',
    ],

    'social_networks' => [
        'facebook.com' => 'Facebook',
        'm.facebook.com' => 'Facebook',
        'l.facebook.com' => 'Facebook',
        'instagram.com' => 'Instagram',
        'x.com' => 'X',
        'twitter.com' => 'X',
        't.co' => 'X',
        'linkedin.com' => 'LinkedIn',
        'lnkd.in' => 'LinkedIn',
        'youtube.com' => 'YouTube',
        'youtu.be' => 'YouTube',
        'tiktok.com' => 'TikTok',
        'threads.net' => 'Threads',
        'bsky.app' => 'Bluesky',
        'mastodon.social' => 'Mastodon',
        'discord.com' => 'Discord',
        'discord.gg' => 'Discord',
        't.me' => 'Telegram',
        'web.telegram.org' => 'Telegram',
        'whatsapp.com' => 'WhatsApp',
        'pinterest.com' => 'Pinterest',
    ],

    'developer_communities' => [
        'github.com' => 'GitHub',
        'gist.github.com' => 'GitHub',
        'stackoverflow.com' => 'Stack Overflow',
        'reddit.com' => 'Reddit',
        'old.reddit.com' => 'Reddit',
        'news.ycombinator.com' => 'Hacker News',
        'dev.to' => 'dev.to',
        'laracasts.com' => 'Laracasts',
        'laravel-news.com' => 'Laravel News',
        'medium.com' => 'Medium',
        'hashnode.com' => 'Hashnode',
        'producthunt.com' => 'Product Hunt',
        'alternativeto.net' => 'AlternativeTo',
        'kaskus.co.id' => 'Kaskus',
    ],

    'ai_assistants' => [
        'chatgpt.com' => 'ChatGPT',
        'chat.openai.com' => 'ChatGPT',
        'perplexity.ai' => 'Perplexity',
        'claude.ai' => 'Claude',
        'gemini.google.com' => 'Gemini',
        'copilot.microsoft.com' => 'Copilot',
        'chat.deepseek.com' => 'DeepSeek',
        'you.com' => 'You.com',
        'phind.com' => 'Phind',
    ],

    'email_mediums' => ['email', 'e-mail', 'newsletter'],

    'paid_mediums' => ['cpc', 'ppc', 'paid', 'paidsearch', 'paid_search', 'paid-social', 'paid_social', 'paidsocial', 'cpm', 'display', 'ads'],

    'click_id_params' => [
        'gclid' => 'google',
        'gbraid' => 'google',
        'wbraid' => 'google',
        'msclkid' => 'microsoft',
        'fbclid' => 'facebook',
        'ttclid' => 'tiktok',
        'twclid' => 'x',
        'li_fat_id' => 'linkedin',
    ],

];
