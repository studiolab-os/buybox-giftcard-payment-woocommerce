<?php

class WC_BUYBOX_CONFIG
{
    public function __construct()
    {
        if (!defined('WC_BB_CORE_DIR')) {
            define('WC_BB_CORE_DIR', untrailingslashit(plugin_dir_path(__FILE__)));
        }

        if (!defined('WC_BB_PLUGIN_FOLDER_NAME')) {
            $pluginPath = dirname(__FILE__, 2);
            $pluginPathToArray = explode('/', $pluginPath);
            $pluginFolderName = end($pluginPathToArray);

            define('WC_BB_PLUGIN_FOLDER_NAME', $pluginFolderName);

        }

        if (!defined('WC_BB_PHP_VERSION')) {
            define('WC_BB_PHP_VERSION', '7.4');
        }

        if (!defined('WC_BB_API_VERSION')) {
            define('WC_BB_API_VERSION', '1.0');
        }

        if (!defined('WC_BB_MINIMUM_VERSION')) {
            define('WC_BB_MINIMUM_VERSION', '2.3.5');
        }

        if (!defined('WC_BB_DEV_MODE')) {
            define('WC_BB_DEV_MODE', false);
        }

        if (!defined('WC_BB_CORE_URL')) {
            define('WC_BB_CORE_URL', plugins_url(WC_BB_PLUGIN_FOLDER_NAME));
        }

        if (!defined('WC_BB_PLUGIN_URL')) {
            define('WC_BB_PLUGIN_URL', trailingslashit(WC_BB_CORE_URL));
        }

        if (!defined('WC_BB_INC_FOLDER')) {
            define('WC_BB_INC_FOLDER', WC_BB_CORE_DIR . '/');
        }

        if (!defined('WC_BB_API_FOLDER')) {
            define('WC_BB_API_FOLDER', WC_BB_CORE_DIR . '/api/');
        }

        if (!defined('WC_BB_LOGS_FOLDER')) {
            $upload_dir = wp_upload_dir(null, false);
            define('WC_BB_LOGS_FOLDER', $upload_dir['basedir'] . '/wc-bb-logs/');
        }

        if (WC_BB_DEV_MODE)
            @ini_set('display_errors', 'on');
        else
            @ini_set('display_errors', 'off');
    }
}

new WC_BUYBOX_CONFIG();
