<?php

declare(strict_types=1);

use Remita\Memberpass\Support\PaymentIdentifier;

require_once __DIR__ . '/TestCase.php';

final class PaymentIdentifierTest extends TestCase
{
    public function testBuildsMemberpressScopedIdentifier(): void
    {
        $identifier = PaymentIdentifier::build(42);
        $this->assertSame(42, PaymentIdentifier::extractTransactionId($identifier));
    }

    public function testRejectsForeignIdentifier(): void
    {
        $this->assertNull(PaymentIdentifier::extractTransactionId('gf-42-1234567890-654321'));
    }
}
