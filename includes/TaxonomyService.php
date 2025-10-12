<?php
/**
 * TaxonomyService manages device brands and models for listings/products.
 */
declare(strict_types=1);

final class TaxonomyService
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listBrands(): array
    {
        $sql = 'SELECT b.*, COALESCE(p.product_count, 0) AS product_count, COALESCE(l.listing_count, 0) AS listing_count
                FROM brands b
                LEFT JOIN (
                    SELECT brand_id, COUNT(*) AS product_count FROM products WHERE brand_id IS NOT NULL GROUP BY brand_id
                ) AS p ON p.brand_id = b.id
                LEFT JOIN (
                    SELECT brand_id, COUNT(*) AS listing_count FROM listings WHERE brand_id IS NOT NULL GROUP BY brand_id
                ) AS l ON l.brand_id = b.id
                ORDER BY b.name';

        return $this->fetchAll($sql);
    }

    public function getBrand(int $brandId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, slug FROM brands WHERE id = ?');
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare brand lookup.');
        }

        $stmt->bind_param('i', $brandId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Failed to execute brand lookup.');
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    public function createBrand(string $name, string $slug): int
    {
        $stmt = $this->db->prepare('INSERT INTO brands (name, slug) VALUES (?, ?)');
        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare brand insert.');
        }

        $stmt->bind_param('ss', $name, $slug);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Unable to create brand: ' . $error);
        }

        $stmt->close();
        return (int) $this->db->insert_id;
    }

    public function updateBrand(int $brandId, string $name, string $slug): bool
    {
        $stmt = $this->db->prepare('UPDATE brands SET name = ?, slug = ? WHERE id = ?');
        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare brand update.');
        }

        $stmt->bind_param('ssi', $name, $slug, $brandId);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Unable to update brand: ' . $error);
        }

        $changed = $stmt->affected_rows > 0;
        $stmt->close();

        return $changed;
    }

    public function deleteBrand(int $brandId): bool
    {
        if ($this->hasBrandUsage($brandId)) {
            throw new RuntimeException('Brand is still in use by products or listings.');
        }

        $stmt = $this->db->prepare('DELETE FROM brands WHERE id = ?');
        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare brand delete.');
        }

        $stmt->bind_param('i', $brandId);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Unable to delete brand: ' . $error);
        }

        $deleted = $stmt->affected_rows > 0;
        $stmt->close();

        return $deleted;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listModelsForBrand(int $brandId): array
    {
        $stmt = $this->db->prepare('SELECT m.*, COALESCE(p.product_count, 0) AS product_count, COALESCE(l.listing_count, 0) AS listing_count
            FROM models m
            LEFT JOIN (
                SELECT model_id, COUNT(*) AS product_count FROM products WHERE model_id IS NOT NULL GROUP BY model_id
            ) p ON p.model_id = m.id
            LEFT JOIN (
                SELECT model_id, COUNT(*) AS listing_count FROM listings WHERE model_id IS NOT NULL GROUP BY model_id
            ) l ON l.model_id = m.id
            WHERE m.brand_id = ?
            ORDER BY m.name');
        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare model list.');
        }

        $stmt->bind_param('i', $brandId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to run model list query.');
        }

        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC) ?: [];
        $stmt->close();

        return $rows;
    }

    public function getModel(int $modelId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, brand_id, name, slug, attributes FROM models WHERE id = ?');
        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare model lookup.');
        }

        $stmt->bind_param('i', $modelId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to execute model lookup.');
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return null;
        }

        if ($row['attributes'] !== null) {
            $row['attributes'] = json_decode((string) $row['attributes'], true);
        }

        return $row;
    }

    public function createModel(int $brandId, string $name, string $slug, array $attributes = []): int
    {
        $encoded = $attributes ? json_encode($attributes) : null;
        $stmt = $this->db->prepare('INSERT INTO models (brand_id, name, slug, attributes) VALUES (?, ?, ?, ?)');
        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare model insert.');
        }

        $stmt->bind_param('isss', $brandId, $name, $slug, $encoded);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Unable to create model: ' . $error);
        }

        $stmt->close();
        return (int) $this->db->insert_id;
    }

    public function updateModel(int $modelId, int $brandId, string $name, string $slug, array $attributes = []): bool
    {
        $encoded = $attributes ? json_encode($attributes) : null;
        $stmt = $this->db->prepare('UPDATE models SET brand_id = ?, name = ?, slug = ?, attributes = ? WHERE id = ?');
        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare model update.');
        }

        $stmt->bind_param('isssi', $brandId, $name, $slug, $encoded, $modelId);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Unable to update model: ' . $error);
        }

        $changed = $stmt->affected_rows > 0;
        $stmt->close();

        return $changed;
    }

    public function deleteModel(int $modelId): bool
    {
        if ($this->hasModelUsage($modelId)) {
            throw new RuntimeException('Model is still in use by products or listings.');
        }

        $stmt = $this->db->prepare('DELETE FROM models WHERE id = ?');
        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare model delete.');
        }

        $stmt->bind_param('i', $modelId);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Unable to delete model: ' . $error);
        }

        $deleted = $stmt->affected_rows > 0;
        $stmt->close();

        return $deleted;
    }

    public function validateBrandModelPair(?int $brandId, ?int $modelId): bool
    {
        if ($modelId === null) {
            return true;
        }

        $stmt = $this->db->prepare('SELECT brand_id FROM models WHERE id = ?');
        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare brand-model validation.');
        }

        $stmt->bind_param('i', $modelId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to execute brand-model validation.');
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return false;
        }

        if ($brandId === null) {
            return false;
        }

        return (int) $row['brand_id'] === $brandId;
    }

    private function hasBrandUsage(int $brandId): bool
    {
        $stmt = $this->db->prepare('SELECT (EXISTS(SELECT 1 FROM products WHERE brand_id = ?) OR EXISTS(SELECT 1 FROM listings WHERE brand_id = ?)) AS in_use');
        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare brand usage check.');
        }

        $stmt->bind_param('ii', $brandId, $brandId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to execute brand usage check.');
        }

        $stmt->bind_result($inUse);
        $stmt->fetch();
        $stmt->close();

        return (bool) $inUse;
    }

    private function hasModelUsage(int $modelId): bool
    {
        $stmt = $this->db->prepare('SELECT (EXISTS(SELECT 1 FROM products WHERE model_id = ?) OR EXISTS(SELECT 1 FROM listings WHERE model_id = ?)) AS in_use');
        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare model usage check.');
        }

        $stmt->bind_param('ii', $modelId, $modelId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to execute model usage check.');
        }

        $stmt->bind_result($inUse);
        $stmt->fetch();
        $stmt->close();

        return (bool) $inUse;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAll(string $sql): array
    {
        $result = $this->db->query($sql);
        if ($result === false) {
            throw new RuntimeException('Failed to run taxonomy query.');
        }

        $rows = $result->fetch_all(MYSQLI_ASSOC) ?: [];
        $result->free();

        return $rows;
    }
}
