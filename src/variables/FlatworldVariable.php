<?php
/**
 * Flatworld plugin for Craft CMS 3.x
 *
 * Craft Commerce plugin to provide Postie with an additional shipping provider.
 *
 * @link      https://github.com/fireclaytile
 * @copyright Copyright (c) 2023 Fireclay Tile
 */

namespace fireclaytile\flatworld\variables;

use craft\helpers\Json;
use verbb\postie\Postie;
use craft\commerce\elements\Order;

/**
 * Class FlatworldVariable
 *
 * @author     Fireclay Tile
 * @package    fireclaytile\flatworld\variables
 */
class FlatworldVariable {
    /**
	 * @param $orderId
	 * @return array
	 */
	public function getRates($orderId): array {
		$flatworld = Postie::getInstance()->getProviders()->getProviderByHandle('flatworld');

		$flatworld->displayDebugMessage('FlatworldVariable.php :: getRates :: Order ID: '.$orderId);

		$order = Order::find()->id($orderId)->one();

		if (! empty($order)) {
			$flatworld->displayDebugMessage('FlatworldVariable.php :: getRates :: Found an order');

			$rates = $flatworld->fetchShippingRates($order);

			if ($rates) {
				$flatworld->displayDebugMessage('FlatworldVariable.php :: getRates :: Rates: '.Json::encode($rates));

				return $rates;
			}

			$flatworld->displayDebugMessage('FlatworldVariable.php :: getRates :: Rates were empty');

			return [];
		}

		$flatworld->displayDebugMessage('FlatworldVariable.php :: getRates :: Order was empty');

		return [];
	}
}
