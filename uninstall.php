<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package SAP_WooCommerce_Sync
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Define plugin constants if not already defined.
if ( ! defined( 'SAP_WC_SYNC_DIR' ) ) {
	define( 'SAP_WC_SYNC_DIR', plugin_dir_path( __FILE__ ) );
}

// Load autoloader.
$autoloader = SAP_WC_SYNC_DIR . 'vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
	require_once $autoloader;
} else {
	require_once SAP_WC_SYNC_DIR . 'includes/class-autoloader.php';
	Rasandilikshana\SAP_WooCommerce_Sync\Autoloader::register();
}

/**
 * Check if we should remove all data on uninstall.
 *
 * This option is set in the plugin settings.
 */
$remove_all_data = get_option( 'sap_wc_sync_remove_data_on_uninstall', false );

if ( $remove_all_data ) {
	global $wpdb;

	// Delete custom tables.
	$tables = [
		$wpdb->prefix . 'sap_wc_sync_log',
		$wpdb->prefix . 'sap_wc_product_map',
		$wpdb->prefix . 'sap_wc_order_map',
		$wpdb->prefix . 'sap_wc_customer_map',
		$wpdb->prefix . 'sap_wc_failed_jobs',
	];

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}

	// Delete all options.
	$options = [
		'sap_wc_sync_version',
		'sap_wc_sync_settings',
		'sap_wc_sync_credentials',
		'sap_wc_sync_activation_errors',
		'sap_wc_sync_remove_data_on_uninstall',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete all transients.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			'%_transient_sap_wc_sync_%',
			'%_transient_timeout_sap_wc_sync_%'
		)
	);

	// Clear any scheduled actions (Action Scheduler).
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'sap_wc_sync_order' );
		as_unschedule_all_actions( 'sap_wc_pull_stock' );
		as_unschedule_all_actions( 'sap_wc_sync_product' );
		as_unschedule_all_actions( 'sap_wc_full_stock_sync' );
	}
}
