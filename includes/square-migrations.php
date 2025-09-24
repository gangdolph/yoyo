<?php
/*
 * Discovery note: Square sync tables existed only as raw SQL files without runtime guards.
 * Change: Added idempotent PHP migrations with logging so admin tools can self-heal schema,
 *         expanded coverage to the commerce tables needed for orders, inventory, catalog
 *         linkage, and now provision webhook idempotency storage for processed events.
 */

declare(strict_types=1);

require_once __DIR__ . '/square-log.php';

/**
 * Run lightweight Square migrations in a way that is safe to call repeatedly.
 */
function square_run_migrations(mysqli $conn): void
{
    $database = square_migration_database_name($conn);
    if ($database === null) {
        square_log('square.migration', [
            'migration' => 'bootstrap',
            'status' => 'skipped',
            'reason' => 'no_database_selected',
        ]);
        return;
    }

    $supportsJson = square_mysql_supports_json($conn);
    $jsonType = $supportsJson ? 'JSON' : 'TEXT';

    square_ensure_sync_state_table($conn, $jsonType);
    square_ensure_sync_errors_table($conn, $jsonType);
    square_ensure_user_shipping_profiles_table($conn);
    square_ensure_order_items_table($conn);
    square_ensure_inventory_transactions_table($conn, $jsonType);
    square_ensure_listing_change_requests_table($conn, $jsonType);
    square_ensure_product_modifiers_table($conn, $jsonType);
    square_ensure_processed_events_table($conn);
    square_ensure_order_fulfillments_columns($conn);
    square_ensure_square_catalog_map_indexes($conn);
}

function square_ensure_sync_state_table(mysqli $conn, string $jsonType): void
{
    $table = 'square_sync_state';

    if (!square_table_exists($conn, $table)) {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS {$table} (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(64) NOT NULL UNIQUE,
    last_synced_at DATETIME NULL,
    last_webhook_at DATETIME NULL,
    sync_direction VARCHAR(16) NOT NULL DEFAULT 'pull',
    metadata {$jsonType} NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        square_run_statement($conn, $sql, $table . '.create_table');
    } else {
        square_log('square.migration', [
            'migration' => $table . '.create_table',
            'status' => 'skipped',
            'reason' => 'exists',
        ]);
    }

    square_ensure_column($conn, $table, 'last_synced_at', 'DATETIME NULL', $table . '.last_synced_at');
    square_ensure_column($conn, $table, 'last_webhook_at', 'DATETIME NULL', $table . '.last_webhook_at');
    square_ensure_column($conn, $table, 'sync_direction', "VARCHAR(16) NOT NULL DEFAULT 'pull'", $table . '.sync_direction');
    square_ensure_column($conn, $table, 'metadata', $jsonType . ' NULL', $table . '.metadata');
}

function square_ensure_sync_errors_table(mysqli $conn, string $jsonType): void
{
    $table = 'square_sync_errors';

    if (!square_table_exists($conn, $table)) {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS {$table} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    error_code VARCHAR(64) NULL,
    message TEXT NOT NULL,
    context {$jsonType} NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX ({created_at})
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        // Replace the placeholder index syntax for portability
        $sql = str_replace('{created_at}', '`created_at`', $sql);
        square_run_statement($conn, $sql, $table . '.create_table');
    } else {
        square_log('square.migration', [
            'migration' => $table . '.create_table',
            'status' => 'skipped',
            'reason' => 'exists',
        ]);
    }

    square_ensure_column($conn, $table, 'error_code', 'VARCHAR(64) NULL', $table . '.error_code');
    square_ensure_column($conn, $table, 'message', 'TEXT NOT NULL', $table . '.message');
    square_ensure_column($conn, $table, 'context', $jsonType . ' NULL', $table . '.context');
    square_ensure_index($conn, $table, 'created_at', 'created_at');
}

function square_ensure_order_items_table(mysqli $conn): void
{
    $table = 'order_items';

    if (!square_table_exists($conn, $table)) {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS {$table} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NULL,
    order_id VARCHAR(64) NULL,
    product_sku VARCHAR(64) NULL,
    listing_id INT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    modifiers TEXT NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        square_run_statement($conn, $sql, $table . '.create_table');
    } else {
        square_log('square.migration', [
            'migration' => $table . '.create_table',
            'status' => 'skipped',
            'reason' => 'exists',
        ]);
    }

    square_ensure_column($conn, $table, 'payment_id', 'INT NULL', $table . '.payment_id');
    square_ensure_column($conn, $table, 'order_id', 'VARCHAR(64) NULL', $table . '.order_id');
    square_ensure_column($conn, $table, 'product_sku', 'VARCHAR(64) NULL', $table . '.product_sku');
    square_ensure_column($conn, $table, 'listing_id', 'INT NULL', $table . '.listing_id');
    square_ensure_column($conn, $table, 'quantity', 'INT NOT NULL DEFAULT 1', $table . '.quantity');
    square_ensure_column($conn, $table, 'unit_price', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00', $table . '.unit_price');
    square_ensure_column($conn, $table, 'modifiers', 'TEXT NULL', $table . '.modifiers');
    square_ensure_column($conn, $table, 'subtotal', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00', $table . '.subtotal');
    square_ensure_column($conn, $table, 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', $table . '.created_at');

    square_ensure_index($conn, $table, 'payment_id', 'idx_order_items_payment_id');
    square_ensure_index($conn, $table, 'order_id', 'idx_order_items_order_id');
    square_ensure_index($conn, $table, 'product_sku', 'idx_order_items_product_sku');
    square_ensure_index($conn, $table, 'listing_id', 'idx_order_items_listing_id');
}

function square_ensure_inventory_transactions_table(mysqli $conn, string $jsonType): void
{
    $table = 'inventory_transactions';

    if (!square_table_exists($conn, $table)) {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS {$table} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    product_sku VARCHAR(64) NOT NULL,
    owner_id INT NOT NULL,
    transaction_type VARCHAR(32) NOT NULL,
    quantity_change INT NOT NULL,
    quantity_before INT NULL,
    quantity_after INT NULL,
    reference_type VARCHAR(32) NULL,
    reference_id BIGINT NULL,
    metadata {$jsonType} NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_transactions_product FOREIGN KEY (product_sku) REFERENCES products(sku) ON DELETE CASCADE,
    CONSTRAINT fk_inventory_transactions_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        square_run_statement($conn, $sql, $table . '.create_table');
    } else {
        square_log('square.migration', [
            'migration' => $table . '.create_table',
            'status' => 'skipped',
            'reason' => 'exists',
        ]);
    }

    square_ensure_column($conn, $table, 'product_sku', 'VARCHAR(64) NOT NULL', $table . '.product_sku');
    square_ensure_column($conn, $table, 'owner_id', 'INT NOT NULL', $table . '.owner_id');
    square_ensure_column($conn, $table, 'transaction_type', 'VARCHAR(32) NOT NULL', $table . '.transaction_type');
    square_ensure_column($conn, $table, 'quantity_change', 'INT NOT NULL', $table . '.quantity_change');
    square_ensure_column($conn, $table, 'quantity_before', 'INT NULL', $table . '.quantity_before');
    square_ensure_column($conn, $table, 'quantity_after', 'INT NULL', $table . '.quantity_after');
    square_ensure_column($conn, $table, 'reference_type', 'VARCHAR(32) NULL', $table . '.reference_type');
    square_ensure_column($conn, $table, 'reference_id', 'BIGINT NULL', $table . '.reference_id');
    square_ensure_column($conn, $table, 'metadata', $jsonType . ' NULL', $table . '.metadata');
    square_ensure_column($conn, $table, 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', $table . '.created_at');

    square_ensure_index($conn, $table, 'product_sku', 'idx_inventory_transactions_product');
    square_ensure_index($conn, $table, 'owner_id', 'idx_inventory_transactions_owner');
    square_ensure_composite_index($conn, $table, 'idx_inventory_transactions_reference', ['reference_type', 'reference_id']);
}

function square_ensure_order_fulfillments_columns(mysqli $conn): void
{
    $table = 'order_fulfillments';
    if (!square_table_exists($conn, $table)) {
        square_log('square.migration', [
            'migration' => $table . '.columns',
            'status' => 'skipped',
            'reason' => 'table_missing',
        ]);
        return;
    }

    square_ensure_column($conn, $table, 'shipping_profile_id', 'INT NULL', $table . '.shipping_profile_id');
    square_ensure_column($conn, $table, 'shipping_snapshot', 'TEXT NULL', $table . '.shipping_snapshot');
    square_ensure_column($conn, $table, 'status', "VARCHAR(50) NOT NULL DEFAULT 'pending'", $table . '.status');
    square_ensure_column($conn, $table, 'tracking_number', 'VARCHAR(100) NULL', $table . '.tracking_number');
    square_ensure_column($conn, $table, 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', $table . '.updated_at');

    if (square_table_exists($conn, 'user_shipping_profiles')) {
        square_ensure_foreign_key(
            $conn,
            $table,
            'fk_order_fulfillments_shipping_profile',
            'FOREIGN KEY (`shipping_profile_id`) REFERENCES `user_shipping_profiles`(`id`)'
        );
    }
}

function square_ensure_user_shipping_profiles_table(mysqli $conn): void
{
    $table = 'user_shipping_profiles';

    if (!square_table_exists($conn, $table)) {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS {$table} (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    label VARCHAR(100) NOT NULL,
    recipient_name VARCHAR(255) NOT NULL,
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255) NULL,
    city VARCHAR(100) NOT NULL,
    region VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    country VARCHAR(2) NOT NULL DEFAULT 'US',
    phone VARCHAR(30) NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_shipping_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        square_run_statement($conn, $sql, $table . '.create_table');
    } else {
        square_log('square.migration', [
            'migration' => $table . '.create_table',
            'status' => 'skipped',
            'reason' => 'exists',
        ]);
    }

    square_ensure_column($conn, $table, 'user_id', 'INT NOT NULL', $table . '.user_id');
    square_ensure_column($conn, $table, 'label', 'VARCHAR(100) NOT NULL', $table . '.label');
    square_ensure_column($conn, $table, 'recipient_name', 'VARCHAR(255) NOT NULL', $table . '.recipient_name');
    square_ensure_column($conn, $table, 'address_line1', 'VARCHAR(255) NOT NULL', $table . '.address_line1');
    square_ensure_column($conn, $table, 'address_line2', 'VARCHAR(255) NULL', $table . '.address_line2');
    square_ensure_column($conn, $table, 'city', 'VARCHAR(100) NOT NULL', $table . '.city');
    square_ensure_column($conn, $table, 'region', 'VARCHAR(100) NOT NULL', $table . '.region');
    square_ensure_column($conn, $table, 'postal_code', 'VARCHAR(20) NOT NULL', $table . '.postal_code');
    square_ensure_column($conn, $table, 'country', "VARCHAR(2) NOT NULL DEFAULT 'US'", $table . '.country');
    square_ensure_column($conn, $table, 'phone', 'VARCHAR(30) NULL', $table . '.phone');
    square_ensure_column($conn, $table, 'is_default', 'TINYINT(1) NOT NULL DEFAULT 0', $table . '.is_default');
    square_ensure_column($conn, $table, 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', $table . '.created_at');
    square_ensure_column($conn, $table, 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', $table . '.updated_at');

    square_ensure_index($conn, $table, 'user_id', 'idx_user_shipping_profiles_user_id');
}

function square_ensure_listing_change_requests_table(mysqli $conn, string $jsonType): void
{
    $table = 'listing_change_requests';

    if (!square_table_exists($conn, $table)) {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS {$table} (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    requester_id INT NOT NULL,
    reviewer_id INT NULL,
    change_type VARCHAR(32) NOT NULL DEFAULT 'status',
    change_summary TEXT NULL,
    requested_status ENUM('draft','pending','approved','live','closed','delisted') NULL,
    payload {$jsonType} NULL,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    review_notes TEXT NULL,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_listing_change_requests_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    CONSTRAINT fk_listing_change_requests_requester FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_listing_change_requests_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        square_run_statement($conn, $sql, $table . '.create_table');
    } else {
        square_log('square.migration', [
            'migration' => $table . '.create_table',
            'status' => 'skipped',
            'reason' => 'exists',
        ]);
    }

    square_ensure_column($conn, $table, 'listing_id', 'INT NOT NULL', $table . '.listing_id');
    square_ensure_column($conn, $table, 'requester_id', 'INT NOT NULL', $table . '.requester_id');
    square_ensure_column($conn, $table, 'reviewer_id', 'INT NULL', $table . '.reviewer_id');
    square_ensure_column($conn, $table, 'change_type', "VARCHAR(32) NOT NULL DEFAULT 'status'", $table . '.change_type');
    square_ensure_column($conn, $table, 'change_summary', 'TEXT NULL', $table . '.change_summary');
    square_ensure_column($conn, $table, 'requested_status', "ENUM('draft','pending','approved','live','closed','delisted') NULL", $table . '.requested_status');
    square_ensure_column($conn, $table, 'payload', $jsonType . ' NULL', $table . '.payload');
    square_ensure_column($conn, $table, 'status', "ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending'", $table . '.status');
    square_ensure_column($conn, $table, 'review_notes', 'TEXT NULL', $table . '.review_notes');
    square_ensure_column($conn, $table, 'resolved_at', 'TIMESTAMP NULL DEFAULT NULL', $table . '.resolved_at');
    square_ensure_column($conn, $table, 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', $table . '.created_at');
    square_ensure_column($conn, $table, 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', $table . '.updated_at');

    square_ensure_index($conn, $table, 'listing_id', 'idx_listing_change_requests_listing_id');
    square_ensure_index($conn, $table, 'requester_id', 'idx_listing_change_requests_requester_id');
    square_ensure_index($conn, $table, 'reviewer_id', 'idx_listing_change_requests_reviewer_id');
    square_ensure_composite_index($conn, $table, 'idx_listing_change_requests_listing_status', ['listing_id', 'status']);
}

function square_ensure_product_modifiers_table(mysqli $conn, string $jsonType): void
{
    $table = 'product_modifiers';

    if (!square_table_exists($conn, $table)) {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS {$table} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    product_sku VARCHAR(64) NOT NULL,
    square_modifier_list_id VARCHAR(255) NULL,
    data {$jsonType} NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_modifiers_product FOREIGN KEY (product_sku) REFERENCES products(sku) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        square_run_statement($conn, $sql, $table . '.create_table');
    } else {
        square_log('square.migration', [
            'migration' => $table . '.create_table',
            'status' => 'skipped',
            'reason' => 'exists',
        ]);
    }

    square_ensure_column($conn, $table, 'product_sku', 'VARCHAR(64) NOT NULL', $table . '.product_sku');
    square_ensure_column($conn, $table, 'square_modifier_list_id', 'VARCHAR(255) NULL', $table . '.square_modifier_list_id');
    square_ensure_column($conn, $table, 'data', $jsonType . ' NULL', $table . '.data');
    square_ensure_column($conn, $table, 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', $table . '.created_at');
    square_ensure_column($conn, $table, 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP', $table . '.updated_at');

    square_ensure_index($conn, $table, 'product_sku', 'idx_product_modifiers_product_sku');
    square_ensure_composite_index($conn, $table, 'uniq_product_modifiers_product_sku', ['product_sku'], true);
}

function square_ensure_processed_events_table(mysqli $conn): void
{
    $table = 'square_processed_events';

    if (!square_table_exists($conn, $table)) {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS {$table} (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(255) NOT NULL,
    event_type VARCHAR(64) NULL,
    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_square_processed_event_id (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        square_run_statement($conn, $sql, $table . '.create_table');
    } else {
        square_log('square.migration', [
            'migration' => $table . '.create_table',
            'status' => 'skipped',
            'reason' => 'exists',
        ]);
    }

    square_ensure_column($conn, $table, 'event_type', 'VARCHAR(64) NULL', $table . '.event_type');
    square_ensure_column($conn, $table, 'received_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', $table . '.received_at');
    square_ensure_composite_index($conn, $table, 'uniq_square_processed_event_id', ['event_id'], true);
}

function square_ensure_square_catalog_map_indexes(mysqli $conn): void
{
    $table = 'square_catalog_map';
    if (!square_table_exists($conn, $table)) {
        square_log('square.migration', [
            'migration' => $table . '.indexes',
            'status' => 'skipped',
            'reason' => 'table_missing',
        ]);
        return;
    }

    square_ensure_composite_index($conn, $table, 'uniq_square_catalog_map_local', ['local_type', 'local_id'], true);
    square_ensure_composite_index($conn, $table, 'uniq_square_catalog_map_square', ['square_object_id'], true);
}

function square_run_statement(mysqli $conn, string $sql, string $migration): void
{
    try {
        $conn->query($sql);
        square_log('square.migration', [
            'migration' => $migration,
            'status' => 'applied',
        ]);
    } catch (Throwable $e) {
        square_log('square.migration_error', [
            'migration' => $migration,
            'status' => 'failed',
            'error' => $e->getMessage(),
        ]);
    }
}

function square_migration_database_name(mysqli $conn): ?string
{
    $result = $conn->query('SELECT DATABASE() AS db');
    if ($result instanceof mysqli_result) {
        $row = $result->fetch_assoc();
        $result->free();
        if ($row && !empty($row['db'])) {
            return (string) $row['db'];
        }
    }

    return null;
}

function square_table_exists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare('SHOW TABLES LIKE ?');
    if ($stmt === false) {
        return false;
    }

    $stmt->bind_param('s', $table);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function square_column_exists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    if ($stmt === false) {
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return (int) $count > 0;
}

function square_index_exists(mysqli $conn, string $table, string $index): bool
{
    $stmt = $conn->prepare('SHOW INDEX FROM `' . $conn->real_escape_string($table) . '` WHERE Key_name = ?');
    if ($stmt === false) {
        return false;
    }

    $stmt->bind_param('s', $index);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function square_foreign_key_exists(mysqli $conn, string $table, string $constraint): bool
{
    $sql = 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?';
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return false;
    }

    $stmt->bind_param('ss', $table, $constraint);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return (int) $count > 0;
}

function square_ensure_foreign_key(mysqli $conn, string $table, string $constraint, string $definition): void
{
    if (square_foreign_key_exists($conn, $table, $constraint)) {
        square_log('square.migration', [
            'migration' => $table . '.fk.' . $constraint,
            'status' => 'skipped',
            'reason' => 'foreign_key_exists',
        ]);
        return;
    }

    $sql = sprintf('ALTER TABLE `%s` ADD CONSTRAINT `%s` %s', $table, $constraint, $definition);
    square_run_statement($conn, $sql, $table . '.fk.' . $constraint);
}

function square_ensure_column(mysqli $conn, string $table, string $column, string $definition, string $migration): void
{
    if (square_column_exists($conn, $table, $column)) {
        square_log('square.migration', [
            'migration' => $migration,
            'status' => 'skipped',
            'reason' => 'column_exists',
        ]);
        return;
    }

    $sql = sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', $table, $column, $definition);
    square_run_statement($conn, $sql, $migration);
}

function square_ensure_index(mysqli $conn, string $table, string $column, string $index): void
{
    if (square_index_exists($conn, $table, $index)) {
        square_log('square.migration', [
            'migration' => $table . '.index.' . $index,
            'status' => 'skipped',
            'reason' => 'index_exists',
        ]);
        return;
    }

    $sql = sprintf('ALTER TABLE `%s` ADD INDEX `%s` (`%s`)', $table, $index, $column);
    square_run_statement($conn, $sql, $table . '.index.' . $index);
}

function square_ensure_composite_index(mysqli $conn, string $table, string $index, array $columns, bool $unique = false): void
{
    if (square_index_exists($conn, $table, $index)) {
        square_log('square.migration', [
            'migration' => $table . '.index.' . $index,
            'status' => 'skipped',
            'reason' => 'index_exists',
        ]);
        return;
    }

    $quotedColumns = array_map(static fn(string $column): string => sprintf('`%s`', $column), $columns);
    $columnList = implode(', ', $quotedColumns);
    $type = $unique ? 'UNIQUE INDEX' : 'INDEX';
    $sql = sprintf('ALTER TABLE `%s` ADD %s `%s` (%s)', $table, $type, $index, $columnList);
    square_run_statement($conn, $sql, $table . '.index.' . $index);
}

function square_mysql_supports_json(mysqli $conn): bool
{
    $version = mysqli_get_server_version($conn);
    if ($version <= 0) {
        return false;
    }

    // MySQL 5.7.8+ and MariaDB 10.2.7+ have JSON support; MariaDB uses 10.x numbering (first two digits major*10000)
    $major = (int) floor($version / 10000);
    $minor = (int) floor(($version % 10000) / 100);
    $patch = (int) ($version % 100);

    // MySQL 8.x or newer definitely supports JSON
    if ($major >= 8) {
        return true;
    }

    // MySQL 5.7.8+
    if ($major === 5) {
        if ($minor > 7) {
            return true;
        }
        if ($minor === 7 && $patch >= 8) {
            return true;
        }
    }

    // MariaDB 10.2.7+
    if ($major >= 10) {
        if ($minor > 2) {
            return true;
        }
        if ($minor === 2 && $patch >= 7) {
            return true;
        }
    }

    return false;
}
