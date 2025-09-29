<?php
require_once __DIR__ . '/../includes/require-auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/listing-query.php';
require __DIR__ . '/../includes/user.php';
require __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');

function json_error(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'error' => $message,
    ]);
    exit;
}

$context = $_GET['context'] ?? 'buy';
if (!in_array($context, ['buy', 'trade'], true)) {
    json_error('Unsupported context.');
}

try {
    if ($context === 'buy') {
        $filters = sanitize_buy_filters($_GET, $conn);
        $result = run_buy_listing_query($conn, $filters);
        $active = $result['filters'];
        $listings = $result['items'];
        $total = $result['total'];
        $totalPages = $result['total_pages'];
        $page = $active['page'];
        $limit = $active['limit'];
        $tagFilters = $active['tags'];

        $categoryOptions = listing_filter_categories('buy');
        $subcategoryOptions = listing_filter_subcategories('buy', $active['category']);
        $conditionOptions = listing_filter_conditions('buy', $active['category']);
        $brandOptions = listing_brand_options($conn);
        $modelIndex = listing_model_index($conn);
        $sortOptions = buy_sort_options();
        $limitOptions = buy_limit_options();
        $availableTags = load_available_tags($conn, $tagFilters);

        $baseQuery = [
            'search' => $active['search'] !== '' ? $active['search'] : null,
            'category' => $active['category'] !== '' ? $active['category'] : null,
            'subcategory' => $active['subcategory'] !== '' ? $active['subcategory'] : null,
            'condition' => $active['condition'] !== '' ? $active['condition'] : null,
            'brand_id' => $active['brand_id'] > 0 ? $active['brand_id'] : null,
            'model_id' => $active['model_id'] > 0 ? $active['model_id'] : null,
            'sort' => $active['sort'] !== '' ? $active['sort'] : null,
            'limit' => $limit,
            'tags' => !empty($tagFilters) ? $tagFilters : null,
        ];
        $baseQuery = array_filter($baseQuery, static function ($value) {
            if (is_array($value)) {
                return !empty($value);
            }

            return $value !== null;
        });

        $resultCount = count($listings);
        ob_start();
        include __DIR__ . '/../includes/partials/buy-results.php';
        $resultsHtml = ob_get_clean();

        $optionCounts = function (array $overrides) use ($conn, $active) {
            $countFilters = array_merge($active, $overrides);
            $countFilters['page'] = 1;
            $countFilters['limit'] = 1;
            $countResult = run_buy_listing_query($conn, $countFilters);

            return $countResult['total'];
        };

        $categoryData = [];
        foreach ($categoryOptions as $value => $meta) {
            $count = $optionCounts([
                'category' => $value,
                'subcategory' => '',
            ]);
            $categoryData[] = [
                'value' => $value,
                'label' => $meta['label'],
                'count' => $count,
                'selected' => $active['category'] === $value,
                'disabled' => $count === 0 && $active['category'] !== $value,
            ];
        }

        $subcategoryData = [];
        if (!empty($subcategoryOptions)) {
            foreach ($subcategoryOptions as $value => $meta) {
                $count = $optionCounts([
                    'subcategory' => $value,
                ]);
                $subcategoryData[] = [
                    'value' => $value,
                    'label' => $meta['label'] ?? $meta,
                    'count' => $count,
                    'selected' => $active['subcategory'] === $value,
                    'disabled' => $count === 0 && $active['subcategory'] !== $value,
                ];
            }
        }

        $conditionData = [];
        foreach ($conditionOptions as $value => $label) {
            $count = $optionCounts([
                'condition' => $value,
            ]);
            $conditionData[] = [
                'value' => $value,
                'label' => $label,
                'count' => $count,
                'selected' => $active['condition'] === $value,
                'disabled' => $count === 0 && $active['condition'] !== $value,
            ];
        }

        $brandData = [];
        $brandData[] = [
            'value' => '',
            'label' => 'Any brand',
            'selected' => $active['brand_id'] <= 0,
        ];
        foreach ($brandOptions as $id => $name) {
            $brandData[] = [
                'value' => (string)$id,
                'label' => $name,
                'selected' => (int)$active['brand_id'] === (int)$id,
            ];
        }

        $modelData = [];
        $modelData[] = [
            'value' => '',
            'label' => 'Any model',
            'selected' => $active['model_id'] <= 0,
        ];
        foreach ($modelIndex as $model) {
            $brandLabel = $brandOptions[$model['brand_id']] ?? ('Brand ' . $model['brand_id']);
            $modelLabel = $brandLabel . ' – ' . $model['name'];
            $modelData[] = [
                'value' => (string)$model['id'],
                'label' => $modelLabel,
                'selected' => (int)$active['model_id'] === (int)$model['id'],
            ];
        }

        $tagData = [];
        foreach ($availableTags as $tag) {
            $tagData[] = [
                'value' => $tag,
                'selected' => in_array($tag, $tagFilters, true),
            ];
        }

        $sortData = [];
        foreach ($sortOptions as $value => $label) {
            $sortData[] = [
                'value' => $value,
                'label' => $label,
                'selected' => $active['sort'] === $value,
            ];
        }

        $limitData = [];
        foreach ($limitOptions as $value) {
            $limitData[] = [
                'value' => $value,
                'label' => (string)$value,
                'selected' => (int)$value === (int)$limit,
            ];
        }

        echo json_encode([
            'success' => true,
            'context' => 'buy',
            'results' => [
                'html' => $resultsHtml,
                'total' => $total,
                'page' => $page,
                'totalPages' => $totalPages,
            ],
            'filters' => [
                'category' => $categoryData,
                'subcategory' => $subcategoryData,
                'condition' => $conditionData,
                'brand' => $brandData,
                'model' => $modelData,
                'tags' => $tagData,
                'sort' => $sortData,
                'limit' => $limitData,
            ],
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Trade context
    $filters = sanitize_trade_filters($_GET, $conn);
    $result = run_trade_listing_query($conn, $filters);
    $active = $result['filters'];
    $listings = $result['items'];
    $total = $result['total'];
    $totalPages = $result['total_pages'];
    $page = $active['page'];
    $limit = $active['limit'];

    $categoryOptions = listing_filter_categories('trade');
    $subcategoryOptions = listing_filter_subcategories('trade', $active['category']);
    $conditionOptions = listing_filter_conditions('trade', $active['category']);
    $formatOptions = listing_filter_format_options('trade');
    $brandOptions = listing_brand_options($conn);
    $modelIndex = listing_model_index($conn);
    $sortOptions = trade_sort_options();
    $limitOptions = trade_limit_options();

    $baseQuery = [
        'search' => $active['search'] !== '' ? $active['search'] : null,
        'category' => $active['category'] !== '' ? $active['category'] : null,
        'subcategory' => $active['subcategory'] !== '' ? $active['subcategory'] : null,
        'condition' => $active['condition'] !== '' ? $active['condition'] : null,
        'trade_type' => $active['trade_type'] !== '' ? $active['trade_type'] : null,
        'brand_id' => $active['brand_id'] > 0 ? $active['brand_id'] : null,
        'model_id' => $active['model_id'] > 0 ? $active['model_id'] : null,
        'sort' => $active['sort'] !== '' ? $active['sort'] : null,
        'limit' => $limit,
    ];
    $baseQuery = array_filter($baseQuery, static function ($value) {
        return $value !== null;
    });

    $resultCount = count($listings);
    ob_start();
    include __DIR__ . '/../includes/partials/trade-results.php';
    $resultsHtml = ob_get_clean();

    $optionCounts = function (array $overrides) use ($conn, $active) {
        $countFilters = array_merge($active, $overrides);
        $countFilters['page'] = 1;
        $countFilters['limit'] = 1;
        $countResult = run_trade_listing_query($conn, $countFilters);

        return $countResult['total'];
    };

    $categoryData = [];
    foreach ($categoryOptions as $value => $meta) {
        $count = $optionCounts([
            'category' => $value,
            'subcategory' => '',
        ]);
        $categoryData[] = [
            'value' => $value,
            'label' => $meta['label'],
            'count' => $count,
            'selected' => $active['category'] === $value,
            'disabled' => $count === 0 && $active['category'] !== $value,
        ];
    }

    $subcategoryData = [];
    if (!empty($subcategoryOptions)) {
        foreach ($subcategoryOptions as $value => $meta) {
            $count = $optionCounts([
                'subcategory' => $value,
            ]);
            $subcategoryData[] = [
                'value' => $value,
                'label' => $meta['label'] ?? $meta,
                'count' => $count,
                'selected' => $active['subcategory'] === $value,
                'disabled' => $count === 0 && $active['subcategory'] !== $value,
            ];
        }
    }

    $conditionData = [];
    foreach ($conditionOptions as $value => $label) {
        $count = $optionCounts([
            'condition' => $value,
        ]);
        $conditionData[] = [
            'value' => $value,
            'label' => $label,
            'count' => $count,
            'selected' => $active['condition'] === $value,
            'disabled' => $count === 0 && $active['condition'] !== $value,
        ];
    }

    $brandData = [];
    $brandData[] = [
        'value' => '',
        'label' => 'Any brand',
        'selected' => $active['brand_id'] <= 0,
    ];
    foreach ($brandOptions as $id => $name) {
        $brandData[] = [
            'value' => (string)$id,
            'label' => $name,
            'selected' => (int)$active['brand_id'] === (int)$id,
        ];
    }

    $modelData = [];
    $modelData[] = [
        'value' => '',
        'label' => 'Any model',
        'selected' => $active['model_id'] <= 0,
    ];
    foreach ($modelIndex as $model) {
        $brandLabel = $brandOptions[$model['brand_id']] ?? ('Brand ' . $model['brand_id']);
        $modelLabel = $brandLabel . ' – ' . $model['name'];
        $modelData[] = [
            'value' => (string)$model['id'],
            'label' => $modelLabel,
            'selected' => (int)$active['model_id'] === (int)$model['id'],
        ];
    }

    $formatData = [];
    foreach ($formatOptions as $value => $label) {
        $count = $optionCounts([
            'trade_type' => $value,
        ]);
        $formatData[] = [
            'value' => $value,
            'label' => $label,
            'count' => $count,
            'selected' => $active['trade_type'] === $value,
            'disabled' => $count === 0 && $active['trade_type'] !== $value,
        ];
    }

    $sortData = [];
    foreach ($sortOptions as $value => $label) {
        $sortData[] = [
            'value' => $value,
            'label' => $label,
            'selected' => $active['sort'] === $value,
        ];
    }

    $limitData = [];
    foreach ($limitOptions as $value) {
        $limitData[] = [
            'value' => $value,
            'label' => (string)$value,
            'selected' => (int)$value === (int)$limit,
        ];
    }

    echo json_encode([
        'success' => true,
        'context' => 'trade',
        'results' => [
            'html' => $resultsHtml,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ],
        'filters' => [
            'category' => $categoryData,
            'subcategory' => $subcategoryData,
            'condition' => $conditionData,
            'brand' => $brandData,
            'model' => $modelData,
            'trade_type' => $formatData,
            'sort' => $sortData,
            'limit' => $limitData,
        ],
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    json_error('Failed to load listings.', 500);
}
