<?php
/**
 * Defines the contract for initiating wallet withdrawals via third-party providers.
 */
declare(strict_types=1);

interface PayoutProvider
{
    /**
     * @param array<string, mixed> $payload
     * @return array{status:string, reference?:string, message?:string}
     */
    public function payout(array $payload): array;
}

final class ManualPayoutProvider implements PayoutProvider
{
    public function payout(array $payload): array
    {
        return [
            'status' => 'manual',
            'message' => 'Manual payout recorded. Update status in admin dashboard.',
        ];
    }
}

final class NullPayoutProvider implements PayoutProvider
{
    public function payout(array $payload): array
    {
        return [
            'status' => 'skipped',
            'message' => 'No external payout provider configured.',
        ];
    }
}
