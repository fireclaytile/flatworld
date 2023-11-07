<?php

namespace fireclaytile\flatworld\services;

use Craft;
use craft\base\Component;
use craft\mail\Message;
use Throwable;

/**
 * Service class for emailing.
 *
 * @author     Fireclay Tile
 * @since      0.9.0
 */
class Mailer extends Component
{
    const MAIL_TO = 'web@fireclaytile.com';
    const MAIL_SUBJECT = 'Flatworld Plugin - Error Found';

    /**
     * @var boolean|null
     */
    private bool|null $_loggingEnabled;

    /**
     * Mailer constructor.
     *
     * @param boolean $loggingEnabled Whether or not logging is enabled
     */
    public function __construct($loggingEnabled = false)
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
            $message->setTo(self::MAIL_TO);
            $message->setSubject(self::MAIL_SUBJECT);
            $message->setTextBody($textBody);

            Craft::$app->getMailer()->send($message);
        } catch (Throwable $e) {
            $msg =
                __METHOD__ .
                ' :: Something went wrong: ' .
                $e->getMessage() .
                '';
            Logger::logMessage($msg, $this->_loggingEnabled);
        }
    }
}
