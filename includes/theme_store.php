<?php
declare(strict_types=1);

if (!function_exists('yoyo_theme_store_path')) {
    function yoyo_theme_store_path(): string
    {
        return dirname(__DIR__) . '/data/themes.json';
    }
}

if (!function_exists('yoyo_theme_store_default')) {
    function yoyo_theme_store_default(): array
    {
        return [
            'defaultThemeId' => null,
            'themes' => [],
            'pairings' => [],
        ];
    }
}

if (!function_exists('yoyo_theme_store_to_array')) {
    /**
     * @param mixed $value
     */
    function yoyo_theme_store_to_array($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            $encoded = json_encode($value);
            if ($encoded !== false) {
                $decoded = json_decode($encoded, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }

            return get_object_vars($value);
        }

        return [];
    }
}

if (!function_exists('yoyo_theme_store_normalise')) {
    /**
     * @param array<string,mixed> $payload
     */
    function yoyo_theme_store_normalise(array $payload): array
    {
        $themes = [];
        $seenIds = [];
        $rawThemes = isset($payload['themes']) && is_array($payload['themes']) ? $payload['themes'] : [];

        foreach ($rawThemes as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $id = isset($entry['id']) && is_string($entry['id']) ? trim($entry['id']) : '';
            if ($id === '' || isset($seenIds[$id])) {
                continue;
            }

            $themeData = isset($entry['theme']) ? yoyo_theme_store_to_array($entry['theme']) : [];
            if (!is_array($themeData)) {
                $themeData = [];
            }

            $themes[] = [
                'id' => $id,
                'label' => isset($entry['label']) && is_string($entry['label']) && trim($entry['label']) !== ''
                    ? trim($entry['label'])
                    : $id,
                'description' => isset($entry['description']) && is_string($entry['description'])
                    ? trim($entry['description'])
                    : '',
                'theme' => $themeData,
            ];

            $seenIds[$id] = true;
        }

        if (empty($themes)) {
            return yoyo_theme_store_default();
        }

        $defaultId = isset($payload['defaultThemeId']) && is_string($payload['defaultThemeId'])
            ? trim($payload['defaultThemeId'])
            : '';

        if ($defaultId === '' || !isset($seenIds[$defaultId])) {
            $defaultId = $themes[0]['id'];
        }

        $pairings = [];
        $rawPairings = isset($payload['pairings']) && is_array($payload['pairings']) ? $payload['pairings'] : [];
        $pairedBases = [];

        foreach ($rawPairings as $pair) {
            if (!is_array($pair)) {
                continue;
            }

            $type = isset($pair['type']) && $pair['type'] === 'negative' ? 'negative' : null;
            $baseId = isset($pair['baseThemeId']) && is_string($pair['baseThemeId']) ? trim($pair['baseThemeId']) : '';
            $variantId = isset($pair['variantThemeId']) && is_string($pair['variantThemeId']) ? trim($pair['variantThemeId']) : '';

            if ($type === null || $baseId === '' || $variantId === '') {
                continue;
            }

            if (!isset($seenIds[$baseId]) || !isset($seenIds[$variantId])) {
                continue;
            }

            if (isset($pairedBases[$baseId])) {
                continue;
            }

            $pairings[] = [
                'type' => 'negative',
                'baseThemeId' => $baseId,
                'variantThemeId' => $variantId,
                'label' => isset($pair['label']) && is_string($pair['label']) ? trim($pair['label']) : '',
                'generated' => !empty($pair['generated']),
            ];

            $pairedBases[$baseId] = true;
        }

        return [
            'defaultThemeId' => $defaultId,
            'themes' => $themes,
            'pairings' => $pairings,
        ];
    }
}

if (!function_exists('yoyo_theme_store_load')) {
    function yoyo_theme_store_load(): array
    {
        $path = yoyo_theme_store_path();
        $result = [
            'collection' => yoyo_theme_store_default(),
            'errors' => [],
        ];

        if (!is_file($path)) {
            $result['errors'][] = 'Theme collection file is missing.';
            return $result;
        }

        if (!is_readable($path)) {
            $result['errors'][] = 'Theme collection file is not readable.';
            return $result;
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            $result['errors'][] = 'Theme collection file is empty.';
            return $result;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $result['errors'][] = 'Theme collection JSON is malformed.';
            return $result;
        }

        $normalised = yoyo_theme_store_normalise($decoded);
        $result['collection'] = $normalised;

        if (empty($normalised['themes'])) {
            $result['errors'][] = 'Theme collection does not contain any themes.';
        }

        return $result;
    }
}

if (!function_exists('yoyo_theme_store_validate_submission')) {
    /**
     * @param mixed $payload
     */
    function yoyo_theme_store_validate_submission($payload): array
    {
        $result = [
            'collection' => yoyo_theme_store_default(),
            'errors' => [],
        ];

        if (!is_array($payload)) {
            $result['errors'][] = 'Payload must be a JSON object.';
            return $result;
        }

        if (!isset($payload['themes']) || !is_array($payload['themes'])) {
            $result['errors'][] = 'Themes must be provided as an array.';
            return $result;
        }

        $themes = [];
        $seenIds = [];

        foreach ($payload['themes'] as $index => $entry) {
            if (!is_array($entry)) {
                $result['errors'][] = 'Each theme entry must be an object.';
                continue;
            }

            $id = isset($entry['id']) && is_string($entry['id']) ? trim($entry['id']) : '';
            if ($id === '') {
                $result['errors'][] = 'Theme entries require a non-empty "id".';
                continue;
            }

            if (isset($seenIds[$id])) {
                $result['errors'][] = sprintf('Duplicate theme id "%s" detected.', $id);
                continue;
            }

            $themeData = isset($entry['theme']) ? yoyo_theme_store_to_array($entry['theme']) : [];
            if (!is_array($themeData) || empty($themeData)) {
                $result['errors'][] = sprintf('Theme "%s" is missing theme tokens.', $id);
                continue;
            }

            $themes[] = [
                'id' => $id,
                'label' => isset($entry['label']) && is_string($entry['label']) && trim($entry['label']) !== ''
                    ? trim($entry['label'])
                    : $id,
                'description' => isset($entry['description']) && is_string($entry['description'])
                    ? trim($entry['description'])
                    : '',
                'theme' => $themeData,
            ];

            $seenIds[$id] = true;
        }

        if (empty($themes)) {
            $result['errors'][] = 'At least one valid theme is required.';
            return $result;
        }

        $defaultId = isset($payload['defaultThemeId']) && is_string($payload['defaultThemeId'])
            ? trim($payload['defaultThemeId'])
            : '';

        if ($defaultId === '' || !isset($seenIds[$defaultId])) {
            $result['errors'][] = 'Default theme id is missing or does not match any theme.';
        }

        $pairings = [];
        $rawPairings = isset($payload['pairings']) && is_array($payload['pairings']) ? $payload['pairings'] : [];
        $pairedBases = [];

        foreach ($rawPairings as $pair) {
            if (!is_array($pair)) {
                $result['errors'][] = 'Each pairing entry must be an object.';
                continue;
            }

            $type = isset($pair['type']) && $pair['type'] === 'negative' ? 'negative' : null;
            $baseId = isset($pair['baseThemeId']) && is_string($pair['baseThemeId']) ? trim($pair['baseThemeId']) : '';
            $variantId = isset($pair['variantThemeId']) && is_string($pair['variantThemeId']) ? trim($pair['variantThemeId']) : '';

            if ($type !== 'negative') {
                $result['errors'][] = 'Pairings must declare a "negative" type.';
                continue;
            }

            if ($baseId === '' || !isset($seenIds[$baseId])) {
                $result['errors'][] = sprintf('Pairing references unknown base theme "%s".', $baseId !== '' ? $baseId : '[missing]');
                continue;
            }

            if ($variantId === '' || !isset($seenIds[$variantId])) {
                $result['errors'][] = sprintf('Pairing references unknown variant theme "%s".', $variantId !== '' ? $variantId : '[missing]');
                continue;
            }

            if ($baseId === $variantId) {
                $result['errors'][] = 'Pairings must reference two different theme ids.';
                continue;
            }

            if (isset($pairedBases[$baseId])) {
                $result['errors'][] = sprintf('Multiple pairings defined for base theme "%s".', $baseId);
                continue;
            }

            $pairings[] = [
                'type' => 'negative',
                'baseThemeId' => $baseId,
                'variantThemeId' => $variantId,
                'label' => isset($pair['label']) && is_string($pair['label']) ? trim($pair['label']) : '',
                'generated' => !empty($pair['generated']),
            ];

            $pairedBases[$baseId] = true;
        }

        $result['collection'] = [
            'defaultThemeId' => $defaultId !== '' && isset($seenIds[$defaultId]) ? $defaultId : $themes[0]['id'],
            'themes' => $themes,
            'pairings' => $pairings,
        ];

        return $result;
    }
}
