<?php
/*
Plugin Name: WooCommerce AppyPay MCX Express
Plugin URI: https://github.com/k3nn3d/WooCommerce-AppyPay-MCX-Express-Plugin
Description: Gateway de pagamento MCX Express via AppyPay para WooCommerce
Version: 1.0.0
Author: Terêncio Gaspar(k3nn3d)
Author URI: https://github.com/k3nn3d
License: GPL-2.0+
Text Domain: woocommerce-gateway-appypay
Domain Path: /languages
*/

defined('ABSPATH') or exit;

// Verifica se WooCommerce está ativo
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p><strong>AppyPay MCX Express</strong> requer que o WooCommerce esteja instalado e ativo.</p></div>';
    });
    return;
}

// Carrega as classes do gateway
add_action('plugins_loaded', 'init_appypay_gateway');
function init_appypay_gateway() {
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-appypay.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-appypay-logger.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-appypay-webhook.php';
    
    WC_AppyPay_Webhook::setup();
}

// Adiciona o gateway à lista de métodos de pagamento
add_filter('woocommerce_payment_gateways', 'add_appypay_gateway');
function add_appypay_gateway($gateways) {
    $gateways[] = 'WC_Gateway_AppyPay';
    return $gateways;
}

// Handler AJAX para verificação de status (quando webhook desativado)
add_action('wp_ajax_appypay_check_payment_status', 'appypay_check_payment_status');
add_action('wp_ajax_nopriv_appypay_check_payment_status', 'appypay_check_payment_status');

function appypay_check_payment_status() {
    check_ajax_referer('appypay-nonce', 'nonce');
    
    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    
    if ($order_id) {
        $gateway = new WC_Gateway_AppyPay();
        $success = $gateway->check_payment_status($order_id);
        
        wp_send_json(array('success' => $success));
    }
    
    wp_send_json_error();
}