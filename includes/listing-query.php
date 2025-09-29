<?php

declare(strict_types=1);

require_once __DIR__ . '/listing-filters.php';
require_once __DIR__ . '/tags.php';

function listing_brand_options(mysqli $conn): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    if ($result = $conn->query('SELECT id, name FROM service_brands ORDER BY name')) {
        while ($row = $result->fetch_assoc()) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id > 0) {
                $cache[$id] = (string) $row['name'];
            }
        }
        $result->close();
    }

    return $cache;
}

function listing_model_index(mysqli $conn): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    if ($result = $conn->query('SELECT id, brand_id, name FROM service_models ORDER BY name')) {
        while ($row = $result->fetch_assoc()) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($id > 0) {
                $cache[$id] = [
                    'id' => $id,
                    'brand_id' => isset($row['brand_id']) ? (int) $row['brand_id'] : 0,
                    'name' => (string) $row['name'],
                ];
            }
        }
        $result->close();
    }

    return $cache;
}

function listing_model_groups(mysqli $conn): array
{
    static $groups = null;

    if ($groups !== null) {
        return $groups;
    }

    $groups = [];
    foreach (listing_model_index($conn) as $model) {
        $brandId = (int) $model['brand_id'];
        if (!isset($groups[$brandId])) {
            $groups[$brandId] = [];
        }
        $groups[$brandId][] = $model;
    }

    return $groups;
}

function buy_sort_options(): array
{
    return [
        '' => 'Featured',
        'price' => 'Price: Low to High',
        'latest' => 'Newest first',
    ];
}

function buy_limit_options(): array
{
    return [25, 50, 100];
}

function sanitize_buy_filters(array $input, ?mysqli $conn = null): array
{
    $search = trim((string)($input['search'] ?? ''));
    $category = trim((string)($input['category'] ?? ''));
    $categories = listing_filter_categories('buy');
    if (!array_key_exists($category, $categories)) {
        $category = '';
    }

    $subcategory = trim((string)($input['subcategory'] ?? ''));
    $subcategoryOptions = listing_filter_subcategories('buy', $category);
    if (!array_key_exists($subcategory, $subcategoryOptions)) {
        $subcategory = '';
    }

    $condition = trim((string)($input['condition'] ?? ''));
    $conditionOptions = listing_filter_conditions('buy', $category);
    if (!array_key_exists($condition, $conditionOptions)) {
        $condition = '';
    }

    $brandId = (int)($input['brand_id'] ?? 0);
    $modelId = (int)($input['model_id'] ?? 0);

    if ($conn instanceof mysqli) {
        $brandOptions = listing_brand_options($conn);
        if ($brandId > 0 && !isset($brandOptions[$brandId])) {
            $brandId = 0;
        }

        $modelIndex = listing_model_index($conn);
        if ($modelId > 0) {
            $model = $modelIndex[$modelId] ?? null;
            if ($model === null) {
                $modelId = 0;
            } else {
                $modelBrand = (int) $model['brand_id'];
                if ($brandId > 0 && $brandId !== $modelBrand) {
                    $modelId = 0;
                } elseif ($brandId === 0) {
                    $brandId = $modelBrand;
                }
            }
        }
    } else {
        if ($brandId < 0) {
            $brandId = 0;
        }
        if ($modelId < 0) {
            $modelId = 0;
        }
    }

    $sort = (string)($input['sort'] ?? '');
    $allowedSort = array_keys(buy_sort_options());
    if (!in_array($sort, $allowedSort, true)) {
        $sort = '';
    }

    $limit = (int)($input['limit'] ?? 25);
    $limitOptions = buy_limit_options();
    if (!in_array($limit, $limitOptions, true)) {
        $limit = 25;
    }

    $page = max(1, (int)($input['page'] ?? 1));

    $tagsParam = $input['tags'] ?? [];
    $tagFilters = [];
    if (is_string($tagsParam)) {
        $tagFilters = tags_from_input($tagsParam);
    } elseif (is_array($tagsParam)) {
        foreach ($tagsParam as $tagCandidate) {
            $normalized = normalize_tag((string)$tagCandidate);
            if ($normalized !== null) {
                $tagFilters[$normalized] = true;
            }
        }
        $tagFilters = array_keys($tagFilters);
    }

    return [
        'search' => $search,
        'category' => $category,
        'subcategory' => $subcategory,
        'condition' => $condition,
        'brand_id' => $brandId,
        'model_id' => $modelId,
        'sort' => $sort,
        'limit' => $limit,
        'page' => $page,
        'tags' => $tagFilters,
    ];
}

function run_buy_listing_query(mysqli $conn, array $filters): array
{
    $search = $filters['search'];
    $category = $filters['category'];
    $subcategory = $filters['subcategory'];
    $condition = $filters['condition'];
    $brandId = isset($filters['brand_id']) ? (int)$filters['brand_id'] : 0;
    $modelId = isset($filters['model_id']) ? (int)$filters['model_id'] : 0;
    $sort = $filters['sort'];
    $limit = $filters['limit'];
    $page = max(1, $filters['page']);
    $tagFilters = $filters['tags'];

    $where = "WHERE l.status='approved'";
    $params = [];
    $types = '';

    if ($search !== '') {
        $where .= " AND (l.title LIKE ? OR l.description LIKE ?)";
        $like = "%{$search}%";
        $params[] = $like;
        $params[] = $like;
        $types .= 'ss';
    }

    if ($category !== '') {
        $where .= " AND l.category = ?";
        $params[] = $category;
        $types .= 's';
    }

    if ($condition !== '') {
        $where .= " AND l.`condition` = ?";
        $params[] = $condition;
        $types .= 's';
    }

    if ($subcategory !== '') {
        $tagMatchers = listing_filter_subcategory_matchers('buy', $category, $subcategory);
        if ($tagMatchers) {
            $clauses = [];
            foreach ($tagMatchers as $tag) {
                $clauses[] = 'l.tags LIKE ?';
                $params[] = tag_like_parameter($tag);
                $types .= 's';
            }
            if ($clauses) {
                $where .= ' AND (' . implode(' OR ', $clauses) . ')';
            }
        }
    }

    if ($brandId > 0) {
        $where .= ' AND l.brand_id = ?';
        $params[] = $brandId;
        $types .= 'i';
    }

    if ($modelId > 0) {
        $where .= ' AND l.model_id = ?';
        $params[] = $modelId;
        $types .= 'i';
    }

    if (!empty($tagFilters)) {
        foreach ($tagFilters as $tag) {
            $where .= " AND l.tags LIKE ?";
            $params[] = tag_like_parameter($tag);
            $types .= 's';
        }
    }

    $countSql = "SELECT COUNT(*) FROM listings l $where";
    $stmt = $conn->prepare($countSql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();

    $total = (int)$total;
    $totalPages = $total === 0 ? 0 : (int)ceil($total / $limit);
    if ($totalPages > 0 && $page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $limit;

    $orderBy = 'title ASC';
    if ($sort === 'price') {
        $orderBy = 'price ASC';
    } elseif ($sort === 'latest') {
        $orderBy = 'created_at DESC';
    }

    $sql = "SELECT l.id, l.title, l.description, l.price, l.sale_price, l.category, l.tags, l.image, l.`condition`, "
        . 'l.product_sku, l.brand_id, l.model_id, sb.name AS brand_name, sm.name AS model_name, '
        . 'l.is_official_listing, l.quantity, l.reserved_qty, p.is_skuze_official, p.is_skuze_product '
        . 'FROM listings l '
        . 'LEFT JOIN service_brands sb ON sb.id = l.brand_id '
        . 'LEFT JOIN service_models sm ON sm.id = l.model_id '
        . 'LEFT JOIN products p ON l.product_sku = p.sku '
        . "$where ORDER BY $orderBy LIMIT ? OFFSET ?";
    $paramsLimit = $params;
    $typesLimit = $types . 'ii';
    $paramsLimit[] = $limit;
    $paramsLimit[] = $offset;
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($typesLimit, ...$paramsLimit);
    $stmt->execute();
    $result = $stmt->get_result();
    $listings = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $row['brand_id'] = isset($row['brand_id']) ? (int) $row['brand_id'] : null;
            $row['model_id'] = isset($row['model_id']) ? (int) $row['model_id'] : null;
            $listings[] = $row;
        }
        $result->close();
    }
    $stmt->close();

    $normalizedFilters = $filters;
    $normalizedFilters['page'] = $page;
    $normalizedFilters['limit'] = $limit;
    $normalizedFilters['brand_id'] = $brandId;
    $normalizedFilters['model_id'] = $modelId;

    return [
        'items' => $listings,
        'total' => $total,
        'total_pages' => $totalPages,
        'filters' => $normalizedFilters,
    ];
}

function load_available_tags(mysqli $conn, array $activeTags): array
{
    $available = [];
    foreach (canonical_tags() as $canonicalTag) {
        $available[$canonicalTag] = true;
    }

    $tagQuery = $conn->query("SELECT tags FROM listings WHERE status='approved' AND tags IS NOT NULL AND tags <> ''");
    if ($tagQuery) {
        while ($row = $tagQuery->fetch_assoc()) {
            foreach (tags_from_storage($row['tags']) as $tag) {
                $available[$tag] = true;
            }
        }
        $tagQuery->close();
    }

    if ($activeTags) {
        foreach ($activeTags as $tag) {
            $available[$tag] = true;
        }
    }

    $availableTags = array_keys($available);
    sort($availableTags);

    return $availableTags;
}

function trade_sort_options(): array
{
    return [
        'latest' => 'Newest first',
        'oldest' => 'Oldest first',
        'offers' => 'Most offers',
    ];
}

function trade_limit_options(): array
{
    return [25, 50, 100];
}

function sanitize_trade_filters(array $input, ?mysqli $conn = null): array
{
    $search = trim((string)($input['search'] ?? ''));
    $category = trim((string)($input['category'] ?? ''));
    $categories = listing_filter_categories('trade');
    if (!array_key_exists($category, $categories)) {
        $category = '';
    }

    $subcategory = trim((string)($input['subcategory'] ?? ''));
    $subcategoryOptions = listing_filter_subcategories('trade', $category);
    if (!array_key_exists($subcategory, $subcategoryOptions)) {
        $subcategory = '';
    }

    $condition = trim((string)($input['condition'] ?? ''));
    $conditionOptions = listing_filter_conditions('trade', $category);
    if (!array_key_exists($condition, $conditionOptions)) {
        $condition = '';
    }

    $tradeType = trim((string)($input['trade_type'] ?? ''));
    $formatOptions = listing_filter_format_options('trade');
    if (!array_key_exists($tradeType, $formatOptions)) {
        $tradeType = '';
    }

    $brandId = (int)($input['brand_id'] ?? 0);
    $modelId = (int)($input['model_id'] ?? 0);

    if ($conn instanceof mysqli) {
        $brandOptions = listing_brand_options($conn);
        if ($brandId > 0 && !isset($brandOptions[$brandId])) {
            $brandId = 0;
        }

        $modelIndex = listing_model_index($conn);
        if ($modelId > 0) {
            $model = $modelIndex[$modelId] ?? null;
            if ($model === null) {
                $modelId = 0;
            } else {
                $modelBrand = (int) $model['brand_id'];
                if ($brandId > 0 && $brandId !== $modelBrand) {
                    $modelId = 0;
                } elseif ($brandId === 0) {
                    $brandId = $modelBrand;
                }
            }
        }
    } else {
        if ($brandId < 0) {
            $brandId = 0;
        }
        if ($modelId < 0) {
            $modelId = 0;
        }
    }

    $sort = (string)($input['sort'] ?? 'latest');
    $allowedSort = array_keys(trade_sort_options());
    if (!in_array($sort, $allowedSort, true)) {
        $sort = 'latest';
    }

    $limit = (int)($input['limit'] ?? 25);
    $limitOptions = trade_limit_options();
    if (!in_array($limit, $limitOptions, true)) {
        $limit = 25;
    }

    $page = max(1, (int)($input['page'] ?? 1));

    return [
        'search' => $search,
        'category' => $category,
        'subcategory' => $subcategory,
        'condition' => $condition,
        'trade_type' => $tradeType,
        'brand_id' => $brandId,
        'model_id' => $modelId,
        'sort' => $sort,
        'limit' => $limit,
        'page' => $page,
    ];
}

function run_trade_listing_query(mysqli $conn, array $filters): array
{
    $search = $filters['search'];
    $category = $filters['category'];
    $subcategory = $filters['subcategory'];
    $condition = $filters['condition'];
    $tradeType = $filters['trade_type'];
    $brandId = isset($filters['brand_id']) ? (int)$filters['brand_id'] : 0;
    $modelId = isset($filters['model_id']) ? (int)$filters['model_id'] : 0;
    $sort = $filters['sort'];
    $limit = $filters['limit'];
    $page = max(1, $filters['page']);

    $where = 'WHERE 1=1';
    $params = [];
    $types = '';

    if ($condition !== '') {
        $where .= ' AND tl.status = ?';
        $params[] = $condition;
        $types .= 's';
    }

    if ($tradeType !== '') {
        $where .= ' AND tl.trade_type = ?';
        $params[] = $tradeType;
        $types .= 's';
    }

    if ($brandId > 0) {
        $where .= ' AND tl.brand_id = ?';
        $params[] = $brandId;
        $types .= 'i';
    }

    if ($modelId > 0) {
        $where .= ' AND tl.model_id = ?';
        $params[] = $modelId;
        $types .= 'i';
    }

    if ($search !== '') {
        $where .= ' AND (tl.have_item LIKE ? OR tl.want_item LIKE ? OR tl.description LIKE ?)';
        $like = "%{$search}%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= 'sss';
    }

    if ($category !== '') {
        $keywords = listing_filter_category_matchers('trade', $category);
        if ($keywords) {
            $clauses = [];
            foreach ($keywords as $keyword) {
                $clauses[] = '(tl.have_item LIKE ? OR tl.want_item LIKE ? OR tl.description LIKE ?)';
                $like = "%{$keyword}%";
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $types .= 'sss';
            }
            if ($clauses) {
                $where .= ' AND (' . implode(' OR ', $clauses) . ')';
            }
        }
    }

    if ($subcategory !== '') {
        $subKeywords = listing_filter_subcategory_matchers('trade', $category, $subcategory);
        if ($subKeywords) {
            $clauses = [];
            foreach ($subKeywords as $keyword) {
                $clauses[] = '(tl.have_item LIKE ? OR tl.want_item LIKE ? OR tl.description LIKE ?)';
                $like = "%{$keyword}%";
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $types .= 'sss';
            }
            if ($clauses) {
                $where .= ' AND (' . implode(' OR ', $clauses) . ')';
            }
        }
    }

    $countSql = "SELECT COUNT(*) FROM trade_listings tl JOIN users u ON tl.owner_id = u.id $where";
    $stmt = $conn->prepare($countSql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();

    $total = (int)$total;
    $totalPages = $total === 0 ? 0 : (int)ceil($total / $limit);
    if ($totalPages > 0 && $page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $limit;

    $orderBy = 'tl.created_at DESC';
    if ($sort === 'oldest') {
        $orderBy = 'tl.created_at ASC';
    } elseif ($sort === 'offers') {
        $orderBy = 'offers DESC, tl.created_at DESC';
    }

    $sql = "SELECT tl.id, tl.have_item, tl.want_item, tl.trade_type, tl.description, tl.image, tl.status, tl.owner_id, tl.brand_id, tl.model_id, "
        . 'sb.name AS brand_name, sm.name AS model_name, u.username, '
        . "(SELECT COUNT(*) FROM trade_offers o WHERE o.listing_id = tl.id AND o.status IN ('pending','accepted')) AS offers "
        . 'FROM trade_listings tl '
        . 'JOIN users u ON tl.owner_id = u.id '
        . 'LEFT JOIN service_brands sb ON sb.id = tl.brand_id '
        . 'LEFT JOIN service_models sm ON sm.id = tl.model_id '
        . "$where ORDER BY $orderBy LIMIT ? OFFSET ?";
    $paramsLimit = $params;
    $typesLimit = $types . 'ii';
    $paramsLimit[] = $limit;
    $paramsLimit[] = $offset;
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($typesLimit, ...$paramsLimit);
    $stmt->execute();
    $result = $stmt->get_result();
    $listings = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $row['brand_id'] = isset($row['brand_id']) ? (int) $row['brand_id'] : null;
            $row['model_id'] = isset($row['model_id']) ? (int) $row['model_id'] : null;
            $listings[] = $row;
        }
        $result->close();
    }
    $stmt->close();

    $normalizedFilters = $filters;
    $normalizedFilters['page'] = $page;
    $normalizedFilters['limit'] = $limit;
    $normalizedFilters['brand_id'] = $brandId;
    $normalizedFilters['model_id'] = $modelId;

    return [
        'items' => $listings,
        'total' => $total,
        'total_pages' => $totalPages,
        'filters' => $normalizedFilters,
    ];
}
