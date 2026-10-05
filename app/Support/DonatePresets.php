<?php

namespace App\Support;

/**
 * Known donate platforms offered as one-click starting points on the
 * dashboard's "Add donate method" page — picking one pre-fills the label
 * (and symbol and network, for crypto) but every field stays editable.
 * `'custom'` is always available alongside these and isn't listed here; it
 * starts every field blank.
 */
class DonatePresets
{
    /**
     * @return array<string, string>|array<string, array{label: string, symbol: string, network: string}>
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
                'btc' => ['label' => 'Bitcoin', 'symbol' => 'BTC', 'network' => 'Bitcoin'],
                'eth' => ['label' => 'Ethereum', 'symbol' => 'ETH', 'network' => 'Ethereum (ERC-20)'],
                'usdt' => ['label' => 'Tether', 'symbol' => 'USDT', 'network' => 'Tron (TRC-20)'],
                'bnb' => ['label' => 'BNB', 'symbol' => 'BNB', 'network' => 'BNB Smart Chain (BEP-20)'],
                'sol' => ['label' => 'Solana', 'symbol' => 'SOL', 'network' => 'Solana'],
            ],
            default => [],
        };
    }

    /**
     * Networks suggested while typing a wallet's network — the presets' own
     * plus other common chains. Free text still works for any other.
     *
     * @return list<string>
     */
    public static function networks(): array
    {
        return collect(self::forCategory('crypto'))
            ->pluck('network')
            ->merge([
                'Ethereum (ERC-20)',
                'Tron (TRC-20)',
                'BNB Smart Chain (BEP-20)',
                'Polygon',
                'Arbitrum One',
                'Optimism',
                'Base',
                'Avalanche C-Chain',
                'Litecoin',
                'TON',
            ])
            ->unique()
            ->values()
            ->all();
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
