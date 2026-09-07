<?php

declare(strict_types=1);

use Remita\Memberpass\Support\PaymentStatusMapper;
use Remita\Memberpass\Support\PaymentUpdateService;

require_once __DIR__ . '/TestCase.php';

final class PaymentUpdateServiceTest extends TestCase
{
    public function testKeepsPendingTransactionPending(): void
    {
        $service = new PaymentUpdateService();
        $transaction = (object) [
            'id' => 11,
            'status' => 'pending',
        ];

        $completed = false;

        $result = $service->applyQueryResponse(
            $transaction,
            [
                'status' => '09',
                'data' => ['paymentState' => 'PENDING'],
            ],
            function () use (&$completed): void {
                $completed = true;
            }
        );

        $this->assertSame(PaymentStatusMapper::STATUS_PENDING, $result);
        $this->assertSame(false, $completed);
    }

    public function testIgnoresRegressiveWebhookAfterCompletion(): void
    {
        $service = new PaymentUpdateService();
        $transaction = (object) [
            'id' => 19,
            'status' => 'complete',
        ];

        $completed = false;

        $result = $service->applyWebhookPayload(
            $transaction,
            [
                'data' => ['status' => 'failed'],
            ],
            function () use (&$completed): void {
                $completed = true;
            }
        );

        $this->assertSame(PaymentStatusMapper::STATUS_SUCCESS, $result);
        $this->assertSame(false, $completed);
    }
}
