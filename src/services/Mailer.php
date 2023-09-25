<?php

namespace fireclaytile\flatworld\services;

use Craft;
use Throwable;
use craft\base\Component;
use craft\mail\Message;
use fireclaytile\flatworld\Flatworld as FlatworldPlugin;
use fireclaytile\flatworld\services\Logger;

/**
 * Service class for emailing.
 *
 * @author     Fireclay Tile
 * @package    fireclaytile\flatworld\services
 */
class Mailer extends Component
{
    /**
     * @var boolean
     */
    private bool $_loggingEnabled;

    /**
     * Mailer constructor.
     *
     * @param boolean $loggingEnabled Whether or not logging is enabled
     */
    function __construct($loggingEnabled = false)
    {
        $this->_loggingEnabled = $loggingEnabled;
    }

    /**
     * Sends an email to the web team with an error message
     *
     * @param string $textBody The message to include in the email
     * @return void
     */
    public function sendMail(string $textBody): void
    {
        try {
            $message = new Message();
            $message->setTo('web@fireclaytile.com');
            $message->setSubject('Flatworld Plugin - Error Found');
            $message->setTextBody($textBody);

            Craft::$app->getMailer()->send($message);
        } catch (Throwable $e) {
            $logger = new Logger($this->_loggingEnabled);
            $logger->logMessage(
                __METHOD__ . ' :: Something went wrong: ' . $e->getMessage(),
            );
        }
    }
}
