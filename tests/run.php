<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/TestCase.php';
require __DIR__ . '/AmountNormalizerTest.php';
require __DIR__ . '/PaymentIdentifierTest.php';
require __DIR__ . '/PaymentStatusMapperTest.php';
require __DIR__ . '/PaymentUpdateServiceTest.php';

$testClasses = [
    AmountNormalizerTest::class,
    PaymentIdentifierTest::class,
    PaymentStatusMapperTest::class,
    PaymentUpdateServiceTest::class,
];

$failures = [];
$executed = 0;

foreach ($testClasses as $testClass) {
    $instance = new $testClass();

    foreach (array_filter(get_class_methods($instance), static fn (string $method): bool => str_starts_with($method, 'test')) as $method) {
        $executed++;

        try {
            $instance->{$method}();
            echo "[PASS] {$testClass}::{$method}\n";
        } catch (Throwable $throwable) {
            $failures[] = sprintf('[FAIL] %s::%s %s', $testClass, $method, $throwable->getMessage());
        }
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure . "\n";
    }

    echo sprintf("\n%d/%d tests failed.\n", count($failures), $executed);
    exit(1);
}

echo sprintf("\nAll %d MemberPress tests passed.\n", $executed);
