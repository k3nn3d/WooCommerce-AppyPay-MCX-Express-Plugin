<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_AppyPay_Logger {
    public static function log($message, $level = 'info') {
        $logger = wc_get_logger();
        $context = array('source' => 'appypay-mcx-express');
        
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }

        $logger->log($level, $message, $context);
    }
}