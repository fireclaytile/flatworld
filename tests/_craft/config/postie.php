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

        'displayDebug' => true,

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
