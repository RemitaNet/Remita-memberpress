<?php

declare(strict_types=1);

use Remita\Memberpass\Support\PaymentStatusMapper;

require_once __DIR__ . '/TestCase.php';

final class PaymentStatusMapperTest extends TestCase
{
    public function testMapsApprovedQueryToSuccess(): void
    {
        $this->assertSame(PaymentStatusMapper::STATUS_SUCCESS, PaymentStatusMapper::mapQueryResponse([
            'status' => '00',
            'data' => ['paymentState' => 'APPROVED'],
        ]));
    }

    public function testMapsPendingWebhookToPending(): void
    {
        $this->assertSame(PaymentStatusMapper::STATUS_PENDING, PaymentStatusMapper::mapWebhookPayload([
            'data' => ['status' => 'pending'],
        ]));
    }
}
