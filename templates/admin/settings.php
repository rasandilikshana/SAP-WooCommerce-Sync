<?php
/**
 * Admin Settings Template
 *
 * @package SAP_WooCommerce_Sync
 * @since   1.0.0
 */

defined('ABSPATH') || exit;
?>

<div class="wrap sap-wc-sync-settings">
    <h1>
        <?php esc_html_e('SAP WooCommerce Sync Settings', 'sap-woocommerce-sync'); ?>
    </h1>

    <form method="post" action="options.php">
        <?php
        settings_fields('sap_wc_sync_settings');
        do_settings_sections('sap-wc-sync-settings');
        ?>

        <div class="sap-wc-sync-connection-test">
            <h2>
                <?php esc_html_e('Test Connection', 'sap-woocommerce-sync'); ?>
            </h2>
            <p class="description">
                <?php esc_html_e('Save settings first, then test the connection.', 'sap-woocommerce-sync'); ?>
            </p>
            <button type="button" class="button" id="test-connection">
                <?php esc_html_e('Test SAP Connection', 'sap-woocommerce-sync'); ?>
            </button>
            <span id="connection-result"></span>
        </div>

        <?php submit_button(); ?>
    </form>
</div>