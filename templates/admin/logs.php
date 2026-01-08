<?php
/**
 * Admin Logs Template
 *
 * @package SAP_WooCommerce_Sync
 * @since   1.0.0
 */

defined('ABSPATH') || exit;

$current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
$per_page = 50;
$total_pages = ceil($total / $per_page);
?>

<div class="wrap sap-wc-sync-logs">
    <h1>
        <?php esc_html_e('SAP Sync Logs', 'sap-woocommerce-sync'); ?>
    </h1>

    <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=sap-wc-sync')); ?>" class="button">
            &larr;
            <?php esc_html_e('Back to Dashboard', 'sap-woocommerce-sync'); ?>
        </a>
    </p>

    <?php if (empty($logs)): ?>
        <p>
            <?php esc_html_e('No logs found.', 'sap-woocommerce-sync'); ?>
        </p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 150px;">
                        <?php esc_html_e('Date', 'sap-woocommerce-sync'); ?>
                    </th>
                    <th style="width: 100px;">
                        <?php esc_html_e('Type', 'sap-woocommerce-sync'); ?>
                    </th>
                    <th style="width: 80px;">
                        <?php esc_html_e('Status', 'sap-woocommerce-sync'); ?>
                    </th>
                    <th style="width: 80px;">
                        <?php esc_html_e('WC ID', 'sap-woocommerce-sync'); ?>
                    </th>
                    <th style="width: 100px;">
                        <?php esc_html_e('SAP ID', 'sap-woocommerce-sync'); ?>
                    </th>
                    <th>
                        <?php esc_html_e('Message', 'sap-woocommerce-sync'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <?php echo esc_html($log->created_at); ?>
                        </td>
                        <td>
                            <?php echo esc_html($log->sync_type); ?>
                        </td>
                        <td>
                            <span class="log-status log-status-<?php echo esc_attr($log->status); ?>">
                                <?php echo esc_html(ucfirst($log->status)); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo esc_html($log->wc_id ?: '-'); ?>
                        </td>
                        <td>
                            <?php echo esc_html($log->sap_id ?: '-'); ?>
                        </td>
                        <td>
                            <?php echo esc_html($log->message); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo wp_kses_post(
                        paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page,
                        ])
                    );
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>