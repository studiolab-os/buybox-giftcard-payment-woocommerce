<?php
/**
 * Plugin Name: Woocommerce Buybox
 * Text Domain: woocommerce-buybox
 * Description: Accepts payments by social gift card.
 * Version: 2.0.2
 * Author: Studiolab
 **/

// Exit if accessed directly
if (false === defined('ABSPATH')) {
    exit;
}

include_once('includes/class-wc-buybox-config.php');

if (
    !in_array(
        'woocommerce/woocommerce.php',
        apply_filters('active_plugins', get_option('active_plugins'))
    )
) {
    function woocommerce_required()
    {
        echo sprintf(
            '<div class="error"><p>%s</p></div>',
            __('<strong>Error!</strong> Woocommerce is mandatory. Please install it.', 'woocommerce-buybox')
        );
        return;
    }

    add_action('admin_notices', 'woocommerce_required');
}

if (!function_exists('curl_exec')) {
    function curl_required()
    {
        echo sprintf(
            '<div class="error"><p>%s</p></div>',
            __('<strong>Error!</strong> Curl is mandatory for buybox plugin. Please activate it.', 'woocommerce-buybox')
        );
    }

    add_action('admin_notices', 'curl_required');
}

if (!version_compare(PHP_VERSION, WC_BB_PHP_VERSION, '>=')) {
    function woocommerce_required_version()
    {
        echo sprintf(
            '<div class="error"><p>%s</p></div>',
            sprintf(
                __('<strong>Error!</strong> Woocommerce Buybox requires at least PHP %s! Your version is: %s. Please upgrade.',
                    'woocommerce-buybox'),
                WC_BB_PHP_VERSION,
                PHP_VERSION
            )
        );
    }

    add_action('admin_notices', 'woocommerce_required_version');
}

if (function_exists('add_action')) {
    add_action('plugins_loaded', 'woocommerce_buybox_init', 0);
}

function woocommerce_buybox_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    include_once('includes/class-wc-buybox-gift-card.php');

    add_filter(sprintf('plugin_action_links_%s', plugin_basename(__FILE__)), 'add_buybox_action_links');

    new WC_BUYBOX_GIFTCARD();

}

add_filter('query_vars', 'add_buybox_query_vars', 10, 1);

/**
 * Add query vars.
 *
 * @param array $vars
 *
 * @return string[]
 * @since  2.4.0
 *
 */
function add_buybox_query_vars(array $vars)
{
    $vars[] = 'token';
    $vars[] = 'PayerID';
    return $vars;
}

register_activation_hook(__FILE__, 'woocommerce_buybox_activation');
register_deactivation_hook(__FILE__, 'woocommerce_buybox_deactivation');
register_uninstall_hook(__FILE__, 'woocommerce_buybox_uninstall');

load_buybox_plugin_textdomain();


function woocommerce_buybox_activation()
{
    global $wpdb, $wp_roles;

    $charset_collate = '';
    if (!empty($wpdb->charset)) {
        $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
    }
    if (!empty($wpdb->collate)) {
        $charset_collate .= " COLLATE $wpdb->collate";
    }

    $order_table = $wpdb->prefix . 'woocommerce_buybox_order';
    $trustbox_order_table = $wpdb->prefix . 'woocommerce_trustbox_order';
    // create tables
    if (!$wpdb->get_var("SHOW TABLES LIKE '{$order_table}';")) {
        $queries[] = "CREATE TABLE {$order_table} (
			`id_order` int(10) unsigned NOT NULL auto_increment,
  			`id_transaction` varchar(255) NOT NULL,
  			PRIMARY KEY (`id_order`)
		) $charset_collate;";
    }
    if (!$wpdb->get_var("SHOW TABLES LIKE '{$trustbox_order_table}';")) {
        $queries[] = "CREATE TABLE {$trustbox_order_table} (
			`id_order` int(10) unsigned NOT NULL auto_increment,
  			`token` varchar(255) NOT NULL,
  			PRIMARY KEY (`id_order`)
		) $charset_collate;";
    }
    if (!empty($queries)) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($queries);
    }
}

function woocommerce_buybox_deactivation()
{
    //do nothing
}

function woocommerce_buybox_uninstall()
{
    global $wpdb;
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'woocommerce_buybox_order');
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'woocommerce_trustbox_order');
}


function load_buybox_plugin_textdomain()
{
    load_plugin_textdomain(
        'woocommerce-buybox',
        false,
        plugin_basename(dirname(__FILE__)) . '/languages'
    );
}

function add_buybox_action_links($links)
{
    $myLinks = [
        sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wc-settings&tab=checkout&section=woocommerce_buybox'),
            __('Settings', 'woocommerce-buybox')
        ),
        sprintf(
            '<a href="https://www.dropbox.com/scl/fi/c4aq2ftri1ovjx26tzeoi/notice_egift_card_plus_plugin_WooCommerce_fr.pdf?rlkey=q51ytreh2tz8em0lzwl056xtw&dl=0" target="_blank">%s</a>',
            __('Docs', 'woocommerce-buybox')
        )
    ];

    return array_merge($links, $myLinks);
}
