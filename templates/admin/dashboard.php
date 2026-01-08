<?php
/**
 * Admin Dashboard Template
 *
 * @package SAP_WooCommerce_Sync
 * @since   1.0.0
 */

defined('ABSPATH') || exit;
?>

<div class="wrap sap-wc-sync-dashboard">
    <h1>
        <?php esc_html_e('SAP WooCommerce Sync', 'sap-woocommerce-sync'); ?>
    </h1>

    <?php if (!$is_configured): ?>
        <div class="notice notice-warning">
            <p>
                <strong>
                    <?php esc_html_e('Not Configured', 'sap-woocommerce-sync'); ?>
                </strong>
                <?php esc_html_e('Please configure your SAP connection settings.', 'sap-woocommerce-sync'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sap-wc-sync-settings')); ?>">
                    <?php esc_html_e('Go to Settings', 'sap-woocommerce-sync'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <div class="sap-wc-sync-cards">
        <!-- Connection Status Card -->
        <div class="sap-wc-sync-card">
            <h2>
                <?php esc_html_e('Connection Status', 'sap-woocommerce-sync'); ?>
            </h2>
            <div class="sap-wc-sync-card-content">
                <div id="sap-connection-status" class="status-indicator">
                    <?php if ($is_configured): ?>
                        <span class="status-dot status-unknown"></span>
                        <span class="status-text">
                            <?php esc_html_e('Click to test', 'sap-woocommerce-sync'); ?>
                        </span>
                    <?php else: ?>
                        <span class="status-dot status-error"></span>
                        <span class="status-text">
                            <?php esc_html_e('Not configured', 'sap-woocommerce-sync'); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if ($is_configured): ?>
                    <button type="button" class="button" id="test-connection">
                        <?php esc_html_e('Test Connection', 'sap-woocommerce-sync'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Queue Status Card -->
        <div class="sap-wc-sync-card">
            <h2>
                <?php esc_html_e('Queue Status', 'sap-woocommerce-sync'); ?>
            </h2>
            <div class="sap-wc-sync-card-content">
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td>
                                <?php esc_html_e('Pending Orders', 'sap-woocommerce-sync'); ?>
                            </td>
                            <td><strong>
                                    <?php echo esc_html((string) $pending_orders); ?>
                                </strong></td>
                        </tr>
                        <tr>
                            <td>
                                <?php esc_html_e('Pending Stock Sync', 'sap-woocommerce-sync'); ?>
                            </td>
                            <td><strong>
                                    <?php echo esc_html((string) $pending_stock); ?>
                                </strong></td>
                        </tr>
                        <tr>
                            <td>
                                <?php esc_html_e('Failed Jobs', 'sap-woocommerce-sync'); ?>
                            </td>
                            <td>
                                <strong class="<?php echo $failed_jobs > 0 ? 'error-text' : ''; ?>">
                                    <?php echo esc_html((string) $failed_jobs); ?>
                                </strong>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="sap-wc-sync-card">
            <h2>
                <?php esc_html_e('Quick Actions', 'sap-woocommerce-sync'); ?>
            </h2>
            <div class="sap-wc-sync-card-content">
                <p>
                    <button type="button" class="button button-primary" id="sync-stock-now" <?php disabled(!$is_configured); ?>>
                        <?php esc_html_e('Sync Stock Now', 'sap-woocommerce-sync'); ?>
                    </button>
                </p>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sap-wc-sync-logs')); ?>" class="button">
                        <?php esc_html_e('View Logs', 'sap-woocommerce-sync'); ?>
                    </a>
                </p>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sap-wc-sync-settings')); ?>"
                        class="button">
                        <?php esc_html_e('Settings', 'sap-woocommerce-sync'); ?>
                    </a>
                </p>
            </div>
        </div>
    </div>

    <!-- Sync Settings Summary -->
    <div class="sap-wc-sync-section">
        <h2>
            <?php esc_html_e('Current Settings', 'sap-woocommerce-sync'); ?>
        </h2>
        <table class="widefat">
            <tbody>
                <tr>
                    <td><strong>
                            <?php esc_html_e('SAP URL', 'sap-woocommerce-sync'); ?>
                        </strong></td>
                    <td>
                        <?php echo esc_html($settings['sap_service_url'] ?: __('Not set', 'sap-woocommerce-sync')); ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>
                            <?php esc_html_e('Company DB', 'sap-woocommerce-sync'); ?>
                        </strong></td>
                    <td>
                        <?php echo esc_html($settings['sap_company_db'] ?: __('Not set', 'sap-woocommerce-sync')); ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>
                            <?php esc_html_e('Stock Sync Interval', 'sap-woocommerce-sync'); ?>
                        </strong></td>
                    <td>
                        <?php
                        printf(
                            /* translators: %d: Number of minutes */
                            esc_html__('Every %d minutes', 'sap-woocommerce-sync'),
                            (int) ($settings['stock_sync_interval'] ?? 5)
                        );
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>
                            <?php esc_html_e('Auto Sync Orders', 'sap-woocommerce-sync'); ?>
                        </strong></td>
                    <td>
                        <?php echo !empty($settings['auto_sync_orders']) ? esc_html__('Yes', 'sap-woocommerce-sync') : esc_html__('No', 'sap-woocommerce-sync'); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>