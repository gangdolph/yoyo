<?php
/*
 * Discovery note: Square sync logic previously lived in ad-hoc helpers without catalog or
 * inventory reconciliation routines.
 * Change: Introduced a feature-gated SyncService that can pull catalog data, refresh inventory
 *         counts, and stage push operations while recording sync health state.
 */

declare(strict_types=1);

require_once __DIR__ . '/square-log.php';
require_once __DIR__ . '/SquareHttpClient.php';
require_once __DIR__ . '/square-migrations.php';
if (!class_exists('InventoryService')) {
    require_once __DIR__ . '/repositories/InventoryService.php';
}

final class SyncService
{
    private mysqli $conn;
    private array $config;
    private ?SquareHttpClient $client = null;
    private InventoryService $inventory;
    private bool $enabled;
    private string $direction;
    private string $stateKey = 'square_core';
    private bool $catalogMapAvailable;
    private bool $modifierTableAvailable;

    public function __construct(mysqli $conn, array $config)
    {
        $this->conn = $conn;
        $this->config = $config;
        $this->enabled = !empty($config['SQUARE_SYNC_ENABLED']);
        $direction = strtolower((string) ($config['SQUARE_SYNC_DIRECTION'] ?? 'pull'));
        $this->direction = in_array($direction, ['pull', 'push', 'two_way'], true) ? $direction : 'pull';
        $this->inventory = new InventoryService($conn);

        square_run_migrations($conn);
        $this->catalogMapAvailable = $this->tableExists('square_catalog_map');
        $this->modifierTableAvailable = $this->tableExists('product_modifiers');
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Pull Square catalog objects and update local metadata.
     *
     * @return array{status: string, variation_ids?: array<int, string>, synced_listings?: int, modifier_updates?: int}
     */
    public function pullCatalog(): array
    {
        if (!$this->enabled) {
            return ['status' => 'disabled'];
        }

        $client = $this->getClient();

        $cursor = null;
        $items = [];
        $variations = [];
        $modifierLists = [];
        $apiCalls = 0;

        do {
            $params = ['types' => 'ITEM,ITEM_VARIATION,MODIFIER_LIST'];
            if ($cursor !== null) {
                $params['cursor'] = $cursor;
            }

            try {
                $response = $client->request('GET', '/v2/catalog/list', $params);
            } catch (Throwable $e) {
                $this->recordSyncError('catalog_request_failed', $e->getMessage(), ['cursor' => $cursor]);
                throw $e;
            }

            $apiCalls++;
            if ($response['statusCode'] < 200 || $response['statusCode'] >= 300) {
                $this->recordSyncError('catalog_http_error', 'Square catalog list failed.', [
                    'status' => $response['statusCode'],
                    'body' => $response['raw'],
                ]);
                throw new RuntimeException('Square catalog request failed.');
            }

            $body = $response['body'];
            if (!is_array($body)) {
                $this->recordSyncError('catalog_invalid_body', 'Square catalog response missing payload.');
                throw new RuntimeException('Square catalog response invalid.');
            }

            $objects = $body['objects'] ?? [];
            if (is_array($objects)) {
                foreach ($objects as $object) {
                    if (!is_array($object)) {
                        continue;
                    }
                    $type = (string) ($object['type'] ?? '');
                    $id = (string) ($object['id'] ?? '');
                    if ($id === '') {
                        continue;
                    }
                    switch ($type) {
                        case 'ITEM':
                            $items[$id] = $object;
                            break;
                        case 'ITEM_VARIATION':
                            $variations[$id] = $object;
                            break;
                        case 'MODIFIER_LIST':
                            $modifierLists[$id] = $object;
                            break;
                        default:
                            break;
                    }
                }
            }

            $cursor = isset($body['cursor']) && is_string($body['cursor']) && $body['cursor'] !== ''
                ? $body['cursor']
                : null;
        } while ($cursor !== null);

        $variationSkuMap = [];
        foreach ($variations as $variationId => $variation) {
            if (!is_array($variation)) {
                continue;
            }
            $data = $variation['item_variation_data'] ?? [];
            if (!is_array($data)) {
                continue;
            }
            $sku = trim((string) ($data['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $variationSkuMap[$variationId] = [
                'sku' => $sku,
                'item_id' => (string) ($data['item_id'] ?? ''),
                'object' => $variation,
            ];
        }

        $skuToListingIds = [];
        $skus = array_values(array_unique(array_map(static fn(array $meta): string => $meta['sku'], $variationSkuMap)));
        if ($skus) {
            $placeholders = implode(',', array_fill(0, count($skus), '?'));
            $types = str_repeat('s', count($skus));
            $sql = 'SELECT id, product_sku FROM listings WHERE product_sku IN (' . $placeholders . ')';
            if ($stmt = $this->conn->prepare($sql)) {
                $stmt->bind_param($types, ...$skus);
                if ($stmt->execute() && ($result = $stmt->get_result())) {
                    while ($row = $result->fetch_assoc()) {
                        $sku = (string) ($row['product_sku'] ?? '');
                        if ($sku === '') {
                            continue;
                        }
                        $skuToListingIds[$sku][] = (int) $row['id'];
                    }
                    $result->free();
                }
                $stmt->close();
            }
        }

        $syncedListings = 0;
        if ($this->catalogMapAvailable) {
            foreach ($variationSkuMap as $variationId => $meta) {
                $sku = $meta['sku'];
                $listingIds = $skuToListingIds[$sku] ?? [];
                foreach ($listingIds as $listingId) {
                    if ($this->upsertCatalogMap('listing', $listingId, $variationId)) {
                        $syncedListings++;
                    }
                }
            }
        }

        $modifierUpdates = 0;
        if ($this->modifierTableAvailable) {
            $skuModifierMap = [];
            foreach ($items as $itemId => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $itemData = $item['item_data'] ?? [];
                if (!is_array($itemData)) {
                    continue;
                }
                $modifierRefs = $itemData['modifier_list_info'] ?? [];
                $modifierIds = [];
                if (is_array($modifierRefs)) {
                    foreach ($modifierRefs as $ref) {
                        if (is_array($ref) && isset($ref['modifier_list_id'])) {
                            $modifierId = (string) $ref['modifier_list_id'];
                            if ($modifierId !== '') {
                                $modifierIds[] = $modifierId;
                            }
                        }
                    }
                }

                $variationRefs = $itemData['variations'] ?? [];
                if (is_array($variationRefs)) {
                    foreach ($variationRefs as $ref) {
                        if (!is_array($ref)) {
                            continue;
                        }
                        $variationId = isset($ref['id']) ? (string) $ref['id'] : '';
                        if ($variationId === '') {
                            continue;
                        }
                        if (!isset($variationSkuMap[$variationId])) {
                            continue;
                        }
                        $sku = $variationSkuMap[$variationId]['sku'];
                        $skuModifierMap[$sku] = [
                            'item_id' => $itemId,
                            'variation_id' => $variationId,
                            'modifier_list_ids' => $modifierIds,
                        ];
                    }
                }
            }

            foreach ($skuModifierMap as $sku => $meta) {
                $modifierPayload = [];
                foreach ($meta['modifier_list_ids'] as $modifierId) {
                    if (isset($modifierLists[$modifierId]) && is_array($modifierLists[$modifierId])) {
                        $modifierPayload[] = $modifierLists[$modifierId];
                    }
                }

                $data = [
                    'square_item_id' => $meta['item_id'],
                    'square_variation_id' => $meta['variation_id'],
                    'modifier_list_ids' => $meta['modifier_list_ids'],
                    'modifier_lists' => $modifierPayload,
                ];
                $primaryModifier = $meta['modifier_list_ids'][0] ?? null;
                if ($this->upsertProductModifier($sku, $data, $primaryModifier)) {
                    $modifierUpdates++;
                }
            }
        }

        $now = date('Y-m-d H:i:s');
        $this->touchSyncState([
            'last_synced_at' => $now,
            'sync_direction' => $this->direction,
        ]);

        square_log('square.sync_pull_catalog_complete', [
            'variations' => count($variationSkuMap),
            'listings_mapped' => $syncedListings,
            'modifier_updates' => $modifierUpdates,
            'api_calls' => $apiCalls,
        ]);

        return [
            'status' => 'ok',
            'variation_ids' => array_keys($variationSkuMap),
            'synced_listings' => $syncedListings,
            'modifier_updates' => $modifierUpdates,
        ];
    }

    /**
     * Pull Square inventory counts for the provided variation IDs.
     *
     * @param array<int, string> $variationIds
     * @return array{status: string, applied?: int}
     */
    public function pullInventory(array $variationIds): array
    {
        if (!$this->enabled) {
            return ['status' => 'disabled'];
        }

        if (!$variationIds) {
            return ['status' => 'no_variations'];
        }

        $client = $this->getClient();
        $applied = 0;

        foreach (array_chunk($variationIds, 50) as $chunk) {
            $payload = [
                'catalog_object_ids' => array_values(array_filter($chunk, static fn($id): bool => is_string($id) && $id !== '')),
            ];
            if (!$payload['catalog_object_ids']) {
                continue;
            }

            try {
                $response = $client->request(
                    'POST',
                    '/v2/inventory/batch-retrieve-counts',
                    $payload,
                    bin2hex(random_bytes(16))
                );
            } catch (Throwable $e) {
                $this->recordSyncError('inventory_request_failed', $e->getMessage(), ['chunk' => $chunk]);
                continue;
            }

            if ($response['statusCode'] < 200 || $response['statusCode'] >= 300) {
                $this->recordSyncError('inventory_http_error', 'Inventory retrieve failed.', [
                    'status' => $response['statusCode'],
                    'body' => $response['raw'],
                ]);
                continue;
            }

            $body = $response['body'];
            if (!is_array($body) || !isset($body['counts']) || !is_array($body['counts'])) {
                continue;
            }

            foreach ($body['counts'] as $count) {
                if (!is_array($count)) {
                    continue;
                }
                $squareId = isset($count['catalog_object_id']) ? (string) $count['catalog_object_id'] : '';
                if ($squareId === '') {
                    continue;
                }

                $quantity = $count['quantity'] ?? null;
                if ($quantity === null) {
                    continue;
                }
                $quantityValue = is_string($quantity) || is_numeric($quantity)
                    ? (int) floor((float) $quantity)
                    : null;
                if ($quantityValue === null) {
                    continue;
                }
                if ($quantityValue < 0) {
                    $quantityValue = 0;
                }

                $sku = $this->lookupSkuForSquareId($squareId);
                if ($sku === null) {
                    continue;
                }

                try {
                    $result = $this->inventory->reconcileExternalStock(
                        $sku,
                        $quantityValue,
                        'square_webhook',
                        null,
                        [
                            'square_object_id' => $squareId,
                            'source' => 'pull_inventory',
                            'state' => $count['state'] ?? null,
                        ]
                    );
                    if ($result !== null) {
                        $applied++;
                    }
                } catch (Throwable $e) {
                    $this->recordSyncError('inventory_update_failed', $e->getMessage(), [
                        'square_object_id' => $squareId,
                        'sku' => $sku,
                    ]);
                }
            }
        }

        $this->touchSyncState(['last_synced_at' => date('Y-m-d H:i:s')]);

        square_log('square.sync_pull_inventory_complete', [
            'applied' => $applied,
        ]);

        return ['status' => 'ok', 'applied' => $applied];
    }

    /**
     * Push local catalog updates to Square for the provided listing IDs.
     *
     * @param array<int, int> $localIds
     * @return array{status: string, synced?: int}
     */
    public function pushCatalog(array $localIds): array
    {
        if (!$this->enabled) {
            return ['status' => 'disabled'];
        }

        if ($this->direction === 'pull') {
            square_log('square.sync_push_skipped', ['reason' => 'direction_pull_only']);
            return ['status' => 'skipped'];
        }

        if (!$localIds) {
            return ['status' => 'no_ids'];
        }

        $client = $this->getClient();
        $synced = 0;

        foreach ($localIds as $listingId) {
            $listingId = (int) $listingId;
            if ($listingId <= 0) {
                continue;
            }

            $listing = $this->fetchListing($listingId);
            if ($listing === null) {
                continue;
            }

            $sku = $listing['product_sku'] !== '' ? $listing['product_sku'] : 'listing-' . $listingId;
            $priceCents = (int) round((float) $listing['price'] * 100);
            if ($priceCents < 0) {
                $priceCents = 0;
            }

            $existingMapId = $this->lookupCatalogMap($listingId);
            $modifierMeta = $this->fetchModifierMeta($sku);
            $existingItemId = $modifierMeta['square_item_id'] ?? null;
            $existingVariationId = $modifierMeta['square_variation_id'] ?? $existingMapId;

            $payload = $this->buildCatalogUpsertPayload(
                $listing,
                $sku,
                $priceCents,
                $existingItemId,
                $existingVariationId
            );

            try {
                $response = $client->request('POST', '/v2/catalog/object', $payload, $payload['idempotency_key']);
            } catch (Throwable $e) {
                $this->recordSyncError('catalog_push_failed', $e->getMessage(), [
                    'listing_id' => $listingId,
                ]);
                continue;
            }

            if ($response['statusCode'] < 200 || $response['statusCode'] >= 300) {
                $this->recordSyncError('catalog_push_http_error', 'Catalog push failed.', [
                    'listing_id' => $listingId,
                    'status' => $response['statusCode'],
                    'body' => $response['raw'],
                ]);
                continue;
            }

            $body = $response['body'];
            if (!is_array($body)) {
                continue;
            }

            $resolved = $this->resolveCatalogIdentifiers($body, $existingItemId, $existingVariationId);
            if ($resolved['variation_id'] === null) {
                continue;
            }

            $this->upsertCatalogMap('listing', $listingId, $resolved['variation_id']);
            $this->upsertProductModifier($sku, [
                'square_item_id' => $resolved['item_id'],
                'square_variation_id' => $resolved['variation_id'],
            ], null);

            $synced++;
        }

        if ($synced > 0) {
            $this->touchSyncState(['last_synced_at' => date('Y-m-d H:i:s')]);
        }

        square_log('square.sync_push_complete', [
            'synced' => $synced,
        ]);

        return ['status' => 'ok', 'synced' => $synced];
    }

    private function getClient(): SquareHttpClient
    {
        if ($this->client === null) {
            $this->client = new SquareHttpClient($this->config);
        }

        return $this->client;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->conn->prepare("SHOW TABLES LIKE ?");
        if ($stmt === false) {
            return false;
        }

        $stmt->bind_param('s', $table);
        $exists = false;
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            $exists = $result->num_rows > 0;
            $result->free();
        }
        $stmt->close();

        return $exists;
    }

    private function upsertCatalogMap(string $type, int $localId, string $squareId): bool
    {
        if (!$this->catalogMapAvailable || $squareId === '') {
            return false;
        }

        $stmt = $this->conn->prepare(
            'INSERT INTO square_catalog_map (local_type, local_id, square_object_id, sync_status, last_synced_at, updated_at) '
            . 'VALUES (?, ?, ?, "synced", NOW(), NOW()) '
            . 'ON DUPLICATE KEY UPDATE square_object_id = VALUES(square_object_id), '
            . 'sync_status = "synced", updated_at = NOW(), last_synced_at = NOW()'
        );

        if ($stmt === false) {
            return false;
        }

        $stmt->bind_param('sis', $type, $localId, $squareId);
        $executed = $stmt->execute();
        $stmt->close();

        return $executed;
    }

    private function upsertProductModifier(string $sku, array $data, ?string $primaryModifierId): bool
    {
        if (!$this->modifierTableAvailable || $sku === '') {
            return false;
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = null;
        }

        $stmt = $this->conn->prepare(
            'INSERT INTO product_modifiers (product_sku, square_modifier_list_id, data, created_at, updated_at) '
            . 'VALUES (?, ?, ?, NOW(), NOW()) '
            . 'ON DUPLICATE KEY UPDATE square_modifier_list_id = VALUES(square_modifier_list_id), '
            . 'data = VALUES(data), updated_at = NOW()'
        );

        if ($stmt === false) {
            return false;
        }

        $stmt->bind_param('sss', $sku, $primaryModifierId, $json);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    private function touchSyncState(array $fields): void
    {
        if (!$fields) {
            return;
        }

        $columns = [];
        $updates = [];
        $types = 's';
        $values = [$this->stateKey];

        foreach ($fields as $column => $value) {
            $columns[] = sprintf('`%s`', $column);
            $updates[] = sprintf('`%s` = VALUES(`%s`)', $column, $column);
            $types .= 's';
            $values[] = (string) $value;
        }

        $placeholders = implode(', ', array_fill(0, count($columns) + 1, '?'));
        $sql = sprintf(
            'INSERT INTO square_sync_state (setting_key, %s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            implode(', ', $columns),
            $placeholders,
            implode(', ', $updates)
        );

        $stmt = $this->conn->prepare($sql);
        if ($stmt === false) {
            return;
        }

        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();
    }

    private function recordSyncError(string $code, string $message, array $context = []): void
    {
        $json = $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $stmt = $this->conn->prepare(
            'INSERT INTO square_sync_errors (error_code, message, context, created_at) VALUES (?, ?, ?, NOW())'
        );
        if ($stmt === false) {
            return;
        }
        $stmt->bind_param('sss', $code, $message, $json);
        $stmt->execute();
        $stmt->close();
    }

    private function lookupSkuForSquareId(string $squareId): ?string
    {
        if (!$this->catalogMapAvailable) {
            return null;
        }

        $stmt = $this->conn->prepare(
            'SELECT l.product_sku FROM square_catalog_map scm '
            . 'JOIN listings l ON l.id = scm.local_id '
            . 'WHERE scm.local_type = "listing" AND scm.square_object_id = ? LIMIT 1'
        );
        if ($stmt === false) {
            return null;
        }

        $stmt->bind_param('s', $squareId);
        $sku = null;
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            if ($row = $result->fetch_assoc()) {
                $candidate = (string) ($row['product_sku'] ?? '');
                if ($candidate !== '') {
                    $sku = $candidate;
                }
            }
            $result->free();
        }
        $stmt->close();

        return $sku;
    }

    private function fetchListing(int $listingId): ?array
    {
        $stmt = $this->conn->prepare(
            'SELECT l.id, l.title, l.price, l.quantity, COALESCE(l.product_sku, "") AS product_sku '
            . 'FROM listings l WHERE l.id = ? LIMIT 1'
        );
        if ($stmt === false) {
            return null;
        }

        $stmt->bind_param('i', $listingId);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($result) {
            $result->free();
        }
        $stmt->close();

        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'title' => (string) ($row['title'] ?? ''),
            'price' => (float) $row['price'],
            'quantity' => (int) ($row['quantity'] ?? 0),
            'product_sku' => (string) $row['product_sku'],
        ];
    }

    private function lookupCatalogMap(int $listingId): ?string
    {
        if (!$this->catalogMapAvailable) {
            return null;
        }

        $stmt = $this->conn->prepare(
            'SELECT square_object_id FROM square_catalog_map WHERE local_type = "listing" AND local_id = ? LIMIT 1'
        );
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param('i', $listingId);
        $squareId = null;
        if ($stmt->execute()) {
            $stmt->bind_result($squareObjectId);
            if ($stmt->fetch()) {
                $squareId = (string) $squareObjectId;
            }
        }
        $stmt->close();
        return $squareId ?: null;
    }

    private function fetchModifierMeta(string $sku): array
    {
        if (!$this->modifierTableAvailable || $sku === '') {
            return [];
        }

        $stmt = $this->conn->prepare('SELECT data FROM product_modifiers WHERE product_sku = ? LIMIT 1');
        if ($stmt === false) {
            return [];
        }

        $stmt->bind_param('s', $sku);
        $meta = [];
        if ($stmt->execute()) {
            $stmt->bind_result($data);
            if ($stmt->fetch() && is_string($data) && $data !== '') {
                $decoded = json_decode($data, true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }
        }
        $stmt->close();

        return $meta;
    }

    /**
     * @return array{item_id: ?string, variation_id: ?string}
     */
    private function resolveCatalogIdentifiers(array $body, ?string $itemId, ?string $variationId): array
    {
        $resolvedItemId = $itemId;
        $resolvedVariationId = $variationId;

        if (isset($body['catalog_object']) && is_array($body['catalog_object'])) {
            $object = $body['catalog_object'];
            $type = (string) ($object['type'] ?? '');
            if ($type === 'ITEM') {
                $resolvedItemId = (string) ($object['id'] ?? $resolvedItemId);
                $itemData = $object['item_data'] ?? [];
                if (is_array($itemData) && isset($itemData['variations']) && is_array($itemData['variations'])) {
                    foreach ($itemData['variations'] as $variation) {
                        if (is_array($variation) && isset($variation['id'])) {
                            $resolvedVariationId = (string) $variation['id'];
                        }
                    }
                }
            } elseif ($type === 'ITEM_VARIATION') {
                $resolvedVariationId = (string) ($object['id'] ?? $resolvedVariationId);
                $data = $object['item_variation_data'] ?? [];
                if (is_array($data) && isset($data['item_id'])) {
                    $resolvedItemId = (string) $data['item_id'];
                }
            }
        }

        if (isset($body['related_objects']) && is_array($body['related_objects'])) {
            foreach ($body['related_objects'] as $related) {
                if (!is_array($related)) {
                    continue;
                }
                $type = (string) ($related['type'] ?? '');
                if ($type === 'ITEM_VARIATION') {
                    $resolvedVariationId = (string) ($related['id'] ?? $resolvedVariationId);
                    $data = $related['item_variation_data'] ?? [];
                    if (is_array($data) && isset($data['item_id'])) {
                        $resolvedItemId = (string) $data['item_id'];
                    }
                } elseif ($type === 'ITEM' && $resolvedItemId === null) {
                    $resolvedItemId = (string) ($related['id'] ?? $resolvedItemId);
                }
            }
        }

        return [
            'item_id' => $resolvedItemId,
            'variation_id' => $resolvedVariationId,
        ];
    }

    private function buildCatalogUpsertPayload(
        array $listing,
        string $sku,
        int $priceCents,
        ?string $itemId,
        ?string $variationId
    ): array {
        $idempotencyKey = bin2hex(random_bytes(16));
        $title = trim($listing['title']);
        if ($title === '') {
            $title = 'Listing #' . $listing['id'];
        }

        if ($itemId !== null && $variationId !== null) {
            return [
                'idempotency_key' => $idempotencyKey,
                'object' => [
                    'type' => 'ITEM_VARIATION',
                    'id' => $variationId,
                    'item_variation_data' => [
                        'item_id' => $itemId,
                        'name' => 'Default',
                        'sku' => $sku,
                        'price_money' => [
                            'amount' => $priceCents,
                            'currency' => 'USD',
                        ],
                    ],
                ],
            ];
        }

        $itemAlias = $itemId ?? ('#ITEM-' . $listing['id']);
        $variationAlias = $variationId ?? ('#VAR-' . $listing['id']);

        return [
            'idempotency_key' => $idempotencyKey,
            'object' => [
                'type' => 'ITEM',
                'id' => $itemAlias,
                'item_data' => [
                    'name' => $title,
                    'variations' => [[
                        'type' => 'ITEM_VARIATION',
                        'id' => $variationAlias,
                        'item_variation_data' => [
                            'item_id' => $itemAlias,
                            'name' => 'Default',
                            'sku' => $sku,
                            'price_money' => [
                                'amount' => $priceCents,
                                'currency' => 'USD',
                            ],
                        ],
                    ]],
                ],
            ],
        ];
    }
}
