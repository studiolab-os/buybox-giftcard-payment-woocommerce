<?php

class WC_BUYBOX_LOGGER
{
    public static function log($data): void
    {
        if (!is_dir(WC_BB_LOGS_FOLDER)) {
            wp_mkdir_p(WC_BB_LOGS_FOLDER);
        }

        $logFile = WC_BB_LOGS_FOLDER . '/log_' . date('ymd') . '.log';

        $timestamp = date('Y-m-d H:i:s');

        if (is_array($data)) {
            $data = print_r($data, true);
        }

        $logMessage = "[$timestamp] $data\n";

        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
