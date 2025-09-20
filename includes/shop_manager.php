<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/store.php';

const SHOP_MANAGER_DEFAULT_TAB = 'listings';
const SHOP_MANAGER_TABS = [
    'listings' => [
        'label' => 'Listings',
        'description' => 'Review and manage your live, draft, or pending listings.',
    ],
    'inventory' => [
        'label' => 'Inventory',
        'description' => 'Track product stock levels and reorder points.',
    ],
    'orders' => [
        'label' => 'Orders',
        'description' => 'Monitor order flow and review purchase history.',
    ],
    'shipping' => [
        'label' => 'Shipping',
        'description' => 'Update fulfillment statuses and tracking numbers.',
    ],
    'settings' => [
        'label' => 'Settings',
        'description' => 'Configure default preferences for your shop.',
    ],
];

/**
 * Resolve the requested tab to a known value.
 */
function shop_manager_resolve_tab(?string $requested): string
{
    $requested = strtolower(trim((string) $requested));
    if ($requested !== '' && array_key_exists($requested, SHOP_MANAGER_TABS)) {
        return $requested;
    }

    return SHOP_MANAGER_DEFAULT_TAB;
}

/**
 * Return the registered tab definitions.
 *
 * @return array<string, array{label: string, description: string}>
 */
function shop_manager_tabs(): array
{
    return SHOP_MANAGER_TABS;
}

/**
 * Fetch a flash message payload for a specific tab.
 *
 * @return array{type: string, message: string}|null
 */
function shop_manager_consume_flash(string $tab): ?array
{
    if (!isset($_SESSION['shop_manager_flash']) || !is_array($_SESSION['shop_manager_flash'])) {
        return null;
    }

    $payload = $_SESSION['shop_manager_flash'][$tab] ?? null;
    if ($payload !== null) {
        unset($_SESSION['shop_manager_flash'][$tab]);
    }

    if (!is_array($payload) || !isset($payload['type'], $payload['message'])) {
        return null;
    }

    return [
        'type' => (string) $payload['type'],
        'message' => (string) $payload['message'],
    ];
}

/**
 * Queue a flash message for the requested tab.
 */
function shop_manager_flash(string $tab, string $type, string $message): void
{
    if (!isset($_SESSION['shop_manager_flash']) || !is_array($_SESSION['shop_manager_flash'])) {
        $_SESSION['shop_manager_flash'] = [];
    }

    $_SESSION['shop_manager_flash'][$tab] = [
        'type' => $type,
        'message' => $message,
    ];
}
