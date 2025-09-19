<?php

declare(strict_types=1);

/**
 * Normalize a single tag string to a safe, comparable value.
 */
function normalize_tag(string $tag): ?string
{
    $tag = strtolower(trim($tag));
    if ($tag === '') {
        return null;
    }

    // Collapse internal whitespace to single hyphen for consistent storage.
    $tag = preg_replace('/[^a-z0-9\s\-_]/', '', $tag);
    $tag = preg_replace('/\s+/', '-', $tag);
    $tag = trim($tag, '-_');

    if ($tag === '') {
        return null;
    }

    return $tag;
}

/**
 * Parse a comma or newline separated list of tags provided by a user.
 */
function tags_from_input(?string $input): array
{
    if ($input === null) {
        return [];
    }

    $parts = preg_split('/[,\n]/', $input) ?: [];
    $normalized = [];
    foreach ($parts as $part) {
        $tag = normalize_tag($part);
        if ($tag !== null) {
            $normalized[$tag] = true;
        }
    }

    return array_keys($normalized);
}

/**
 * Convert normalized tags into the persisted comma-delimited format with sentinels.
 */
function tags_to_storage(array $tags): ?string
{
    if (empty($tags)) {
        return null;
    }

    return ',' . implode(',', $tags) . ',';
}

/**
 * Decode a stored tags string back into an array of tag values.
 */
function tags_from_storage(?string $stored): array
{
    if ($stored === null || $stored === '') {
        return [];
    }

    $trimmed = trim($stored, ',');
    if ($trimmed === '') {
        return [];
    }

    $parts = explode(',', $trimmed);
    $seen = [];
    foreach ($parts as $part) {
        $tag = normalize_tag($part);
        if ($tag !== null) {
            $seen[$tag] = true;
        }
    }

    return array_keys($seen);
}

/**
 * Provide the canonical tag catalogue for discovery and navigation.
 */
function canonical_tags(): array
{
    static $catalogue;

    if ($catalogue === null) {
        $seed = [
            'accessories',
            'bundle',
            'collectible',
            'digital',
            'hardware',
            'limited edition',
            'new arrival',
            'preorder',
            'refurbished',
            'retro',
            'signed',
            'vintage',
        ];

        $normalized = [];
        foreach ($seed as $candidate) {
            $tag = normalize_tag($candidate);
            if ($tag !== null) {
                $normalized[$tag] = true;
            }
        }

        $catalogue = array_keys($normalized);
        sort($catalogue);
    }

    return $catalogue;
}

/**
 * Produce a comma-separated string suitable for re-populating an input control.
 */
function tags_to_input_value(array $tags): string
{
    if (empty($tags)) {
        return '';
    }

    return implode(', ', $tags);
}

/**
 * Prepare parameters for SQL LIKE lookups when matching stored tags.
 */
function tag_like_parameter(string $tag): string
{
    return '%,' . $tag . ',%';
}
