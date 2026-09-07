<?php

declare(strict_types=1);

namespace Remita\Memberpass\Support;

final class PaymentIdentifier
{
    public static function build(int $transactionId): string
    {
        return sprintf('mepr-%d-%d-%06d', $transactionId, time(), wp_rand(100000, 999999));
    }

    public static function extractTransactionId(string $paymentIdentifier): ?int
    {
        if (preg_match('/^mepr-(\d+)-\d+-\d{6}$/', $paymentIdentifier, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
