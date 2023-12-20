<?php
/**
 * Postie / Flatworld plugin for Craft CMS 4.x
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
                        'FEDEX_GROUND' => 'FedEx Ground',
                        'FEDEX_EXPRESS_SAVER' => 'FedEx Express Saver',
                        'FEDEX_2DAY' => 'FedEx 2Day',
                        'FEDEX_2DAY_A.M.' => 'FedEx 2Day A.M.',
                        'FEDEX_STANDARD_OVERNIGHT' =>
                            'FedEx Standard Overnight',
                        'FEDEX_PRIORITY_OVERNIGHT' =>
                            'FedEx Priority Overnight',
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
                        'FLAT_RATE_SHIPPING' => 'Flat Rate Shipping',
                        'TRADE_CUSTOMER_SHIPPING' => 'Trade Customer Shipping',
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
