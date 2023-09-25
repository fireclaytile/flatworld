<?php

namespace fireclaytile\flatworld\services;

use craft\base\Component;
use craft\helpers\Json;
use putyourlightson\logtofile\LogToFile;

/**
 * Service class for logging messages.
 *
 * @author     Fireclay Tile
 * @package    fireclaytile\flatworld\services
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
