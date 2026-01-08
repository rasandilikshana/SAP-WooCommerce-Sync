<?php
/**
 * Plugin deactivation handler.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Jehankandy\SAP_WooCommerce_Sync;

/**
 * Fired during plugin deactivation.
 *
 * This class handles all tasks that need to be performed
 * when the plugin is deactivated. Data is preserved for reactivation.
 *
 * @since 1.0.0
 */
class Deactivator
{

    /**
     * Run deactivation routine.
     *
     * - Clear scheduled events
     * - Clear transients
     * - Flush rewrite rules
     *
     * Note: We do NOT delete data on deactivation.
     * Data cleanup happens only on uninstall.
     *
     * @since 1.0.0
     * @return void
     */
    public static function deactivate(): void
    {
        self::clear_scheduled_events();
        self::clear_transients();

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    /**
     * Clear all scheduled events.
     *
     * @since 1.0.0
     * @return void
     */
    private static function clear_scheduled_events(): void
    {
        // Clear WP Cron events.
        wp_clear_scheduled_hook('sap_wc_sync_log_cleanup');

        // Clear Action Scheduler events.
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions('sap_wc_sync_order');
            as_unschedule_all_actions('sap_wc_pull_stock');
            as_unschedule_all_actions('sap_wc_sync_product');
            as_unschedule_all_actions('sap_wc_full_stock_sync');
        }
    }

    /**
     * Clear plugin transients.
     *
     * @since 1.0.0
     * @return void
     */
    private static function clear_transients(): void
    {
        global $wpdb;

        // Delete all plugin transients.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '%_transient_sap_wc_sync_%',
                '%_transient_timeout_sap_wc_sync_%'
            )
        );

        // Clear object cache if available.
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('sap_wc_sync');
        }
    }
}
