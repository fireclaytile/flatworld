# Flatworld Changelog

## 2.0.3 - 2024-04-30

-   Fix AssetManager method call in provider class.

## 2.0.2 - 2024-04-22

-   Filter out rates that have a null Total value that were returned by the API.

## 2.0.1 - 2024-02-14

-   Added order ID to error messages.

## 2.0.0 - 2023-12-20

-   Updated to work with Craft 4 and Commerce 4

## 1.3.0 - 2023-12-4

-   Added ECS tool and run for pre-craft-4 cleanup
-   Added craftcms/phpstan and run for pre-craft-4 cleanup
-   Created `OrderValidator` service class and moved relevant code into it.
-   Created `OrderMetadata` service class and moved relevant code into it.
-   Moved shipping request creation logic to model.
-   Removed some invalid error handling code in `Flatworld` provider class.
-   Improved code readability and error handling on `SalesforceRestConnection`
-   Removed unnecessary property on `RestDetails`

## 1.2.0 - 2023-11-06

-   Rates now will be filtered based on whether or not they are using a Carrier Service that is enabled in the Postie settings. This way, getting Cheapest and Fastest rates don't return rates from carriers that are not enabled in the settings.

## 1.1.3 - 2023-10-31

-   Don't send error emails if Craft devMode is enabled.
-   Allow null rates to be returned from the Shipping Rates service.

## 1.1.2 - 2023-10-30

-   Ensure TransitDays is a numeric value.
-   Turn Logger into a static class.
-   Use constants in Mailer class.
-   Handle possible empty or null rates array in ShippingRates service.

## 1.1.1 - 2023-10-23

-   Finish simplifiying flat rate handle usage (remove `tradeFlatRateCarrierName` setting)

## 1.1.0 - 2023-10-21

-   Simplify flat rate handle usage.
-   Improve rates service readability.

## 1.0.0 - 2023-10-19

-   Release v1.0.0
-   Add rate type to returned rates array
-   Documentation updates

## 0.9.19 - 2023-10-13

-   Update suggested configuration in README
-   Add `tradeFlatRateCarrierName` and `tradeFlatRateCarrierCost` settings
-   Fix flat rate-related manipulation of rates
-   Some code cleanup and refactoring

## 0.9.18 - 2023-10-11

-   Improved logging of cheapest and quickest rates

## 0.9.17 - 2023-10-10

-   Add `enableLiftGateRates` setting. This will allow you to disable lift gate rates if you don't want them.
-   Improve rate memoized cachekey generation.

## 0.9.16 - 2023-10-03

-   Bugfix for flat rate handle
-   Bugfix for merch-only orders

## 0.9.15 - 2023-10-03

-   Bugfix

## 0.9.14 - 2023-10-03

-   Refactor soem ShippingRates code to make it easier to handle and test.
-   Quickest Rate now factors in the EstimatedDeliveryTime.

## 0.9.13 - 2023-09-29

-   Account for null TransitDays data

## 0.9.12 - 2023-09-29

-   Include liftGate in cacheKey for rates

## 0.9.11 - 2023-09-28

-   set `liftGate` on Shipping Request

## 0.9.10 - 2023-09-28

-   Removed `weightThreshold` setting

## 0.9.9 - 2023-09-28

-   Bugfix and setting clarification

## 0.9.8 - 2023-09-27

-   Set cheapest and quickest rate service handles properly when they are LTL rates.

## 0.9.7 - 2023-09-26

-   Fixed settings check in RatesAPI service

## 0.9.6 - 2023-09-26

-   Fixed some error reporting.
-   Set ShippingRequest LineItems to use the same method of determining the productId as the main Salesforce plugin.
-   Fixed settings template so a couple settings actually get saved.

## 0.9.5 - 2023-09-26

-   Fix Rates service instantiation.
-   Fix some lack of "happy path" testing.

## 0.9.4 - 2023-09-26

-   Add enableErrorEmailMessages setting.
-   Fix default on other settings.
-   Fix Rates service instantiation.

## 0.9.3 - 2023-09-26

-   Fix type mismatch on quickest function, for real this time?

## 0.9.2 - 2023-09-26

-   Add suggestEnvVars to enableSalesforceSandbox setting. TODO: maybe we don't need this setting in this plugin?
-   Handle type mismatch on quickest and cheapest functions

## 0.9.1 - 2023-09-26

-   Fix Connection Test
-   Fix SalesforceAPI Integration
-   Remove other Paceject-specific code

## 0.9.0 - 2023-09-25

-   Lots of cleanup/refactoring/removing of unused code
-   Modified settings
-   Added SalesforceAPI integration
-   Ready for testing on local dev environment only at this time.

## 0.8.0 - 2023-09-14

### Added

-   Initial commit
-   NOT READY FOR TESTING WITH A CRAFT INSTALLATION YET.
