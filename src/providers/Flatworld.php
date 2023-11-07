<?php
/**
 * Flatworld plugin for Craft CMS 3.x
 *
 * Craft Commerce plugin to provide Postie with an additional shipping provider.
 *
 * We don't actually interface directly with Flatworld, but instead use
 * SalesForce's connection to Flatworld via API endpoints in SF.
 *
 * @link      https://github.com/fireclaytile
 * @copyright Copyright (c) 2023 Fireclay Tile
 */

namespace fireclaytile\flatworld\providers;

use Craft;
use Exception;
use Throwable;
use craft\helpers\Json;
use fireclaytile\flatworld\services\Logger;
use fireclaytile\flatworld\services\Mailer;
use fireclaytile\flatworld\services\Rates as RatesService;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use verbb\postie\base\Provider;
use verbb\postie\events\ModifyRatesEvent;

/**
 * Class Flatworld
 *
 * @author      Fireclay Tile
 * @since       0.9.0
 *
 * @property-read array|string[] $serviceList
 * @property-read string $settingsHtml
 * @property-read string $iconUrl
 */
class Flatworld extends Provider
{
    /**
     * @var string
     */
    public string $weightUnit = 'lb';

    /**
     * @var string
     */
    public string $dimensionUnit = 'in';

    /**
     * @var RatesService
     */
    private RatesService $_ratesService;

    /**
     * Gets the plugin's display name.
     *
     * @return string
     */
    public static function displayName(): string
    {
        return Craft::t('flatworld', '{displayName}', [
            'displayName' => 'Flatworld',
        ]);
    }

    /**
     * Renders the settings template.
     *
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Exception
     */
    public function getSettingsHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('flatworld/_settings', [
            'provider' => $this,
        ]);
    }

    /**
     * Gets the plugin's icon URL.
     *
     * @return string
     */
    public function getIconUrl(): string
    {
        return Craft::$app->assetManager->getPublishedUrl(
            '@fireclaytile/flatworld/assetbundles/flatworld/dist/img/Flatworld.svg',
            true,
        );
    }

    /**
     * Gets the carrierClassOfServices setting.
     *
     * @return array
     */
    public function getServiceList(): array
    {
        return $this->getSetting('carrierClassOfServices');
    }

    /**
     * Fetches an array of shipping rates.
     *
     * @param mixed $order
     * @return array
     * @throws Exception
     */
    public function fetchShippingRates($order): array
    {
        // Helps tracks calls from multiple instances
        $uniqueId = uniqid();

        // If we've locally cached the results, return that
        if ($this->_rates) {
            $this->_logMessage(
                __METHOD__,
                'Returning locally cached rates',
                $uniqueId,
            );

            return $this->_rates;
        }

        try {
            $this->_logMessage(__METHOD__, 'Fetching new rates', $uniqueId);
            $this->_logMessage(__METHOD__, 'CALL STARTED', $uniqueId);

            $this->_ratesService = new RatesService(
                $this->getSetting('displayDebugMessages'),
            );

            $this->_rates = $this->_ratesService->getRates($order);

            $this->_logMessage(__METHOD__, 'CALL FINISHED', $uniqueId);

            if ($this->_rates) {
                $rates = Json::encode($this->_rates);
                $this->_logMessage(__METHOD__, "Rates: {$rates}", $uniqueId);
                return $this->_rates;
            }
        } catch (Throwable $error) {
            $this->_throwError($uniqueId, $error);
            $this->_logMessage(__METHOD__, 'CALL FINISHED', $uniqueId);
        }

        $this->_logMessage(__METHOD__, 'Rates were empty', $uniqueId);

        return [];
    }

    /**
     * Gets the order.
     *
     * @return mixed
     */
    public function getOrder()
    {
        $this->_ratesService = new RatesService(
            $this->getSetting('displayDebugMessages'),
        );
        return $this->_ratesService->getOrder();
    }

    /**
     * Modify rates on order and return rates
     *
     * @param array $rates
     * @param $order
     * @return array
     */
    public function modifyRatesEvent(array $rates, $order): array
    {
        // Allow rate modification via events
        $modifyRatesEvent = new ModifyRatesEvent([
            'rates' => $rates,
            'order' => $order,
        ]);

        if ($this->hasEventHandlers(self::EVENT_MODIFY_RATES)) {
            $this->trigger(self::EVENT_MODIFY_RATES, $modifyRatesEvent);
        }

        return $modifyRatesEvent->rates;
    }

    /**
     * Tests the connection to the Rates API.
     *
     * @return bool
     */
    public function fetchConnection(): bool
    {
        $this->_ratesService = new RatesService(
            $this->getSetting('displayDebugMessages'),
        );
        return $this->_ratesService->testRatesConnection();
    }

    /**
     * Logs a debug message to the log file if logging is enabled.
     *
     * @param string $method Method that called this function
     * @param string $message Message to log
     * @param string|null $uniqueId Unique ID for this call
     * @return boolean
     */
    private function _logMessage(
        string $method,
        string $message,
        ?string $uniqueId = null,
    ): bool {
        if (!$this->getSetting('displayDebugMessages')) {
            return false;
        }

        $msg = "{$method} :: {$message}";

        if ($uniqueId) {
            $msg = "{$method} :: {$uniqueId} :: {$message}";
        }

        return Logger::logMessage($msg, true);
    }

    /**
     * Logs a thrown error and sends an email about it.
     *
     * @param $uniqueId
     * @param $error
     * @return void
     * @throws Exception
     */
    private function _throwError($uniqueId, $error): void
    {
        $file = 'NA';
        $line = 'NA';
        $devMode = Craft::$app->getConfig()->getGeneral()->devMode;

        if (method_exists($error, 'hasResponse')) {
            $data = Json::decode((string) $error->getResponse()->getBody());

            if ($data['error']['errorMessage']) {
                $message = $data['error']['errorMessage'];
            } else {
                $message = $error->getMessage();
                $file = $error->getFile();
                $line = $error->getLine();
            }

            Provider::error(
                $this,
                Craft::t('flatworld', 'API error: "{message}" {file}:{line}', [
                    'message' => $message,
                    'file' => $file,
                    'line' => $line,
                ]),
            );
        } else {
            $message = $error->getMessage();
            $file = $error->getFile();
            $line = $error->getLine();

            Provider::error(
                $this,
                Craft::t('flatworld', 'API error: "{message}" {file}:{line}', [
                    'message' => $message,
                    'file' => $file,
                    'line' => $line,
                ]),
            );
        }

        $order = $this->getOrder();
        $debugMessage = "MESSAGE: {$message}, FILE: {$file}, LINE: {$line}";
        if ($order) {
            $debugMessage .= ", ORDER ID: {$order->id}";
        }

        $this->_logMessage(__METHOD__, $debugMessage, $uniqueId);

        if (!$devMode && $this->getSetting('enableErrorEmailMessages')) {
            $mailer = new Mailer($this->getSetting('displayDebugMessages'));
            $mailer->sendMail($debugMessage);
        }
    }
}
