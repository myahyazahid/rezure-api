<?php

namespace App\Support;

/**
 * Known donate platforms offered as one-click starting points on the
 * dashboard's "Add donate method" page — picking one pre-fills the label
 * (and symbol, for crypto) but every field stays editable. `'custom'` is
 * always available alongside these and isn't listed here; it starts every
 * field blank.
 */
class DonatePresets
{
    /**
     * @return array<string, string>|array<string, array{label: string, symbol: string}>
     */
    public static function forCategory(string $category): array
    {
        return match ($category) {
            'local' => [
                'trakteer' => 'Trakteer',
                'saweria' => 'Saweria',
                'sociabuzz' => 'SociaBuzz',
            ],
            'global' => [
                'github_sponsors' => 'GitHub Sponsors',
                'kofi' => 'Ko-fi',
                'buy_me_a_coffee' => 'Buy Me a Coffee',
                'paypal' => 'PayPal',
                'patreon' => 'Patreon',
            ],
            'crypto' => [
                'btc' => ['label' => 'Bitcoin', 'symbol' => 'BTC'],
                'eth' => ['label' => 'Ethereum', 'symbol' => 'ETH'],
                'usdt' => ['label' => 'Tether (TRC-20)', 'symbol' => 'USDT'],
                'bnb' => ['label' => 'BNB', 'symbol' => 'BNB'],
                'sol' => ['label' => 'Solana', 'symbol' => 'SOL'],
            ],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    public static function categories(): array
    {
        return ['local', 'global', 'crypto'];
    }

    public static function label(string $category, string $preset): ?string
    {
        $default = self::forCategory($category)[$preset] ?? null;

        return is_array($default) ? $default['label'] : $default;
    }

    public static function symbol(string $category, string $preset): ?string
    {
        $default = self::forCategory($category)[$preset] ?? null;

        return is_array($default) ? $default['symbol'] : null;
    }
}
