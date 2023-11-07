# Flatworld plugin for Craft CMS 3.x

Craft Commerce plugin to provide Postie with an additional shipping provider for Flatworld rates.

## Requirements

This plugin requires Craft CMS 3.x or later, Craft Commerce 3.x, and the Postie Craft CMS Plugin.

## Installation

To install the plugin, follow these instructions.

1.  Open your terminal and go to your Craft project:

        cd /path/to/project

2.  Then tell Composer to load the plugin:

        composer require fireclaytile/flatworld

3.  In the Control Panel, go to Settings → Plugins and click the "Install" button for Flatworld.

## Overview

Provides live shipping rates during checkout from Flatworld (https://flatworldgs.com/), via a SalesForce API.

## Configuration

Please note: `GROUND` is a special class of service that is used for flat rate carrier matching. Should be used for the "Trade Customer Flat Rate Carrier Name" setting.

```
<?php
/**
 * Postie / Flatworld plugin for Craft CMS 3.x
 *
 * Craft Commerce plugin to provide Postie with an additional shipping provider.
 *
 * @link      https://github.com/fireclaytile
 * @copyright Copyright (c) 2023 Fireclay Tile
 */

use craft\helpers\App;

return [
    // Global settings
    '*' => [
        'pluginName' => 'Postie',

        'hasCpSection' => false,

        'enableCaching' => true,

        'displayDebug' => false,

        'displayErrors' => false,

        'providers' => [
            'flatworld' => [
                'settings' => [
                    'carrierClassOfServices' => [
                        'ABF_FREIGHT' => 'ABF Freight',
                        'DEPENDABLE_HIGHWAY_EXPRESS' =>
                            'Dependable Highway Express',
                        'FEDEX_FREIGHT_PRIORITY' => 'FedEx Freight Priority',
                        'OAK_HARBOR' => 'Oak Harbor',
                        'OLD_DOMINION_FREIGHT_LINE' =>
                            'Old Dominion Freight Line',
                        'R_&_L_CARRIERS' => 'R & L Carriers',
                        'FEDEX_GROUND' => 'FedEx Ground',
                        'FEDEX_EXPRESS_SAVER' => 'FedEx Express Saver',
                        'FEDEX_2DAY' => 'FedEx 2Day',
                        'FEDEX_2DAY_A.M.' => 'FedEx 2Day A.M.',
                        'FEDEX_STANDARD_OVERNIGHT' => 'FedEx Standard Overnight',
                        'FEDEX_PRIORITY_OVERNIGHT' => 'FedEx Priority Overnight',
                        'FEDEX_FIRST_OVERNIGHT' => 'FedEx First Overnight',
                        'UPS_2ND_DAY_AIR' => 'UPS 2nd Day Air',
                        'UPS_SECOND_DAY_AIR_AM' => 'UPS Second Day Air AM',
                        'UPS_3_DAY_SELECT' => 'UPS 3 Day Select',
                        'UPS_GROUND' => 'UPS Ground',
                        'UPS_NEXT_DAY_AIR' => 'UPS Next Day Air',
                        'UPS_NEXT_DAY_AIR_SAVER' => 'UPS Next Day Air Saver',
                        'UPS_NEXT_DAY_AIR_EARLY_AM' =>
                            'UPS Next Day Air Early AM',
                        'USPS_FIRST_CLASS' => 'USPS First Class',
                        'USPS_GROUNDADVANTAGE' => 'USPS GroundAdvantage',
                        'USPS_PARCEL_SELECT' => 'USPS Parcel Select',
                        'USPS_EXPRESS' => 'USPS Express',
                        'USPS_PRIORITY' => 'USPS Priority',
                        'USPS_FLAT_RATE' => 'USPS Flat Rate',
                        'GROUND' => 'Ground',
                    ],
                ],
            ],
        ],
    ],

    // Dev environment settings
    'local' => [
        'enableCaching' => false,
    ],

    // Staging environment settings
    'development' => [],

    // Staging environment settings
    'staging' => [],

    // Production environment settings
    'production' => [],
];
```

## Usage

To get rates for a cart/order. simply use the plugin's template variable function.

Example:

```twig
{% set rates = craft.flatworld.getRates(cart.id) %}
```

The rates are returned as an array. Generally, this will return either:

1.  An empty array if the order is not found or has no line items.
2.  An array of 2 rates, with the first being the cheapest and the second being the fastest.
3.  An array of 1 rate if there is only 1 rate available, or if the cheapest and fastest rates are the same.

Example:

```
Array
  (
    [FLAT_RATE_SHIPPING] => Array
      (
        [arrival] => 3-7 days
        [transitTime] => 3
        [arrivalDateText] => 2023/10/23
        [amount] => 8
        [type] => parcel
      )

    [UPS_SECOND_DAY_AIR_AM] => Array
      (
        [arrival] => 1-3 days
        [transitTime] => 1
        [arrivalDateText] => 2023/10/19
        [amount] => 48.06
        [type] => parcel
      )
  )
```

Notes:

-   'type' will either be 'parcel' or 'ltl'.

## Unit Testing

Unit testing is mainly focused on testing functionality of the Provider.
For best results, since this is a Craft CMS plugin, why not set up a ddev environment and run the tests there?
Don't worry, the .ddev folder is set to be ignored by git within this project.

```
// Runs all tests
ddev php vendor/bin/codecept run unit

// Runs specific test
ddev php vendor/bin/codecept run unit ./tests/unit/FlatworldTest.php

// Runs specific test case
ddev php vendor/bin/codecept run unit ./tests/unit/FlatworldTest.php:testResponseRates
```

Brought to you by [Fireclay Tile](https://github.com/fireclaytile)
