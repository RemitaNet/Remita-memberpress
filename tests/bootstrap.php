<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Support/AmountNormalizer.php';
require_once __DIR__ . '/../src/Support/PaymentIdentifier.php';
require_once __DIR__ . '/../src/Support/PaymentStatusMapper.php';
require_once __DIR__ . '/../src/Support/PaymentUpdateService.php';

if (!function_exists('update_post_meta')) {
    function update_post_meta(int $postId, string $key, mixed $value): void
    {
        $GLOBALS['memberpress_test_meta'][$postId][$key] = $value;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $value): string|false
    {
        return json_encode($value);
    }
}

if (!function_exists('wp_rand')) {
    function wp_rand(int $min = 0, int $max = 0): int
    {
        return $min;
    }
}
