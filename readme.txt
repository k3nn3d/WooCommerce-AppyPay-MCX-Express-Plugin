=== WooCommerce AppyPay MCX Express ===
Contributors: Terêncio Gaspar(k3nn3d)
Tags: woocommerce, payment gateway, appypay, multicaixa, angola
Requires at least: 5.6
Tested up to: 6.0
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Permite que lojas WooCommerce aceitem pagamentos via MCX Express da AppyPay.

== Descrição ==

Gateway de pagamento oficial para integração com o sistema MCX Express da AppyPay. 
Oferece suporte a pagamentos via telefone com confirmação em tempo real.

== Instalação ==

1. Faça upload da pasta 'woocommerce-gateway-appypay' para '/wp-content/plugins/'
2. Ative o plugin no painel WordPress
3. Configure suas credenciais em WooCommerce > Configurações > Pagamentos

== Configuração ==

1. Obtenha suas credenciais de API no painel AppyPay
2. Insira Client ID, API Key e Client Secret
3. Para webhook automático:
   - Ative a opção "Ativar Webhook"
   - Configure no painel AppyPay a URL: 
     `https://seusite.com/wc-api/wc_gateway_appypay`
   - Defina o mesmo segredo nos dois lados

== Changelog ==

= 1.0.0 =
* Versão inicial com suporte a MCX Express
* Webhook opcional com validação de assinatura
* Timeout estendido para 90s