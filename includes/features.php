<?php
/**
 * Feature flag helper functions for central commerce rollout.
 */

declare(strict_types=1);

/**
 * Resolve a feature flag from configuration with a default fallback.
 */
function feature_enabled(string $flag, bool $default = true): bool
{
    static $config;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }

    if (!array_key_exists($flag, $config)) {
        return $default;
    }

    return filter_var($config[$flag], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $config[$flag];
}

function feature_taxonomy_enabled(): bool
{
    return feature_enabled('FEATURE_TAXONOMY', true);
}

function feature_order_centralization_enabled(): bool
{
    return feature_enabled('FEATURE_ORDER_CENTRALIZATION', true);
}

function feature_wallets_enabled(): bool
{
    return feature_enabled('FEATURE_WALLETS', true);
}
