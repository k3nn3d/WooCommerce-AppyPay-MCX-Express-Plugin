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
        $this->icon = plugins_url('assets/images/mcx_express.png', dirname(__FILE__));
        $this->has_fields = true;
        $this->method_title = 'AppyPay MCX EXPRESS';
        $this->method_description = 'Pague via MCX EXPRESS';

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->merchant_name = $this->get_option('merchant_name');
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
                'title' => 'Nome do Comerciante',
                'type' => 'text',
                'description' => 'Nome que aparecerá durante o checkout',
                'default' => ''
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
                'description' => 'Tipo de conexão para autenticação.',
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
            )
        );
    }

    public function payment_scripts() {
        if (!is_checkout() || !$this->enabled) {
            return;
        }

        wp_enqueue_script(
            'woocommerce_appypay',
            plugins_url('assets/js/appypay.js', dirname(__FILE__)),
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
            <h5 class="merchant-name">' . esc_html($this->merchant_name) . '</h5>
            <label for="appypay-phone">Número de Telefone <span class="required">*</span></label>
            <input id="appypay-phone" name="appypay_phone" type="tel" autocomplete="off" placeholder="+244 900 000 000" required>
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

    private function generate_unique_transaction_id($order_id) {
        $prefix = 'WC'; 
        $order_part = substr(preg_replace('/[^0-9]/', '', $order_id), 0, 5); // 5 chars (pedido)
        $datetime = (new DateTime())->format('ymdHis');
    
        $transaction_id = $prefix . $order_part . $datetime;
        
        return substr($transaction_id, 0, 15);
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
        $webhook_enabled = $this->get_option('webhook_enabled') === 'yes';
    
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $access_token
        );
        
        // Adiciona header específico apenas se webhook estiver ativo
        if ($webhook_enabled) {
            $headers['Accept'] = 'application/vnd.appypay.asyncapi+json';
            $headers['X-AppyPay-Callback'] = 'true';
        } else {
            $headers['Accept'] = 'application/json';
        }

        $payload = array(
            'amount' => $order->get_total(),
            'currency' => 'AOA',
            'description' => 'Pedido ' . $order_id,
            'merchantTransactionId' => $this->generate_unique_transaction_id($order_id),
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
        );

        $args = array(
            'headers' => $headers,
            'body' => json_encode($payload),
            'timeout' => $webhook_enabled ? 180 : 60, 
            'blocking' => true
        );

        $response = wp_remote_post($this->api_base_url . '/charges', $args);

        if (is_wp_error($response)) {
            WC_AppyPay_Logger::log('Erro ao processar pagamento: ' . $response->get_error_message());
            wc_add_notice(__('Erro ao processar seu pagamento. Por favor, tente novamente.', 'woocommerce-gateway-appypay'), 'error');
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['id'])) {
            $order->update_meta_data('_appypay_transaction_id', $body['id']);
            $order->set_transaction_id($body['id']);
            $order->update_meta_data('_appypay_merchant_tx_id', $payload['merchantTransactionId']);

            if (isset($body['responseStatus']['status'])) {
                $this->update_order_status($order, $body['responseStatus']['status']);
            } else {
                $order->update_status('wc-on-hold');
            }

            if ($body['responseStatus']['status'] == 'Success') {
                $order->add_order_note(__('Pagamento confirmado via MCX Express', 'woocommerce-gateway-appypay'));
                $order->save();
                $woocommerce->cart->empty_cart();
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($order)
                );
            } 
        } else {
            $error_message = isset($body['message']) ? $body['message'] : __('Erro desconhecido ao processar pagamento.', 'woocommerce-gateway-appypay');
            WC_AppyPay_Logger::log('Falha no pagamento: ' . print_r($body, true));
            wc_add_notice($error_message, 'error');
            return false;
        }
    }

    private function update_order_status($order, $status) {
        switch ($status) {
            case 'Success':
                $order->payment_complete();
                $order->update_status('wc-processing');
                $order->add_order_note(__('Pagamento confirmado via MCX Express', 'woocommerce-gateway-appypay'));
                break;
            case 'Failed':
                $order->update_status('wc-failed');
                $order->add_order_note(__('Pagamento recusado via MCX Express', 'woocommerce-gateway-appypay'));
                break;
            default:
                $order->update_status('wc-on-hold');
                $order->add_order_note(__('Aguardando confirmação via MCX Express', 'woocommerce-gateway-appypay'));
        }
    }
}