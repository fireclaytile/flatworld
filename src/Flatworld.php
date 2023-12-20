<?php
/**
 * Flatworld plugin for Craft CMS 4.x
 *
 * Craft Commerce plugin to provide Postie with an additional shipping provider.
 *
 * @link      https://github.com/fireclaytile
 * @copyright Copyright (c) 2023 Fireclay Tile
 */

namespace fireclaytile\flatworld;

use Craft;
use craft\base\Plugin;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\log\MonologTarget;
use craft\web\twig\variables\CraftVariable;
use fireclaytile\flatworld\providers\Flatworld as FlatworldProvider;
use fireclaytile\flatworld\services\Mailer;
use fireclaytile\flatworld\services\Rates;
use fireclaytile\flatworld\services\RatesApi;
use fireclaytile\flatworld\variables\FlatworldVariable;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;
use verbb\postie\controllers\PluginController;
use verbb\postie\events\ModifyShippableVariantsEvent;
use verbb\postie\events\RegisterProviderTypesEvent;
use verbb\postie\services\Providers;
use yii\base\Event;
use yii\log\Logger;

/**
 * Class Flatworld
 *
 * @author      Fireclay Tile
 * @since       0.8.0
 */
class Flatworld extends Plugin
{
    /**
     * @var Flatworld
     */
    public static $plugin;

    /**
     * @var string
     */
    public string $schemaVersion = '2.0.0';

    /**
     * @var bool
     */
    public bool $hasCpSettings = false;

    /**
     * @var bool
     */
    public bool $hasCpSection = false;

    public function init()
    {
        parent::init();

        self::$plugin = $this;

        $this->_registerLogTarget();

        $this->initServices();

        // Registers the Flatworld as new shipping provider
        Event::on(
            Providers::class,
            Providers::EVENT_REGISTER_PROVIDER_TYPES,
            function (RegisterProviderTypesEvent $event) {
                $event->providerTypes[] = FlatworldProvider::class;
            },
        );

        Event::on(
            PluginController::class,
            PluginController::EVENT_MODIFY_VARIANT_QUERY,
            function (ModifyShippableVariantsEvent $event) {
                $products = Product::find()
                    ->type(['addons', 'merchandise'])
                    ->all();

                if (count($products) > 0) {
                    $excludedProductTypeIds = [];

                    foreach ($products as $product) {
                        if (
                            !in_array($product->typeId, $excludedProductTypeIds)
                        ) {
                            $excludedProductTypeIds[] = $product->typeId;
                        }
                    }

                    $event->query = Variant::find()
                        ->typeId(['not', $excludedProductTypeIds])
                        ->weight([0, null])
                        ->height([0, null])
                        ->width([0, null])
                        ->length([0, null]);
                }
            },
        );

        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function (
            Event $event,
        ) {
            $variable = $event->sender;
            $variable->set('flatworld', FlatworldVariable::class);
        });

        Craft::info(
            Craft::t('flatworld', '{name} plugin loaded', [
                'name' => $this->name,
            ]),
            __METHOD__,
        );
    }

    /**
     * Logs an informational message to our custom log target.
     */
    public static function info(string $message): void
    {
        Craft::info($message, 'flatworld');
    }

    /**
     * Logs an error message to our custom log target.
     */
    public static function error(string $message): void
    {
        Craft::error($message, 'flatworld');
    }

    private function initServices()
    {
        $this->setComponents([
            'logger' => Logger::class,
            'mailer' => Mailer::class,
            'ratesService' => Rates::class,
            'ratesApi' => RatesApi::class,
        ]);
    }

    /**
     * Registers a custom log target, keeping the format as simple as possible.
     */
    private function _registerLogTarget(): void
    {
        Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
            'name' => 'flatworld',
            'categories' => ['flatworld'],
            'level' => LogLevel::INFO,
            'logContext' => false,
            'allowLineBreaks' => false,
            'formatter' => new LineFormatter(
                format: "%datetime% %message%\n",
                dateFormat: 'Y-m-d H:i:s',
            ),
        ]);
    }
}
