<?php

namespace fireclaytile\flatworld\services;

use craft\helpers\Json;
use fireclaytile\flatworld\Flatworld;

/**
 * Static service class for logging messages.
 *
 * @author      Fireclay Tile
 * @since       0.9.0
 */
class Logger
{
    /**
     * Logs a debug message to the log file if logging is enabled.
     *
     * @param string $message Message to log
     * @param bool $loggingEnabled Whether or not logging is enabled
     * @return bool
     */
    public static function logMessage(
        string $message,
        bool $loggingEnabled = false,
    ): bool {
        if (!is_string($message)) {
            $message = Json::encode($message);
        }

        if (!$loggingEnabled) {
            return false;
        }

        Flatworld::info($message);
        return true;
    }
}
