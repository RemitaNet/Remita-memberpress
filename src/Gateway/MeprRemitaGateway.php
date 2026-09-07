<?php

declare(strict_types=1);

use PaymentEngine\Sdk\Client\PaymentEngineClient;
use Remita\Memberpass\Support\AmountNormalizer;
use Remita\Memberpass\Support\PaymentIdentifier;
use Remita\Memberpass\Support\PaymentStatusMapper;
use Remita\Memberpass\Support\PaymentUpdateService;

class MeprRemitaGateway extends MeprBaseRealGateway
{
    public function __construct()
    {
        $this->name = 'Remita Checkout MemberPress';
        $this->key  = 'remita';
        $this->icon = '';
        $this->desc = __('Pay securely through Remita Checkout.', 'mepr-remita');
        $this->set_defaults();

        $this->capabilities = [
            'process-payments',
            'process-refunds'
        ];
    }

    protected function set_defaults(): void
    {
        if (!isset($this->settings)) {
            $this->settings = new \stdClass();
        }

        if (!isset($this->settings->gateway)) {
            $this->settings->gateway = 'MeprRemitaGateway';
        }

        if (!isset($this->settings->id)) {
            $this->settings->id = $this->generate_id();
        }

        if (!isset($this->settings->label)) {
            $this->settings->label = '';
        }

        if (!isset($this->settings->use_label)) {
            $this->settings->use_label = true;
        }

        if (!isset($this->settings->icon)) {
            $this->settings->icon = '';
        }

        if (!isset($this->settings->use_icon)) {
            $this->settings->use_icon = true;
        }

        if (!isset($this->settings->desc)) {
            $this->settings->desc = '';
        }

        if (!isset($this->settings->use_desc)) {
            $this->settings->use_desc = true;
        }

        if (!isset($this->settings->base_url)) {
            $this->settings->base_url = 'https://api-checkout-qa.systemspecsng.com';
        }

        if (!isset($this->settings->secret_key)) {
            $this->settings->secret_key = '';
        }

        $this->id = $this->settings->id;
        $this->label = $this->settings->label;
        $this->use_label = $this->settings->use_label;
        $this->use_icon = $this->settings->use_icon;
        $this->use_desc = $this->settings->use_desc;
    }

    public function setting_name($key): string
    {
        $mepr_options = MeprOptions::fetch();
        return $mepr_options->integrations_str . '[' . $this->id . '][' . $key . ']';
    }

    public function display_options_form(): void
    {
        $baseUrl = $this->settings->base_url ?? 'https://api-checkout-qa.systemspecsng.com';
        $secretKey = $this->settings->secret_key ?? '';
        $webhookUrl = admin_url('admin-ajax.php?action=mepr_remita_callback');
        ?>
        <table>
            <tr>
                <td><?php _e('API Base URL', 'mepr-remita'); ?>:</td>
                <td>
                    <input type="text" name="<?php echo esc_attr($this->setting_name('base_url')); ?>" value="<?php echo esc_attr($baseUrl); ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <td><?php _e('Secret Key', 'mepr-remita'); ?>:</td>
                <td>
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <input type="password" name="<?php echo esc_attr($this->setting_name('secret_key')); ?>" value="<?php echo esc_attr($secretKey); ?>" class="regular-text" style="margin: 0;" />
                        <button type="button" class="button" onclick="const input = this.previousElementSibling; if (input.type === 'password') { input.type = 'text'; this.textContent = 'Hide'; } else { input.type = 'password'; this.textContent = 'Show'; }">Show</button>
                    </div>
                </td>
            </tr>
            <tr>
                <td><?php _e('Webhook/Callback URL', 'mepr-remita'); ?>:</td>
                <td>
                    <input type="text" value="<?php echo esc_attr($webhookUrl); ?>" class="regular-text" readonly="readonly" onclick="this.select();" />
                    <p class="description"><?php _e('Configure this URL on your Remita Checkout merchant profile for both browser return and webhook callbacks.', 'mepr-remita'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function process_signup_form($txn): void
    {
        if (!$txn instanceof MeprTransaction) {
            return;
        }

        $user = new MeprUser($txn->user_id);
        $amountInKobo = AmountNormalizer::toKobo($txn->amount);
        $paymentIdentifier = PaymentIdentifier::build((int) $txn->id);

        update_post_meta($txn->id, '_remita_payment_identifier', $paymentIdentifier);

        // We append an ampersand '&' because Remita's system unconditionally appends '?paymentIdentifier=...'
        // If we don't end with '&', Remita's double question mark corrupts the $_GET['action'] variable, 
        // causing WordPress to return a 400 Bad Request instead of routing to our callback.
        $returnUrl = admin_url('admin-ajax.php?action=mepr_remita_callback&');
        $phone = get_user_meta($user->ID, 'mepr_phone', true) ?: get_user_meta($user->ID, 'phone', true) ?: '08000000000';

        $client = new PaymentEngineClient($this->settings->base_url, $this->settings->secret_key);
        $payload = [
            'firstName' => (string) $user->first_name,
            'lastName' => (string) $user->last_name,
            'email' => (string) $user->user_email,
            'phoneNumber' => (string) $phone,
            'paymentIdentifier' => $paymentIdentifier,
            'currency' => 'NGN',
            'narration' => sprintf('MemberPress Transaction #%s', $txn->trans_num),
            'amount' => $amountInKobo,
            'returnUrl' => $returnUrl,
        ];

        try {
            $response = $client->redirectCheckout->initiate($payload);
            $paymentLink = (string) ($response['data']['paymentLink'] ?? '');

            if ($paymentLink === '') {
                throw new \RuntimeException('Remita did not return a redirect checkout URL.');
            }

            // The browser will automatically carry over the '#mepr_jump' fragment during a 302 redirect.
            // Since Remita's checkout is an SPA, the fragment breaks its internal routing.
            // We use a JS/Meta redirect to ensure the browser navigates cleanly without preserving the fragment.
            $safeLink = esc_url_raw($paymentLink);
            echo '<!DOCTYPE html><html><head><meta http-equiv="refresh" content="0;url=' . $safeLink . '"></head>';
            echo '<body><script>window.location.href = ' . wp_json_encode($paymentLink) . ';</script></body></html>';
            exit;
        } catch (\Throwable $exception) {
            wp_die(esc_html($exception->getMessage()), 'Remita Error', ['response' => 500]);
        }
    }

    public function display_payment_page($txn): void
    {
    }

    public function process_refund(MeprTransaction $txn): void
    {
        $txn->status = MeprTransaction::$refunded_str;
        $txn->store();
    }

    public function thanks_page_url($txn): string
    {
        if ($txn instanceof MeprTransaction) {
            $thanks_page_id = $txn->thanks_page_id();
            if ($thanks_page_id > 0) {
                return get_permalink($thanks_page_id);
            }
        }
        return MeprUtils::get_permalink_by_slug('thanks');
    }

    public function handleCallback(): void
    {
        $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if ($requestMethod === 'POST') {
            $this->handleWebhook();
            return;
        }

        $this->handleBrowserReturn();
    }

    private function handleBrowserReturn(): void
    {
        $paymentIdentifier = sanitize_text_field($_GET['paymentIdentifier'] ?? '');

        // Workaround for Remita returning ?action=mepr_remita_callback?paymentIdentifier=...
        // PHP's $_GET fails to parse the double question mark correctly.
        if ($paymentIdentifier === '') {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($uri, '?paymentIdentifier=') !== false) {
                $parts = explode('?paymentIdentifier=', $uri);
                $paymentIdentifier = sanitize_text_field(explode('&', $parts[1])[0] ?? '');
            }
        }

        if ($paymentIdentifier === '') {
            wp_die('Missing payment identifier.', 'Remita Error', ['response' => 400]);
        }

        $txnId = PaymentIdentifier::extractTransactionId($paymentIdentifier);

        if ($txnId === null) {
            wp_die('Invalid payment identifier.', 'Remita Error', ['response' => 400]);
        }

        $txn = new MeprTransaction($txnId);

        if ($txn->id <= 0) {
            wp_die('Transaction not found.', 'Remita Error', ['response' => 404]);
        }

        $storedIdentifier = (string) get_post_meta($txn->id, '_remita_payment_identifier', true);
        if ($storedIdentifier === '' || !hash_equals($storedIdentifier, $paymentIdentifier)) {
            wp_die('Payment identifier mismatch.', 'Remita Error', ['response' => 400]);
        }

        $client = new PaymentEngineClient($this->settings->base_url, $this->settings->secret_key);

        try {
            $response = $client->payments->query($paymentIdentifier);
            $mappedStatus = (new PaymentUpdateService())->applyQueryResponse(
                $txn,
                $response,
                function () use ($txn, $paymentIdentifier, $response): void {
                    $this->record_successful_payment($txn, $paymentIdentifier, $response);
                }
            );

            if ($mappedStatus === PaymentStatusMapper::STATUS_SUCCESS) {
                $mepr_options = MeprOptions::fetch();
                $product = $txn->product();
                $query_params = [
                    'membership' => sanitize_title($product->post_title),
                    'trans_num' => $txn->trans_num,
                    'membership_id' => $product->ID,
                ];
                $thanksUrl = $mepr_options->thankyou_page_url(http_build_query($query_params));

                wp_redirect($thanksUrl);
                exit;
            }

            $fallbackUrl = add_query_arg(
                [
                    'payment_status' => $mappedStatus,
                    'trans_num' => $txn->trans_num,
                ],
                MeprUtils::get_permalink_by_slug('unauthorized')
            );
            wp_redirect($fallbackUrl);
            exit;
        } catch (\Throwable $exception) {
            wp_die(esc_html($exception->getMessage()), 'Remita Error', ['response' => 500]);
        }
    }

    private function handleWebhook(): void
    {
        $rawPayload = file_get_contents('php://input');

        if ($rawPayload === false || trim($rawPayload) === '') {
            status_header(400);
            echo 'Missing payload';
            exit;
        }

        $payload = json_decode($rawPayload, true);

        if (!is_array($payload)) {
            status_header(400);
            echo 'Invalid payload';
            exit;
        }

        $paymentIdentifier = (string) ($payload['data']['paymentIdentifier'] ?? '');

        if ($paymentIdentifier === '') {
            status_header(400);
            echo 'Missing paymentIdentifier';
            exit;
        }

        $txnId = PaymentIdentifier::extractTransactionId($paymentIdentifier);

        if ($txnId === null) {
            status_header(400);
            echo 'Invalid paymentIdentifier';
            exit;
        }

        $txn = new MeprTransaction($txnId);

        if ($txn->id <= 0) {
            status_header(404);
            echo 'Transaction not found';
            exit;
        }

        $storedIdentifier = (string) get_post_meta($txn->id, '_remita_payment_identifier', true);
        if ($storedIdentifier === '' || !hash_equals($storedIdentifier, $paymentIdentifier)) {
            status_header(400);
            echo 'Payment identifier mismatch';
            exit;
        }

        (new PaymentUpdateService())->applyWebhookPayload(
            $txn,
            $payload,
            function () use ($txn, $paymentIdentifier, $payload): void {
                $this->record_successful_payment($txn, $paymentIdentifier, $payload);
            }
        );

        status_header(200);
        echo 'Webhook processed';
        exit;
    }

    private function record_successful_payment(MeprTransaction $txn, string $paymentIdentifier, array $response): void
    {
        if ($txn->status === MeprTransaction::$complete_str) {
            return;
        }

        $txn->status = MeprTransaction::$complete_str;
        $txn->trans_num = $paymentIdentifier;
        $txn->store();

        $this->record_payment($txn);
    }

    public function load($settings): void
    {
        $this->settings = (object) $settings;
        $this->set_defaults();
    }

    public function is_test_mode(): bool
    {
        return false;
    }

    public function force_ssl(): bool
    {
        return true;
    }

    public function enqueue_payment_form_scripts(): void
    {
    }

    public function display_payment_form($amount, $user, $product_id, $transaction_id): void
    {
    }

    public function validate_payment_form($errors): array
    {
        return $errors;
    }

    public function validate_options_form($errors): array
    {
        return $errors;
    }

    public function process_payment($transaction)
    {
    }

    public function record_payment($txn = null)
    {
    }

    public function record_refund()
    {
    }

    public function record_subscription_payment()
    {
    }

    public function record_payment_failure()
    {
    }

    public function process_trial_payment($transaction)
    {
    }

    public function record_trial_payment($transaction)
    {
    }

    public function process_create_subscription($transaction)
    {
    }

    public function record_create_subscription()
    {
    }

    public function process_update_subscription($subscription_id)
    {
    }

    public function record_update_subscription()
    {
    }

    public function process_suspend_subscription($subscription_id)
    {
    }

    public function record_suspend_subscription()
    {
    }

    public function process_resume_subscription($subscription_id)
    {
    }

    public function record_resume_subscription()
    {
    }

    public function process_cancel_subscription($subscription_id)
    {
    }

    public function record_cancel_subscription()
    {
    }

    public function display_update_account_form($subscription_id, $errors = [], $message = ''): void
    {
    }

    public function validate_update_account_form($errors = []): array
    {
        return $errors;
    }

    public function process_update_account_form($subscription_id): void
    {
    }
}

// Register the AJAX hooks globally so they execute correctly.
// We must fetch the gateway instance from MeprOptions so that it has its saved settings populated.
function mepr_remita_execute_callback(): void {
    if (!class_exists('MeprOptions')) {
        wp_die('MemberPress is not loaded.', 'Remita Error', ['response' => 500]);
    }

    $mepr_options = MeprOptions::fetch();
    $gateways = $mepr_options->payment_methods();

    foreach ($gateways as $gateway) {
        if ($gateway instanceof MeprRemitaGateway) {
            $gateway->handleCallback();
            return;
        }
    }

    wp_die('Remita gateway is not configured or active.', 'Remita Error', ['response' => 400]);
}

add_action('wp_ajax_mepr_remita_callback', 'mepr_remita_execute_callback');
add_action('wp_ajax_nopriv_mepr_remita_callback', 'mepr_remita_execute_callback');

add_action('send_headers', static function (): void {
    if (isset($_GET['action']) && $_GET['action'] === 'mepr_remita_callback') {
        header("Content-Security-Policy: frame-ancestors 'self' http://localhost:4500");
    }
});
