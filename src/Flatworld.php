<?php
/**
 * Flatworld plugin for Craft CMS 3.x
 *
 * Craft Commerce plugin to provide Postie with an additional shipping provider.
 *
 * @link      https://github.com/fireclaytile
 * @copyright Copyright (c) 2023 Fireclay Tile
 */

namespace fireclaytile\flatworld;

use Craft;
use yii\base\Event;
use craft\base\Plugin;
use verbb\postie\services\Providers;
use craft\commerce\elements\Variant;
use craft\commerce\elements\Product;
use craft\web\twig\variables\CraftVariable;
use verbb\postie\controllers\PluginController;
use verbb\postie\events\RegisterProviderTypesEvent;
use fireclaytile\flatworld\variables\FlatworldVariable;
use verbb\postie\events\ModifyShippableVariantsEvent;
use fireclaytile\flatworld\providers\Flatworld as FlatworldProvider;

use fireclaytile\flatworld\services\Logger;
use fireclaytile\flatworld\services\Mailer;
use fireclaytile\flatworld\services\Rates;
use fireclaytile\flatworld\services\RatesApi;

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
    public $schemaVersion = '1.1.1';

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

    private function initServices()
    {
        $this->setComponents([
            'logger' => Logger::class,
            'mailer' => Mailer::class,
            'ratesService' => Rates::class,
            'ratesApi' => RatesApi::class,
        ]);
    }
}
