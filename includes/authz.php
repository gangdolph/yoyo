<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/**
 * Determine if the current session grants the requested role.
 */
function authz_has_role(string $role): bool
{
    $role = strtolower($role);

    return in_array($role, auth_current_roles(), true);
}

/**
 * Ensure the current session grants one of the permitted roles.
 *
 * @param array<int, string> $roles
 */
function require_role(array $roles, ?string $redirect = '/dashboard.php'): void
{
    $allowed = array_values(array_unique(array_map('strtolower', $roles)));
    $currentRoles = auth_current_roles();

    foreach ($allowed as $role) {
        if (in_array($role, $currentRoles, true)) {
            return;
        }
    }

    if ($redirect !== null) {
        if (!headers_sent()) {
            header('Location: ' . $redirect);
        }
        exit;
    }

    http_response_code(403);
    echo 'Access denied.';
    exit;
}

function ensure_admin(?string $redirect = '/dashboard.php'): void
{
    require_role(['admin'], $redirect);
}

function ensure_official(?string $redirect = '/dashboard.php'): void
{
    require_role(['skuze_official'], $redirect);
}

function ensure_admin_or_official(?string $redirect = '/dashboard.php'): void
{
    require_role(['admin', 'skuze_official'], $redirect);
}

function ensure_seller(?string $redirect = '/vip.php?upgrade=1'): void
{
    require_role(['seller'], $redirect);
}
