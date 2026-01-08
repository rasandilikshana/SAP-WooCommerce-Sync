<?php
/**
 * SAP WooCommerce Sync
 *
 * @package           SAP_WooCommerce_Sync
 * @author            Jehan Kandy
 * @copyright         2024 Jehan Kandy
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       SAP WooCommerce Sync
 * Plugin URI:        https://example.com/sap-woocommerce-sync
 * Description:       Synchronizes inventory, orders, and products between WooCommerce and SAP Business One via the SAP Service Layer API.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Jehan Kandy
 * Author URI:        https://example.com
 * Text Domain:       sap-woocommerce-sync
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least: 8.0
 * WC tested up to:   9.0
 */

declare(strict_types=1);

namespace Jehankandy\SAP_WooCommerce_Sync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @var string
 */
define( 'SAP_WC_SYNC_VERSION', '1.0.0' );

/**
 * Plugin file path.
 *
 * @var string
 */
define( 'SAP_WC_SYNC_FILE', __FILE__ );

/**
 * Plugin directory path.
 *
 * @var string
 */
define( 'SAP_WC_SYNC_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 *
 * @var string
 */
define( 'SAP_WC_SYNC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 *
 * @var string
 */
define( 'SAP_WC_SYNC_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Minimum PHP version required.
 *
 * @var string
 */
define( 'SAP_WC_SYNC_MIN_PHP', '8.0' );

/**
 * Minimum WordPress version required.
 *
 * @var string
 */
define( 'SAP_WC_SYNC_MIN_WP', '6.0' );

/**
 * Minimum WooCommerce version required.
 *
 * @var string
 */
define( 'SAP_WC_SYNC_MIN_WC', '8.0' );

/**
 * Check if the server environment meets minimum requirements.
 *
 * @since 1.0.0
 * @return bool True if requirements are met, false otherwise.
 */
function sap_wc_sync_check_requirements(): bool {
	$errors = [];

	// Check PHP version.
	if ( version_compare( PHP_VERSION, SAP_WC_SYNC_MIN_PHP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required PHP version, 2: Current PHP version */
			__( 'SAP WooCommerce Sync requires PHP %1$s or higher. You are running PHP %2$s.', 'sap-woocommerce-sync' ),
			SAP_WC_SYNC_MIN_PHP,
			PHP_VERSION
		);
	}

	// Check WordPress version.
	global $wp_version;
	if ( version_compare( $wp_version, SAP_WC_SYNC_MIN_WP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required WordPress version, 2: Current WordPress version */
			__( 'SAP WooCommerce Sync requires WordPress %1$s or higher. You are running WordPress %2$s.', 'sap-woocommerce-sync' ),
			SAP_WC_SYNC_MIN_WP,
			$wp_version
		);
	}

	// Check if WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		$errors[] = __( 'SAP WooCommerce Sync requires WooCommerce to be installed and activated.', 'sap-woocommerce-sync' );
	} elseif ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, SAP_WC_SYNC_MIN_WC, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required WooCommerce version, 2: Current WooCommerce version */
			__( 'SAP WooCommerce Sync requires WooCommerce %1$s or higher. You are running WooCommerce %2$s.', 'sap-woocommerce-sync' ),
			SAP_WC_SYNC_MIN_WC,
			WC_VERSION
		);
	}

	if ( ! empty( $errors ) ) {
		// Store errors for admin notice.
		update_option( 'sap_wc_sync_activation_errors', $errors );
		return false;
	}

	delete_option( 'sap_wc_sync_activation_errors' );
	return true;
}

/**
 * Display admin notice for activation errors.
 *
 * @since 1.0.0
 * @return void
 */
function sap_wc_sync_activation_errors_notice(): void {
	$errors = get_option( 'sap_wc_sync_activation_errors', [] );

	if ( empty( $errors ) ) {
		return;
	}

	echo '<div class="notice notice-error">';
	echo '<p><strong>' . esc_html__( 'SAP WooCommerce Sync cannot be activated:', 'sap-woocommerce-sync' ) . '</strong></p>';
	echo '<ul style="list-style: disc; margin-left: 20px;">';
	foreach ( $errors as $error ) {
		echo '<li>' . esc_html( $error ) . '</li>';
	}
	echo '</ul>';
	echo '</div>';
}
add_action( 'admin_notices', __NAMESPACE__ . '\\sap_wc_sync_activation_errors_notice' );

/**
 * Load the Composer autoloader.
 *
 * @since 1.0.0
 * @return bool True if autoloader was loaded, false otherwise.
 */
function sap_wc_sync_load_autoloader(): bool {
	$autoloader = SAP_WC_SYNC_DIR . 'vendor/autoload.php';

	if ( file_exists( $autoloader ) ) {
		require_once $autoloader;
		return true;
	}

	// Fall back to custom autoloader.
	require_once SAP_WC_SYNC_DIR . 'includes/class-autoloader.php';
	Autoloader::register();
	return true;
}

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function sap_wc_sync_init(): void {
	// Load translations.
	load_plugin_textdomain(
		'sap-woocommerce-sync',
		false,
		dirname( SAP_WC_SYNC_BASENAME ) . '/languages'
	);

	// Check requirements.
	if ( ! sap_wc_sync_check_requirements() ) {
		return;
	}

	// Load autoloader.
	if ( ! sap_wc_sync_load_autoloader() ) {
		return;
	}

	// Initialize the plugin.
	Plugin::get_instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\sap_wc_sync_init', 10 );

/**
 * Run activation routine.
 *
 * @since 1.0.0
 * @return void
 */
function sap_wc_sync_activate(): void {
	// Load autoloader for activation.
	if ( ! sap_wc_sync_load_autoloader() ) {
		return;
	}

	Activator::activate();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\sap_wc_sync_activate' );

/**
 * Run deactivation routine.
 *
 * @since 1.0.0
 * @return void
 */
function sap_wc_sync_deactivate(): void {
	// Load autoloader for deactivation.
	if ( ! sap_wc_sync_load_autoloader() ) {
		return;
	}

	Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\sap_wc_sync_deactivate' );

/**
 * Declare HPOS compatibility.
 *
 * @since 1.0.0
 * @return void
 */
function sap_wc_sync_declare_hpos_compatibility(): void {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
}
add_action( 'before_woocommerce_init', __NAMESPACE__ . '\\sap_wc_sync_declare_hpos_compatibility' );
