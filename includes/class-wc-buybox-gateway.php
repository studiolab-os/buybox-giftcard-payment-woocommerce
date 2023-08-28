<?php

class WC_BUYBOX_GATEWAY extends WC_Payment_Gateway
{
    protected array $params;
    public array $msg = [];
    public $settings = [];
    public $form_fields = [];
    protected string $sandbox;
    protected string $api_user;
    protected string $api_password;
    protected string $api_signature;
    protected string $merchant_name;
    protected string $service_domain;


    public function __construct()
    {
        $this->init_config();
        $this->init_settings();

        if (is_admin()) {
            $this->init_form_fields();
        }

        // Define user set variables
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_receipt_' . $this->id, [$this, 'receipt_page']);
        add_action('woocommerce_thankyou_woocommerce_buybox', [$this, 'check_response']);
    }

    public function init_form_fields(): void
    {
        $this->form_fields = array(
            'woocommerce_buybox_details' => array(
                'title' => __('Server Credentials', 'woocommerce-buybox'),
                'type' => 'title',
                'description' => __(
                    '<b style="color: red;">Prior to the use of this module, please check if Curl or openSSL are activated on your server.</b><br /><br />Without SSL, this module will not be able to contact Buybox API<br />',
                    'woocommerce-buybox'
                ),
            ),
            'woocommerce_buybox_common_payment_settings' => array(
                'title' => __('Common payment settings', 'woocommerce-buybox'),
                'type' => 'title',
            ),
            'woocommerce_buybox_is_enabled' => array(
                'title' => __('Enable Buybox', 'woocommerce-buybox'),
                'type' => 'checkbox',
                'label' => __('Enable Buybox eGift Card plus Module.', 'woocommerce-buybox'),
                'default' => 'no'
            ),
            'woocommerce_buybox_sandbox' => array(
                'title' => __('Enable sandbox', 'woocommerce-buybox'),
                'type' => 'checkbox',
                'label' => __('Test mode.', 'woocommerce-buybox'),
                'default' => 'no'
            ),
            'woocommerce_buybox_payment_settings_euro' => array(
                'title' => __('Payment settings', 'woocommerce-buybox') . ' - ' . get_woocommerce_currency(),
                'type' => 'title',
            ),
            'woocommerce_buybox_api_user' => array(
                'title' => __('API user:', 'woocommerce-buybox'),
                'type' => 'text',
                'description' => __('60 char max.', 'woocommerce-buybox')
            ),
            'woocommerce_buybox_api_password' => array(
                'title' => __('API password:', 'woocommerce-buybox'),
                'type' => 'text',
                'description' => __('20 char max.', 'woocommerce-buybox')
            ),
            'woocommerce_buybox_api_signature' => array(
                'title' => __('API signature:', 'woocommerce-buybox'),
                'type' => 'text',
                'description' => __('60 char max.', 'woocommerce-buybox')
            ),
            'woocommerce_buybox_general_settings' => array(
                'title' => __('General settings', 'woocommerce-buybox'),
                'type' => 'title',
            ),
            'woocommerce_buybox_merchant_name' => array(
                'title' => __('Merchant name:', 'woocommerce-buybox'),
                'type' => 'text',
                'description' => __('20 char max.', 'woocommerce-buybox')
            ),
            'woocommerce_buybox_service_domain' => array(
                'title' => __('Service domain:', 'woocommerce-buybox'),
                'type' => 'text',
                'description' => __('Example: lapomme.buybox.net', 'woocommerce-buybox')
            )
        );
    }

    public function get_icon()
    {
        $icon = $this->icon ? '<img src="'
            . WC_HTTPS::force_https_url($this->icon)
            . '" alt="' . esc_attr($this->get_title()) . '"'
            . '" style="width: 64px;"'
            . ' />' : '';

        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    public function init_config(): void
    {
        $plugin_url = WC_BB_PLUGIN_URL;

        $this->id = 'woocommerce_buybox';
        $this->icon = sprintf('%s/images/logo.svg', WC_BB_PLUGIN_URL);
        $this->has_fields = false;

        $this->enabled = $this->get_option('woocommerce_buybox_is_enabled');
        $this->sandbox = $this->get_option('woocommerce_buybox_sandbox');
        $this->api_user = $this->get_option('woocommerce_buybox_api_user');
        $this->api_password = $this->get_option('woocommerce_buybox_api_password');
        $this->api_signature = $this->get_option('woocommerce_buybox_api_signature');
        $this->merchant_name = $this->get_option('woocommerce_buybox_merchant_name');
        $this->service_domain = $this->get_option('woocommerce_buybox_service_domain');

        $this->title = sprintf(__('Buybox Gift card %s &nbsp;', 'woocommerce-buybox'), $this->merchant_name);
        $this->method_description = <<<HTML
            <div>
                <img style="width:150px;" src="{$plugin_url}/images/logo.svg" alt="Buybox">
            </div>
        HTML;
    }

    /**
     * Process the payment and return the result
     *
     * @param $order_id
     *
     * @return array
     */
    public function process_payment($order_id): array
    {
        $order = new WC_order($order_id);

        return [
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        ];
    }

    public function payment_fields(): void
    {
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }
    }

    /**
     * @param $order_id
     */
    public function receipt_page($order_id): void
    {
        $response = $this->getAuthorization($order_id);

        if (is_wp_error($response)) {
            echo sprintf(' <p>%s </p> ', $response->get_error_message());
        } else {
            echo sprintf(
                '<p >%s </p > ',
                __('Thank you for your order, you will be redirected on buybox platform . ', 'woocommerce-buybox')
            );
        }
    }

    public function check_response($order_id): void
    {
        $token = htmlentities(strval(get_query_var('token', false)), ENT_COMPAT, 'UTF-8');
        $payerID = htmlentities(strval(get_query_var('PayerID', false)), ENT_COMPAT, 'UTF-8');
        $cookie_token = (isset($_COOKIE['buybox_token'])) ? strval($_COOKIE['buybox_token']) : false;
        $cookie_payer_id = (isset($_COOKIE['buybox_payer_id'])) ? strval($_COOKIE['buybox_payer_id']) : false;

        if (!$token || $token != $cookie_token) {
            die('Invalid cookie token');
        }

        if (!$payerID) {
            if (!$payerID = $cookie_payer_id) {
                $this->getAuthorization($order_id);
            }
        }

        $this->validate_order($order_id, $token, $payerID, 'payment');
    }

    private function get_buybox_state(string $cc, string $state)
    {
        if ('US' === $cc) {
            return $state;
        }

        $states = WC()->countries->get_states($cc);

        if (isset($states[$state])) {
            return $states[$state];
        }

        return $state;
    }

    private function getApiDomain(): string
    {
        return ($this->sandbox == 'yes' ? 'sandbox' : 'www2') . '.buybox.net';
    }

    private function getApiUrl(): string
    {
        return 'https://' . $this->getApiDomain() . '/secure/express-checkout/nvp.php';
    }

    /**
     * Generate buybox button link
     *
     * @param $order_id
     *
     * @return ?array|WP_Error
     */
    private function getAuthorization($order_id)
    {
        $order = new WC_order($order_id);
        $currencyCodeType = get_woocommerce_currency();
        $paymentAmount = floatval(WC()->cart->total);

        if ($paymentAmount <= 0) {
            $paymentAmount = floatval($order->get_total());
        }

        $params = [
            'AMT' => $paymentAmount,
            'PAYMENTACTION' => 'Sale',
            'RETURNURL' => $this->get_return_url($order),
            'CANCELURL' => esc_url_raw($order->get_cancel_order_url_raw()),
            'CURRENCYCODE' => $currencyCodeType,
            'NOSHIPPING' => 1,
            'LOCALECODE' => substr(get_bloginfo('language'), 0, 2)
        ];

        $result = $this->makeCall($this->getApiUrl(), 'SetExpressCheckout', $params, $currencyCodeType);

        if (is_array($result) and sizeof($result)) {
            if (strtoupper($result['ACK']) == 'SUCCESS') {
                if (isset($result['TOKEN'])) {
                    wc_setcookie('buybox_token',
                        strval($result['TOKEN']),
                        null,
                        apply_filters('wc_session_use_secure_cookie', false)
                    );
                    $url = 'https://' . $this->getApiDomain() . '/secure/payment_login.php?token='
                        . urldecode(strval(strval($result['TOKEN']))) . '&useraction=commit&lang='
                        . str_replace('-', '_', get_bloginfo('language'));
                    header('Location: ' . $url);
                } else {
                    WC_BUYBOX_LOGGER::log(__('No token given by Buybox', 'woocommerce-buybox'));
                }
            }
        }

        WC_BUYBOX_LOGGER::log(__('Buybox returned error', 'woocommerce-buybox'));
        return new WP_Error(
            'error',
            __('Buybox returned error', 'woocommerce-buybox') . $result['L_ERRORCODE0']
        );
    }

    private function makeCall($url, $methodName, $params, $currency = "EUR"): ?array
    {
        $authenticationParams = [
            'METHOD' => $methodName,
            'VERSION' => WC_BB_API_VERSION,
            'USER' => $this->api_user,
            'PWD' => $this->api_password,
            'SIGNATURE' => $this->api_signature
        ];

        $params = array_merge($params, $authenticationParams);

        $response = $this->callApi($url, $params, true);

        WC_BUYBOX_LOGGER::log(__('Buybox response:', 'woocommerce-buybox'));
        WC_BUYBOX_LOGGER::log($response);

        return $response;
    }

    private function validate_order($order_id, $token, $payerID, $type)
    {
        // Filling-in vars
        $order = new WC_order($order_id);
        $currencyCodeType = get_woocommerce_currency();
        $total = $order->get_total();
        $payerID = strval($payerID);
        $paymentType = 'Sale';
        $serverName = urlencode($_SERVER['SERVER_NAME']);
        $btnSource = ($type == 'express' ? 'ECS' : 'ECM');

        // Getting address
        $state = $this->get_buybox_state($order->billing_country, $order->billing_state);

        $params = [
            'SHIPTONAME' => $order->billing_company . ' ' . $order->billing_last_name . ' ' . $order->billing_first_name,
            'SHIPTOSTREET' => $order->billing_address_1 . ' ' . $order->billing_address_2,
            'SHIPTOCITY' => $order->billing_city,
            'SHIPTOSTATE' => $state,
            'SHIPTOCOUNTRYCODE' => $order->billing_country,
            'SHIPTOZIP' => $order->billing_postcode,
            'TOKEN' => $token,
            'PAYERID' => $payerID,
            'PAYMENTACTION' => $paymentType,
            'AMT' => $total,
            'CURRENCYCODE' => $currencyCodeType,
            'IPADDRESS' => $serverName,
            'NOTIFYURL' => urlencode(WC_BB_PLUGIN_URL . '/ipn.php'),
            'BUTTONSOURCE' => 'WORDPRESS_' . $btnSource
        ];

        $result = $this->makeCall(
            $this->getApiUrl(),
            'DoExpressCheckoutPayment',
            $params,
            $currencyCodeType
        );

        if (!is_array($result) or !sizeof($result)) {
            WC_BUYBOX_LOGGER::log(__('Authorisation to Buybox failed', 'woocommerce-buybox'));
            return new WP_Error('error', __('Authorisation to Buybox failed', 'woocommerce-buybox'));
        } elseif (!isset($result['ACK']) or strtoupper($result['ACK']) != 'SUCCESS') {
            WC_BUYBOX_LOGGER::log(__('Buybox returned error', 'woocommerce-buybox'));
            return new WP_Error(
                'error',
                __('Buybox returned error ', 'woocommerce-buybox') . $result['L_ERRORCODE0']
            );
        } elseif (!isset($result['TOKEN']) or $result['TOKEN'] != $token) {
            WC_BUYBOX_LOGGER::log(__('Token given by Buybox is not the same than cookie one', 'woocommerce-buybox'));
            return new WP_Error(
                'error',
                __('Buybox returned error ', 'woocommerce-buybox') . $result['L_ERRORCODE0']
            );
        }

        // Making log
        $id_transaction = strval($result['TRANSACTIONID']);
        WC_BUYBOX_LOGGER::log(__('Order finished with Buybox!', 'woocommerce-buybox'));

        // Order status
        switch ($result['PAYMENTSTATUS']) {
            case 'Completed':
                $order->update_status('pending');
                $order->payment_complete($id_transaction);
                $order->add_order_note(__('Payment accepted by buybox', 'woocommerce-buybox'));
                break;
            case 'Pending':
                $order->update_status(
                    'on - hold',
                    sprintf(__('Payment % s via IPN . ', 'woocommerce-buybox'), wc_clean($result['PAYMENTSTATUS']))
                );
                break;
            default:
                $order->update_status('failed');
                $order->add_order_note('Failed');
                break;
        }

        WC()->cart->empty_cart();

        $this->addOrder($order_id, $id_transaction);

        return true;
    }

    private function addOrder($id_order, $id_transaction)
    {
        global $wpdb;

        $order_table = $wpdb->prefix . 'woocommerce_buybox_order';
        $query = <<<SQL
            INSERT INTO $order_table VALUES ($id_order, '$id_transaction')
        SQL;

        $result = $wpdb->query($query);

        if (false === $result) {
            return new WP_Error(
                'db_insert_error',
                __('Could not insert term relationship into the database'),
                $wpdb->last_error
            );
        }

        return true;
    }

    private function callApi($url, $body): array
    {
        WC_BUYBOX_LOGGER::log(__('Making new connection to', 'woocommerce-buybox') . $url);

        $ch = @curl_init();
        if (!$ch) {
            WC_BUYBOX_LOGGER::log(__('Connect failed with CURL method', 'woocommerce-buybox'));
        } else {
            WC_BUYBOX_LOGGER::log(__('Connect with CURL method successfully', 'woocommerce-buybox'));
            WC_BUYBOX_LOGGER::log(__('Sending this params:', 'woocommerce-buybox') . $body);
            @curl_setopt($ch, CURLOPT_URL, $url);
            @curl_setopt($ch, CURLOPT_POST, true);
            @curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            @curl_setopt($ch, CURLOPT_HEADER, false);
            @curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            @curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            @curl_setopt($ch, CURLOPT_VERBOSE, true);
            $result = @curl_exec($ch);

            parse_str($result, $response);

            if (!$result) {
                WC_BUYBOX_LOGGER::log(
                    __('Send with CURL method failed ! Error:', 'woocommerce-buybox') . curl_error($ch)
                );
            } else {
                WC_BUYBOX_LOGGER::log(__('Send with CURL method successfully', 'woocommerce-buybox'));
            }

            @curl_close($ch);
        }

        return $response ?? [];
    }
}