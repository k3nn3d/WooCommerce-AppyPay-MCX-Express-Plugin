jQuery(function($) {
    'use strict';

    // Validação do formulário
    $(document).on('click', '#place_order', function(e) {
        if ($('input[name="payment_method"]:checked').val() === 'appypay_mcx_express') {
            var phone = $('#appypay-phone').val().trim();
            if (!phone) {
                $('.woocommerce-error, .woocommerce-message').remove();
                $('form.checkout').prepend('<div class="woocommerce-error">Por favor, insira seu número de telefone.</div>');
                $('html, body').animate({
                    scrollTop: $('.woocommerce-error').offset().top - 100
                }, 200);
                return false;
            }
        }
    });

    // Verificação do status do pagamento (apenas quando webhook desativado)
    if (typeof wc_appypay_params !== 'undefined' && wc_appypay_params.webhook_enabled === 'no') {
        $(document).ready(function() {
            var order_received_url = window.location.href;
            if (order_received_url.indexOf('order-received') !== -1) {
                var order_id = order_received_url.match(/order-received\/(\d+)/)[1];
                var checks = 0;
                var max_checks = 18; // 180 segundos (3 minutos)
                var check_interval = setInterval(function() {
                    checks++;
                    
                    $.ajax({
                        url: wc_appypay_params.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'appypay_check_payment_status',
                            order_id: order_id,
                            nonce: wc_appypay_params.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                clearInterval(check_interval);
                                window.location.reload();
                            } else if (checks >= max_checks) {
                                clearInterval(check_interval);
                                $('.woocommerce-order-overview__status').append(
                                    '<p class="appypay-waiting">Aguardando confirmação. Você será notificado por e-mail.</p>'
                                );
                            }
                        }
                    });
                }, 10000); // Verifica a cada 10 segundos
            }
        });
    }
});