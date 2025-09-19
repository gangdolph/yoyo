<?php

declare(strict_types=1);

/**
 * Provide the reusable filter catalogues and helper accessors for listings pages.
 */
function listing_filter_catalogue(): array
{
    static $catalogue;

    if ($catalogue === null) {
        $catalogue = [
            'buy' => [
                'categories' => [
                    '' => [
                        'label' => 'All categories',
                        'subcategories' => [
                            '' => [
                                'label' => 'All subcategories',
                                'tags' => [],
                            ],
                        ],
                    ],
                    'phone' => [
                        'label' => 'Phones & tablets',
                        'subcategories' => [
                            '' => [
                                'label' => 'All phone types',
                                'tags' => [],
                            ],
                            'smartphone' => [
                                'label' => 'Smartphones',
                                'tags' => ['smartphone', 'android', 'iphone'],
                            ],
                            'tablet' => [
                                'label' => 'Tablets',
                                'tags' => ['tablet', 'ipad'],
                            ],
                            'accessory' => [
                                'label' => 'Accessories',
                                'tags' => ['accessory', 'case', 'charger'],
                            ],
                        ],
                    ],
                    'console' => [
                        'label' => 'Game consoles',
                        'subcategories' => [
                            '' => [
                                'label' => 'All console types',
                                'tags' => [],
                            ],
                            'playstation' => [
                                'label' => 'PlayStation',
                                'tags' => ['playstation', 'ps5', 'ps4'],
                            ],
                            'xbox' => [
                                'label' => 'Xbox',
                                'tags' => ['xbox', 'series-x', 'series-s', 'xbox-one'],
                            ],
                            'nintendo' => [
                                'label' => 'Nintendo',
                                'tags' => ['nintendo', 'switch', 'wii', 'gamecube'],
                            ],
                            'retro' => [
                                'label' => 'Retro & classics',
                                'tags' => ['retro', 'classic', 'collector'],
                            ],
                        ],
                    ],
                    'pc' => [
                        'label' => 'PC & components',
                        'subcategories' => [
                            '' => [
                                'label' => 'All PC gear',
                                'tags' => [],
                            ],
                            'laptop' => [
                                'label' => 'Laptops',
                                'tags' => ['laptop', 'notebook', 'ultrabook'],
                            ],
                            'desktop' => [
                                'label' => 'Desktops',
                                'tags' => ['desktop', 'tower', 'gaming-pc'],
                            ],
                            'component' => [
                                'label' => 'Components',
                                'tags' => ['component', 'gpu', 'motherboard', 'cpu'],
                            ],
                            'peripheral' => [
                                'label' => 'Peripherals',
                                'tags' => ['peripheral', 'keyboard', 'mouse', 'monitor'],
                            ],
                        ],
                    ],
                    'other' => [
                        'label' => 'Accessories & misc.',
                        'subcategories' => [
                            '' => [
                                'label' => 'All accessory types',
                                'tags' => [],
                            ],
                            'audio' => [
                                'label' => 'Audio',
                                'tags' => ['audio', 'speaker', 'headphones'],
                            ],
                            'wearable' => [
                                'label' => 'Wearables',
                                'tags' => ['wearable', 'watch', 'fitness'],
                            ],
                            'smart-home' => [
                                'label' => 'Smart home',
                                'tags' => ['smart-home', 'iot', 'automation'],
                            ],
                            'collectible' => [
                                'label' => 'Collectibles',
                                'tags' => ['collectible', 'limited', 'signed'],
                            ],
                        ],
                    ],
                ],
                'conditions' => [
                    '' => 'Any condition',
                    'new' => 'New',
                    'used' => 'Used',
                    'refurbished' => 'Refurbished',
                ],
            ],
            'trade' => [
                'categories' => [
                    '' => [
                        'label' => 'All trade categories',
                        'keywords' => [],
                        'subcategories' => [
                            '' => [
                                'label' => 'All subcategories',
                                'keywords' => [],
                            ],
                        ],
                    ],
                    'console' => [
                        'label' => 'Console swaps',
                        'keywords' => ['console', 'playstation', 'xbox', 'nintendo', 'switch', 'wii'],
                        'subcategories' => [
                            '' => [
                                'label' => 'All console swaps',
                                'keywords' => [],
                            ],
                            'playstation' => [
                                'label' => 'PlayStation',
                                'keywords' => ['playstation', 'ps4', 'ps5'],
                            ],
                            'xbox' => [
                                'label' => 'Xbox',
                                'keywords' => ['xbox', 'series-x', 'series-s', 'xbox-one'],
                            ],
                            'nintendo' => [
                                'label' => 'Nintendo',
                                'keywords' => ['nintendo', 'switch', 'wii', 'gamecube'],
                            ],
                            'retro' => [
                                'label' => 'Retro & legacy',
                                'keywords' => ['retro', 'classic', 'legacy'],
                            ],
                        ],
                    ],
                    'pc' => [
                        'label' => 'PC gear',
                        'keywords' => ['pc', 'laptop', 'desktop', 'gpu', 'motherboard', 'keyboard'],
                        'subcategories' => [
                            '' => [
                                'label' => 'All PC swaps',
                                'keywords' => [],
                            ],
                            'laptop' => [
                                'label' => 'Laptops',
                                'keywords' => ['laptop', 'notebook'],
                            ],
                            'desktop' => [
                                'label' => 'Desktops',
                                'keywords' => ['desktop', 'tower', 'gaming-pc'],
                            ],
                            'component' => [
                                'label' => 'Components',
                                'keywords' => ['component', 'gpu', 'cpu', 'motherboard', 'ram'],
                            ],
                            'peripheral' => [
                                'label' => 'Peripherals',
                                'keywords' => ['keyboard', 'mouse', 'monitor', 'peripheral'],
                            ],
                        ],
                    ],
                    'mobile' => [
                        'label' => 'Mobile devices',
                        'keywords' => ['phone', 'iphone', 'android', 'tablet', 'ipad'],
                        'subcategories' => [
                            '' => [
                                'label' => 'All mobile trades',
                                'keywords' => [],
                            ],
                            'smartphone' => [
                                'label' => 'Smartphones',
                                'keywords' => ['smartphone', 'iphone', 'android'],
                            ],
                            'tablet' => [
                                'label' => 'Tablets',
                                'keywords' => ['tablet', 'ipad'],
                            ],
                            'accessory' => [
                                'label' => 'Accessories',
                                'keywords' => ['accessory', 'case', 'charger'],
                            ],
                        ],
                    ],
                ],
                'conditions' => [
                    '' => 'Any status',
                    'open' => 'Open',
                    'accepted' => 'Accepted',
                    'closed' => 'Closed',
                ],
                'formats' => [
                    '' => 'Any format',
                    'item' => 'Item for item',
                    'cash_card' => 'Cash or card',
                ],
            ],
        ];
    }

    return $catalogue;
}

function listing_filter_context(string $context): array
{
    $catalogue = listing_filter_catalogue();

    return $catalogue[$context] ?? [];
}

function listing_filter_categories(string $context): array
{
    $config = listing_filter_context($context);

    return $config['categories'] ?? [];
}

function listing_filter_category_definition(string $context, string $category): ?array
{
    $categories = listing_filter_categories($context);

    return $categories[$category] ?? null;
}

function listing_filter_subcategories(string $context, string $category): array
{
    $definition = listing_filter_category_definition($context, $category);

    if ($definition === null) {
        return [];
    }

    return $definition['subcategories'] ?? [];
}

function listing_filter_conditions(string $context, ?string $category = null): array
{
    $config = listing_filter_context($context);
    $conditions = $config['conditions'] ?? [];

    if ($category) {
        $categoryDefinition = listing_filter_category_definition($context, $category);
        if ($categoryDefinition && isset($categoryDefinition['conditions']) && is_array($categoryDefinition['conditions'])) {
            $conditions = $categoryDefinition['conditions'];
        }
    }

    return $conditions;
}

function listing_filter_format_options(string $context): array
{
    $config = listing_filter_context($context);

    return $config['formats'] ?? [];
}

function listing_filter_subcategory_matchers(string $context, string $category, string $subcategory): array
{
    $definition = listing_filter_category_definition($context, $category);

    if (!$definition) {
        return [];
    }

    $subcategories = $definition['subcategories'] ?? [];
    $subcategoryDefinition = $subcategories[$subcategory] ?? null;

    if (!$subcategoryDefinition) {
        return [];
    }

    if (isset($subcategoryDefinition['tags']) && is_array($subcategoryDefinition['tags'])) {
        return $subcategoryDefinition['tags'];
    }

    if (isset($subcategoryDefinition['keywords']) && is_array($subcategoryDefinition['keywords'])) {
        return $subcategoryDefinition['keywords'];
    }

    return [];
}

function listing_filter_category_matchers(string $context, string $category): array
{
    $definition = listing_filter_category_definition($context, $category);

    if (!$definition) {
        return [];
    }

    if (isset($definition['tags']) && is_array($definition['tags'])) {
        return $definition['tags'];
    }

    if (isset($definition['keywords']) && is_array($definition['keywords'])) {
        return $definition['keywords'];
    }

    return [];
}
