<?php
/**
 * ShippingService centralises fulfillment status transitions.
 */
declare(strict_types=1);

final class ShippingService
{
    private const STATUSES = ['NEW','PACKING','SHIPPED','DELIVERED','COMPLETED','CANCELLED','REFUNDED'];

    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listFulfillmentsForPayment(int $paymentId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM order_fulfillments WHERE payment_id = ? ORDER BY created_at DESC');
        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare fulfillment query.');
        }

        $stmt->bind_param('i', $paymentId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Unable to execute fulfillment query.');
        }

        $result = $stmt->get_result();
        $rows = $result->fetch_all(MYSQLI_ASSOC) ?: [];
        $stmt->close();

        return $rows;
    }

    public function updateStatus(int $fulfillmentId, string $status, ?string $trackingNumber = null): bool
    {
        $status = strtoupper($status);
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Unsupported fulfillment status.');
        }

        $stmt = $this->db->prepare('UPDATE order_fulfillments SET status = ?, tracking_number = ?, updated_at = NOW() WHERE id = ?');
        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare fulfillment update.');
        }

        $stmt->bind_param('ssi', $status, $trackingNumber, $fulfillmentId);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Unable to update fulfillment: ' . $error);
        }

        $changed = $stmt->affected_rows > 0;
        $stmt->close();

        return $changed;
    }

    public function recordSnapshot(int $fulfillmentId, array $snapshot): void
    {
        $encoded = json_encode($snapshot);
        $stmt = $this->db->prepare('UPDATE order_fulfillments SET shipping_snapshot = ?, updated_at = NOW() WHERE id = ?');
        if ($stmt === false) {
            throw new RuntimeException('Unable to prepare shipping snapshot update.');
        }

        $stmt->bind_param('si', $encoded, $fulfillmentId);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new RuntimeException('Unable to store shipping snapshot: ' . $error);
        }

        $stmt->close();
    }

    public static function allowedStatuses(): array
    {
        return self::STATUSES;
    }
}
