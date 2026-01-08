<?php
/**
 * Plugin activation handler.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Rasandilikshana\SAP_WooCommerce_Sync;

use Rasandilikshana\SAP_WooCommerce_Sync\Data\Database;

/**
 * Fired during plugin activation.
 *
 * This class handles all tasks that need to be performed
 * when the plugin is activated.
 *
 * @since 1.0.0
 */
class Activator
{

    /**
     * Run activation routine.
     *
     * - Create custom database tables
     * - Set default options
     * - Schedule initial cron jobs
     * - Flush rewrite rules if needed
     *
     * @since 1.0.0
     * @return void
     */
    public static function activate(): void
    {
        self::create_tables();
        self::set_default_options();
        self::schedule_events();
        self::set_version();

        // Clear the permalinks.
        flush_rewrite_rules();
    }

    /**
     * Create custom database tables.
     *
     * @since 1.0.0
     * @return void
     */
    private static function create_tables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Sync log table.
        $sql_sync_log = "CREATE TABLE {$prefix}sap_wc_sync_log (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			sync_type varchar(50) NOT NULL,
			wc_id bigint(20) UNSIGNED DEFAULT NULL,
			sap_id varchar(100) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			direction varchar(10) NOT NULL DEFAULT 'push',
			message text DEFAULT NULL,
			request_data longtext DEFAULT NULL,
			response_data longtext DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY sync_type (sync_type),
			KEY wc_id (wc_id),
			KEY sap_id (sap_id),
			KEY status (status),
			KEY created_at (created_at)
		) $charset_collate;";

        dbDelta($sql_sync_log);

        // Product mapping table.
        $sql_product_map = "CREATE TABLE {$prefix}sap_wc_product_map (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			wc_product_id bigint(20) UNSIGNED NOT NULL,
			sap_item_code varchar(100) NOT NULL,
			sync_enabled tinyint(1) NOT NULL DEFAULT 1,
			last_synced_at datetime DEFAULT NULL,
			last_stock_qty decimal(15,4) DEFAULT NULL,
			last_price decimal(15,4) DEFAULT NULL,
			sync_status varchar(20) NOT NULL DEFAULT 'pending',
			error_message text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY wc_product_id (wc_product_id),
			UNIQUE KEY sap_item_code (sap_item_code),
			KEY sync_enabled (sync_enabled),
			KEY sync_status (sync_status)
		) $charset_collate;";

        dbDelta($sql_product_map);

        // Order mapping table.
        $sql_order_map = "CREATE TABLE {$prefix}sap_wc_order_map (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			wc_order_id bigint(20) UNSIGNED NOT NULL,
			sap_doc_entry bigint(20) UNSIGNED DEFAULT NULL,
			sap_doc_num bigint(20) UNSIGNED DEFAULT NULL,
			sap_doc_type varchar(20) NOT NULL DEFAULT 'Orders',
			sync_status varchar(20) NOT NULL DEFAULT 'pending',
			sync_attempts int(11) NOT NULL DEFAULT 0,
			last_error text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			synced_at datetime DEFAULT NULL,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY wc_order_id (wc_order_id),
			KEY sap_doc_entry (sap_doc_entry),
			KEY sync_status (sync_status)
		) $charset_collate;";

        dbDelta($sql_order_map);

        // Customer mapping table.
        $sql_customer_map = "CREATE TABLE {$prefix}sap_wc_customer_map (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			wc_customer_id bigint(20) UNSIGNED NOT NULL,
			wc_email varchar(255) NOT NULL,
			sap_card_code varchar(50) DEFAULT NULL,
			sap_card_name varchar(255) DEFAULT NULL,
			sync_status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY wc_customer_id (wc_customer_id),
			KEY wc_email (wc_email),
			KEY sap_card_code (sap_card_code)
		) $charset_collate;";

        dbDelta($sql_customer_map);

        // Failed jobs (dead letter queue) table.
        $sql_failed_jobs = "CREATE TABLE {$prefix}sap_wc_failed_jobs (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			job_type varchar(50) NOT NULL,
			job_group varchar(50) NOT NULL,
			payload longtext NOT NULL,
			error_message text NOT NULL,
			attempts int(11) NOT NULL DEFAULT 0,
			max_attempts int(11) NOT NULL DEFAULT 5,
			failed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			resolved_at datetime DEFAULT NULL,
			resolution varchar(20) DEFAULT NULL,
			PRIMARY KEY (id),
			KEY job_type (job_type),
			KEY resolved_at (resolved_at)
		) $charset_collate;";

        dbDelta($sql_failed_jobs);
    }

    /**
     * Set default plugin options.
     *
     * @since 1.0.0
     * @return void
     */
    private static function set_default_options(): void
    {
        $default_settings = [
            'sap_service_url' => '',
            'sap_company_db' => '',
            'sap_username' => '',
            'stock_sync_interval' => 5,
            'auto_sync_orders' => true,
            'auto_create_customers' => true,
            'default_warehouse' => '',
            'default_tax_code' => '',
            'log_level' => 'info',
            'log_retention_days' => 30,
        ];

        // Only set if not already exists.
        if (false === get_option('sap_wc_sync_settings')) {
            add_option('sap_wc_sync_settings', $default_settings);
        }
    }

    /**
     * Schedule WP Cron events as fallback.
     *
     * Note: We prefer Action Scheduler which is initialized in the Plugin class.
     * This is a fallback for edge cases.
     *
     * @since 1.0.0
     * @return void
     */
    private static function schedule_events(): void
    {
        // Log cleanup cron.
        if (!wp_next_scheduled('sap_wc_sync_log_cleanup')) {
            wp_schedule_event(time(), 'daily', 'sap_wc_sync_log_cleanup');
        }
    }

    /**
     * Set the plugin version in the database.
     *
     * @since 1.0.0
     * @return void
     */
    private static function set_version(): void
    {
        $current_version = get_option('sap_wc_sync_version', '0.0.0');

        if (version_compare($current_version, SAP_WC_SYNC_VERSION, '<')) {
            // Run any upgrade routines here.
            self::run_upgrades($current_version);

            // Update version.
            update_option('sap_wc_sync_version', SAP_WC_SYNC_VERSION);
        }
    }

    /**
     * Run upgrade routines for version migrations.
     *
     * @since 1.0.0
     * @param string $from_version The version upgrading from.
     * @return void
     */
    private static function run_upgrades(string $from_version): void
    {
        // Example upgrade routine:
        // if ( version_compare( $from_version, '1.1.0', '<' ) ) {
        //     self::upgrade_to_1_1_0();
        // }
    }
}
