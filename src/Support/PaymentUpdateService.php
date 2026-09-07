<?php

declare(strict_types=1);

namespace Remita\Memberpass\Support;

final class PaymentUpdateService
{
    public function applyQueryResponse(object $transaction, array $response, callable $markSuccess): string
    {
        $transactionId = (int) ($transaction->id ?? 0);
        $mappedStatus = PaymentStatusMapper::mapQueryResponse($response);
        $numericStatus = (string) ($response['status'] ?? '');
        $paymentState = (string) ($response['data']['paymentState'] ?? '');
        $rrr = (string) ($response['data']['rrr'] ?? $response['data']['formattedRRR'] ?? '');
        $currentStatus = strtolower((string) ($transaction->status ?? ''));

        update_post_meta($transactionId, '_remita_last_numeric_status', $numericStatus);
        update_post_meta($transactionId, '_remita_last_payment_state', $paymentState);
        update_post_meta($transactionId, '_remita_last_rrr', $rrr);
        update_post_meta($transactionId, '_remita_last_mapped_status', $mappedStatus);
        update_post_meta($transactionId, '_remita_last_query_payload', wp_json_encode($response));

        if ($this->shouldPreventRegression($currentStatus, $mappedStatus)) {
            return PaymentStatusMapper::STATUS_SUCCESS;
        }

        if ($mappedStatus === PaymentStatusMapper::STATUS_SUCCESS) {
            $markSuccess();
            return $mappedStatus;
        }

        return $mappedStatus;
    }

    public function applyWebhookPayload(object $transaction, array $payload, callable $markSuccess): string
    {
        $transactionId = (int) ($transaction->id ?? 0);
        $mappedStatus = PaymentStatusMapper::mapWebhookPayload($payload);
        $rrr = (string) ($payload['data']['rrr'] ?? '');
        $currentStatus = strtolower((string) ($transaction->status ?? ''));

        update_post_meta($transactionId, '_remita_last_rrr', $rrr);
        update_post_meta($transactionId, '_remita_last_mapped_status', $mappedStatus);
        update_post_meta($transactionId, '_remita_last_webhook_payload', wp_json_encode($payload));

        if ($this->shouldPreventRegression($currentStatus, $mappedStatus)) {
            return PaymentStatusMapper::STATUS_SUCCESS;
        }

        if ($mappedStatus === PaymentStatusMapper::STATUS_SUCCESS) {
            $markSuccess();
            return $mappedStatus;
        }

        return $mappedStatus;
    }

    private function shouldPreventRegression(string $currentStatus, string $nextMappedStatus): bool
    {
        if ($nextMappedStatus === PaymentStatusMapper::STATUS_SUCCESS) {
            return false;
        }

        return $currentStatus === 'complete';
    }
}
