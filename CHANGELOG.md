# Flatworld Changelog

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
