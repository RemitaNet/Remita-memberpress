<?php
/**
 * Plugin Name: Remita MemberPress
 * Description: Remita Checkout payment gateway integration for MemberPress.
 * Version: 0.1.3
 * Author: Remita
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('REMITA_MEMBERPASS_PLUGIN_FILE', __FILE__);
define('REMITA_MEMBERPASS_PLUGIN_DIR', plugin_dir_path(__FILE__));

if (!function_exists('remita_memberpass_require_sdk')) {
    function remita_memberpass_require_sdk(): void
    {
        $candidates = [
            REMITA_MEMBERPASS_PLUGIN_DIR . 'vendor/payment-engine-sdk/index.php',
            REMITA_MEMBERPASS_PLUGIN_DIR . '../../../developer-tools/server-side-sdks/php/src/index.php',
            REMITA_MEMBERPASS_PLUGIN_DIR . '../../developer-tools/server-side-sdks/php/src/index.php',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                require_once $candidate;
                return;
            }
        }

        throw new RuntimeException('Payment Engine PHP SDK bootstrap file could not be found.');
    }
}

add_action('plugins_loaded', static function (): void {
    if (!class_exists('MeprCtrlFactory')) {
        return;
    }

    remita_memberpass_require_sdk();

    require_once REMITA_MEMBERPASS_PLUGIN_DIR . 'src/Support/AmountNormalizer.php';
    require_once REMITA_MEMBERPASS_PLUGIN_DIR . 'src/Support/PaymentIdentifier.php';
    require_once REMITA_MEMBERPASS_PLUGIN_DIR . 'src/Support/PaymentStatusMapper.php';
    require_once REMITA_MEMBERPASS_PLUGIN_DIR . 'src/Support/PaymentUpdateService.php';
    require_once REMITA_MEMBERPASS_PLUGIN_DIR . 'src/Gateway/MeprRemitaGateway.php';

    add_filter('mepr-gateway-paths', static function (array $paths): array {
        $paths[] = REMITA_MEMBERPASS_PLUGIN_DIR . 'src/Gateway';
        return $paths;
    });
});

register_activation_hook(REMITA_MEMBERPASS_PLUGIN_FILE, static function (): void {
    if (class_exists('MeprOptions')) {
        $mepr_options = MeprOptions::fetch();
        $mepr_options->currency_code = 'NGN';
        $mepr_options->currency_symbol = '₦';
        $mepr_options->store();
    }
});
