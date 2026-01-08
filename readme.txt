=== SAP WooCommerce Sync ===
Contributors: jehankandy
Tags: sap, woocommerce, sync, integration, erp, inventory, orders
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Synchronizes inventory, orders, and products between WooCommerce and SAP Business One via the SAP Service Layer API.

== Description ==

SAP WooCommerce Sync provides bidirectional synchronization between your WooCommerce store and SAP Business One ERP system.

**Features:**

* **Stock Synchronization** - Automatically pull stock levels from SAP to WooCommerce
* **Order Synchronization** - Push WooCommerce orders to SAP as Sales Orders
* **Customer Management** - Auto-create SAP Business Partners from WooCommerce customers
* **Product Mapping** - Map WooCommerce products to SAP Items via SKU
* **Background Processing** - All sync operations run in the background using Action Scheduler
* **Retry Mechanism** - Failed operations are automatically retried with exponential backoff
* **Detailed Logging** - Track all sync operations with filterable logs

**Requirements:**

* WordPress 6.0 or higher
* WooCommerce 8.0 or higher
* PHP 8.0 or higher
* SAP Business One with Service Layer enabled
* SSL certificate for SAP connection

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/sap-woocommerce-sync/`
2. Run `composer install` in the plugin directory
3. Activate the plugin through the 'Plugins' screen in WordPress
4. Go to WooCommerce → SAP Settings to configure your SAP connection
5. Test the connection and start syncing!

== Frequently Asked Questions ==

= What SAP version is required? =

SAP Business One 9.3 or higher with Service Layer enabled.

= Is HTTPS required? =

Yes, all SAP Service Layer connections must use HTTPS for security.

= How often does stock sync run? =

Stock sync interval is configurable, default is every 5 minutes.

= What happens if an order sync fails? =

Failed orders are automatically retried up to 5 times with exponential backoff. After max retries, they're moved to a dead letter queue for manual review.

== Changelog ==

= 1.0.0 =
* Initial release
* Stock synchronization from SAP to WooCommerce
* Order synchronization from WooCommerce to SAP
* Customer creation in SAP
* Admin dashboard and settings
* Logging and monitoring

== Upgrade Notice ==

= 1.0.0 =
Initial release.
