<?php

class WC_BUYBOX_GIFTCARD
{
    public function __construct()
    {
        load_plugin_textdomain(
            'woocommerce-buybox',
            false,
            sprintf('%s/languages/', dirname(dirname(plugin_basename(__FILE__))) . '/languages/')
        );

        $this->load_dependencies();
        $this->woo_gateway_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     * @return void
     */
    public function load_dependencies(): void
    {
        require_once(WC_BB_INC_FOLDER . 'class-wc-buybox-logger.php');
        require_once(WC_BB_INC_FOLDER . 'class-wc-buybox-gateway.php');
    }

    /**
     * Add Payment Gateways Woocommerce Section.
     *
     * @return void
     */
    public function woo_gateway_hooks(): void
    {
        add_filter('woocommerce_payment_gateways', [$this, 'add_buybox_gateway'], 10, 1);
    }

    /**
     * Add Buybox Payment gateway to Woocommerce
     */
    public function add_buybox_gateway($methods)
    {
        $methods[] = 'WC_BUYBOX_GATEWAY';
        return $methods;
    }

}