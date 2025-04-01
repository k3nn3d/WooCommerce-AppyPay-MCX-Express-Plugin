<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_AppyPay extends WC_Payment_Gateway {

    private $api_base_url = 'https://gwy-api.appypay.co.ao/v2.0';
    private $auth_url = 'https://login.microsoftonline.com/auth.appypay.co.ao/oauth2/token';
    private $access_token = '';
    private $token_expiry = 0;

    public function __construct() {
        $this->id = 'appypay_mcx_express';
        $this->icon = plugins_url('../assets/images/mcx_express.png', __FILE__);
        $this->has_fields = true;
        $this->method_title = 'AppyPay MCX EXPRESS';
        $this->method_description = 'Pague via MCX EXPRESS';

        $this->init_form_fields();
        $this->init_settings();

        $this->merchant_name = $this->get_option('merchant_name');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->client_id = $this->get_option('client_id');
        $this->api_key = $this->get_option('api_key');
        $this->client_secret = $this->get_option('client_secret');
        $this->resource = $this->get_option('resource');
        $this->grant_type = $this->get_option('grant_type');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Ativar/Desativar',
                'type' => 'checkbox',
                'label' => 'Ativar MCX EXPRESS',
                'default' => 'yes'
            ),
            'merchant_name' => array(
                'title' => 'Nome de Comerciante',
                'type' => 'text',
                'description' => 'Nome de Comerciante que o cliente verá durante o checkout, fornecido pela EMIS',
                'default' => '',
            ),
            'title' => array(
                'title' => 'Título',
                'type' => 'text',
                'description' => 'Título que o cliente verá durante o checkout.',
                'default' => 'MCX EXPRESS',
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => 'Descrição',
                'type' => 'textarea',
                'description' => 'Descrição que o cliente verá durante o checkout.',
                'default' => 'Pague com segurança usando o Multicaixa Express.',
            ),
            'client_id' => array(
                'title' => 'Client ID',
                'type' => 'text',
                'description' => 'Seu Client ID fornecido pela AppyPay.',
                'default' => '',
            ),
            'api_key' => array(
                'title' => 'API Key',
                'type' => 'text',
                'description' => 'Seu API Key fornecido pela AppyPay.',
                'default' => '',
            ),
            'client_secret' => array(
                'title' => 'Client Secret',
                'type' => 'password',
                'description' => 'Seu Client Secret fornecido pela AppyPay.',
                'default' => '',
            ),
            'resource' => array(
                'title' => 'Resource',
                'type' => 'text',
                'description' => 'Resource URL fornecido pela AppyPay.',
                'default' => '',
            ),
            'grant_type' => array(
                'title' => 'Grant Type',
                'type' => 'text',
                'description' => 'Tipo de concexão para autenticação.',
                'default' => 'client_credentials',
            ),
            'webhook_section' => array(
                'title' => 'Configurações de Webhook',
                'type'  => 'title'
            ),
            'webhook_enabled' => array(
                'title'   => 'Ativar Webhook',
                'type'    => 'checkbox',
                'label'   => 'Usar webhook para confirmações automáticas',
                'default' => 'no'
            ),
            'webhook_url' => array(
                'title'       => 'URL do Webhook',
                'type'        => 'text',
                'description' => 'URL para configurar no painel AppyPay: <code>' . esc_url(home_url('/wc-api/wc_gateway_appypay')) . '</code>',
                'default'     => '',
                'custom_attributes' => array('readonly' => 'readonly')
            ),
            'webhook_secret' => array(
                'title'       => 'Segredo do Webhook',
                'type'        => 'password',
                'description' => 'Chave secreta para validar as requisições do webhook'
            ),
        );
    }

    public function payment_scripts() {
        if (!is_checkout() || !$this->enabled) {
            return;
        }

        wp_enqueue_script(
            'woocommerce_appypay',
            plugins_url('../assets/js/appypay.js', __FILE__),
            array('jquery'),
            WC_VERSION,
            true
        );

        wp_localize_script('woocommerce_appypay', 'wc_appypay_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('appypay-nonce'),
            'webhook_enabled' => $this->get_option('webhook_enabled') === 'yes' ? 'yes' : 'no'
        ));
    }

    public function payment_fields() {
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }

        echo '<fieldset id="wc-' . esc_attr($this->id) . '-form" class="wc-payment-form">';
        
        echo '<div class="form-row form-row-wide">
            <label for="appypay-merchant">Nome do Comerciante</label>    
            <h3 class="merchant-name">' . esc_html($this->merchant_name) . '</h3>
            <label for="appypay-phone">Número de Telefone <span class="required">*</span></label>
            <input id="appypay-phone" name="appypay_phone" type="tel" autocomplete="off" placeholder="900 000 000" required>
        </div>';
        
        echo '</fieldset>';
    }

    private function get_access_token() {
        if (!empty($this->access_token) && time() < $this->token_expiry) {
            return $this->access_token;
        }

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json'
            ),
            'body' => array(
                'grant_type' => $this->grant_type,
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'resource' => $this->resource
            )
        );

        $response = wp_remote_post($this->auth_url, $args);

        if (is_wp_error($response)) {
            WC_AppyPay_Logger::log('Erro ao obter access token: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            $this->token_expiry = time() + 3000;
            return $this->access_token;
        }

        WC_AppyPay_Logger::log('Falha ao obter access token: ' . print_r($body, true));
        return false;
    }

    public function process_payment($order_id) {
        global $woocommerce;
        $order = wc_get_order($order_id);

        $phone = sanitize_text_field($_POST['appypay_phone']);
        if (empty($phone)) {
            wc_add_notice(__('Por favor, insira seu número de telefone.', 'woocommerce-gateway-appypay'), 'error');
            return false;
        }

        $access_token = $this->get_access_token();
        if (!$access_token) {
            wc_add_notice(__('Erro ao conectar com o gateway de pagamento. Por favor, tente novamente.', 'woocommerce-gateway-appypay'), 'error');
            return false;
        }

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token
            ),
            'timeout' => 100,
            'body' => json_encode(array(
                'amount' => $order->get_total(),
                'currency' => 'AOA',
                'description' => 'Pedido #' . $order_id,
                'merchantTransactionId' => 'WC-' . $order_id,
                'paymentMethod' => 'GPO_' . $this->api_key,
                'paymentInfo' => array('phoneNumber' => $phone),
                'notify' => array(
                    'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'telephone' => $order->get_billing_phone(),
                    'email' => $order->get_billing_email(),
                    'smsNotification' => false,
                    'emailNotification' => false
                ),
                'callback_url' => $this->get_option('webhook_enabled') === 'yes' ? home_url('/wc-api/wc_gateway_appypay') : null
            ))
        );

        $response = wp_remote_post($this->api_base_url . '/charges', $args);

        if (is_wp_error($response)) {
            WC_AppyPay_Logger::log('Erro ao processar pagamento: ' . $response->get_error_message());
            wc_add_notice(__('Erro ao processar seu pagamento. Por favor, tente novamente.', 'woocommerce-gateway-appypay'), 'error');
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['transactionId'])) {
            $order->update_meta_data('_appypay_transaction_id', $body['transactionId']);
            
            // Se webhook desativado, verifica status imediatamente
            if ($this->get_option('webhook_enabled') !== 'yes' && isset($body['status'])) {
                $this->update_order_status($order, $body['status']);
            } else {
                $order->update_status('pending', __('Aguardando confirmação via MCX Express', 'woocommerce-gateway-appypay'));
            }
            
            $order->save();
            $woocommerce->cart->empty_cart();

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        } else {
            $error_message = isset($body['message']) ? $body['message'] : __('Erro desconhecido ao processar pagamento.', 'woocommerce-gateway-appypay');
            WC_AppyPay_Logger::log('Falha no pagamento: ' . $error_message);
            wc_add_notice($error_message, 'error');
            return false;
        }
    }

    private function update_order_status($order, $status) {
        switch ($status) {
            case 'Success':
                $order->payment_complete();
                $order->add_order_note(__('Pagamento confirmado via MCX Express', 'woocommerce-gateway-appypay'));
                break;
            case 'Failed':
                $order->update_status('failed', __('Pagamento recusado via MCX Express', 'woocommerce-gateway-appypay'));
                break;
            default:
                $order->update_status('pending', __('Aguardando confirmação via MCX Express', 'woocommerce-gateway-appypay'));
        }
    }

    public function check_payment_status($order_id) {
        $order = wc_get_order($order_id);
        $transaction_id = $order->get_meta('_appypay_transaction_id');

        if (empty($transaction_id)) {
            return false;
        }

        $access_token = $this->get_access_token();
        if (!$access_token) {
            return false;
        }

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token
            ),
            'timeout' => 30
        );

        $response = wp_remote_get($this->api_base_url . '/charges/' . $transaction_id, $args);

        if (is_wp_error($response)) {
            WC_AppyPay_Logger::log('Erro ao verificar status: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['payment']['status'])) {
            $this->update_order_status($order, $body['payment']['status']);
            return $body['payment']['status'] === 'Success';
        }

        return false;
    }
}