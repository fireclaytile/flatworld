<?php

namespace fireclaytile\flatworld\services;

use craft\base\Component;
use craft\helpers\Json;
use putyourlightson\logtofile\LogToFile;

/**
 * Service class for logging messages.
 *
 * TODO: Change this completely for Craft 4. Helpful reference: https://putyourlightson.com/articles/adding-logging-to-craft-plugins-with-monolog
 *
 * @author      Fireclay Tile
 * @since       0.9.0
 */
class Logger extends Component
{
    /**
     * @var boolean
     */
    private bool $_loggingEnabled;

    /**
     * Logger constructor.
     *
     * @param boolean $loggingEnabled Whether or not logging is enabled
     */
    function __construct($loggingEnabled = false)
    {
        $this->_loggingEnabled = $loggingEnabled;
    }

    /**
     * Logs a debug message to the log file if logging is enabled.
     *
     * @param string $message Message to log
     * @return bool
     */
    public function logMessage(string $message): bool
    {
        if (!is_string($message)) {
            $message = Json::encode($message);
        }

        if (!$this->_loggingEnabled) {
            return false;
        }

        LogToFile::info($message, 'flatworld');
        return true;
    }
}
