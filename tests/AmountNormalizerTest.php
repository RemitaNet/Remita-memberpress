<?php

declare(strict_types=1);

use Remita\Memberpass\Support\AmountNormalizer;

require_once __DIR__ . '/TestCase.php';

final class AmountNormalizerTest extends TestCase
{
    public function testConvertsToKobo(): void
    {
        $this->assertSame(300000, AmountNormalizer::toKobo(3000.00));
    }
}
