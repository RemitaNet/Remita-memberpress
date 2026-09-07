<?php

declare(strict_types=1);

abstract class TestCase
{
    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message !== '' ? $message : sprintf(
                'Expected %s but got %s',
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    protected function assertNull(mixed $actual, string $message = ''): void
    {
        $this->assertSame(null, $actual, $message);
    }
}
