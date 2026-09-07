<?php

declare(strict_types=1);

namespace Remita\Memberpass\Support;

final class PaymentStatusMapper
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_PENDING = 'pending';
    public const STATUS_FAILED = 'failed';

    public static function mapQueryResponse(array $queryResponse): string
    {
        $numericStatus = strtoupper((string) ($queryResponse['status'] ?? ''));
        $paymentState = strtoupper((string) ($queryResponse['data']['paymentState'] ?? ''));

        if ($numericStatus === '00' || $paymentState === 'APPROVED') {
            return self::STATUS_SUCCESS;
        }

        if (in_array($numericStatus, ['01', '02', '03', '04', '09', '45'], true)) {
            return self::STATUS_PENDING;
        }

        return self::STATUS_FAILED;
    }

    public static function mapWebhookPayload(array $payload): string
    {
        $status = strtolower((string) ($payload['data']['status'] ?? ''));

        if ($status === 'success') {
            return self::STATUS_SUCCESS;
        }

        if (in_array($status, ['pending', 'processing', 'redirect'], true)) {
            return self::STATUS_PENDING;
        }

        return self::STATUS_FAILED;
    }
}
