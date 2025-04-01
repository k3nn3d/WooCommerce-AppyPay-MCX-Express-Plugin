<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_AppyPay_Webhook {
    public static function setup() {
        add_action('woocommerce_api_wc_gateway_appypay', array(__CLASS__, 'handle_webhook'));
    }

    public static function handle_webhook() {
        $gateway = WC()->payment_gateways->payment_gateways()['appypay_mcx_express'];
        
        // Verifica se webhook está ativo
        if ($gateway->get_option('webhook_enabled') !== 'yes') {
            status_header(404);
            exit;
        }

        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            WC_AppyPay_Logger::log('Webhook error: Invalid JSON payload');
            status_header(400);
            exit;
        }

        // Validação de assinatura
        $signature = $_SERVER['HTTP_X_APPYPAY_SIGNATURE'] ?? '';
        $computed = hash_hmac('sha256', $payload, $gateway->get_option('webhook_secret'));
        
        if (!hash_equals($signature, $computed)) {
            WC_AppyPay_Logger::log('Assinatura de webhook inválida');
            status_header(403);
            exit;
        }

        // Processamento do webhook
        if (isset($data['transactionId']) && isset($data['status'])) {
            $orders = wc_get_orders(array(
                'meta_key' => '_appypay_transaction_id',
                'meta_value' => sanitize_text_field($data['transactionId']),
                'limit' => 1
            ));
            
            if (!empty($orders)) {
                $order = $orders[0];
                $gateway->update_order_status($order, sanitize_text_field($data['status']));
            }
        }

        status_header(200);
        exit;
    }
}