<?php
/**
 * WooCommerce Order Hooks class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/WooCommerce
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Rasandilikshana\SAP_WooCommerce_Sync\WooCommerce;

use Rasandilikshana\SAP_WooCommerce_Sync\Queue\Queue_Manager;
use Rasandilikshana\SAP_WooCommerce_Sync\Utilities\Helper;
use Rasandilikshana\SAP_WooCommerce_Sync\Utilities\Logger;

/**
 * Handles WooCommerce order events for SAP synchronization.
 *
 * @since 1.0.0
 */
class Order_Hooks
{

    /**
     * Queue manager instance.
     *
     * @since 1.0.0
     * @var Queue_Manager
     */
    private Queue_Manager $queue_manager;

    /**
     * Logger instance.
     *
     * @since 1.0.0
     * @var Logger
     */
    private Logger $logger;

    /**
     * Plugin settings.
     *
     * @since 1.0.0
     * @var array<string, mixed>
     */
    private array $settings;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param Queue_Manager        $queue_manager Queue manager instance.
     * @param Logger               $logger        Logger instance.
     * @param array<string, mixed> $settings      Plugin settings.
     */
    public function __construct(
        Queue_Manager $queue_manager,
        Logger $logger,
        array $settings
    ) {
        $this->queue_manager = $queue_manager;
        $this->logger = $logger;
        $this->settings = $settings;
    }

    /**
     * Handle order creation event.
     *
     * @since 1.0.0
     * @param int       $order_id    Order ID.
     * @param array     $posted_data Posted checkout data.
     * @param \WC_Order $order       Order object.
     * @return void
     */
    public function on_order_created(int $order_id, array $posted_data, \WC_Order $order): void
    {
        // Check if auto-sync is enabled.
        if (empty($this->settings['auto_sync_orders'])) {
            $this->logger->debug('Auto order sync disabled, skipping', [
                'order_id' => $order_id,
            ]);
            return;
        }

        // Check if order status is syncable.
        if (!Helper::is_syncable_status($order->get_status())) {
            $this->logger->debug('Order status not syncable on creation', [
                'order_id' => $order_id,
                'status' => $order->get_status(),
            ]);
            return;
        }

        // Queue the order for sync.
        $this->queue_order($order);
    }

    /**
     * Handle order status change event.
     *
     * @since 1.0.0
     * @param int       $order_id   Order ID.
     * @param string    $old_status Previous status.
     * @param string    $new_status New status.
     * @param \WC_Order $order      Order object.
     * @return void
     */
    public function on_order_status_changed(int $order_id, string $old_status, string $new_status, \WC_Order $order): void
    {
        // Check if auto-sync is enabled.
        if (empty($this->settings['auto_sync_orders'])) {
            return;
        }

        $this->logger->debug('Order status changed', [
            'order_id' => $order_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
        ]);

        // Check if transitioning to a syncable status.
        $was_syncable = Helper::is_syncable_status($old_status);
        $is_syncable = Helper::is_syncable_status($new_status);

        // If becoming syncable and wasn't before, queue it.
        if ($is_syncable && !$was_syncable) {
            $this->queue_order($order);
            return;
        }

        // Handle cancellation.
        if ('cancelled' === $new_status) {
            $this->handle_cancellation($order);
            return;
        }

        // Handle completion (might trigger delivery note in SAP).
        if ('completed' === $new_status && 'completed' !== $old_status) {
            $this->handle_completion($order);
        }
    }

    /**
     * Handle order refund event.
     *
     * @since 1.0.0
     * @param int $order_id  Order ID.
     * @param int $refund_id Refund ID.
     * @return void
     */
    public function on_order_refunded(int $order_id, int $refund_id): void
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $refund = wc_get_order($refund_id);

        if (!$refund) {
            return;
        }

        $this->logger->info('Order refunded', [
            'order_id' => $order_id,
            'refund_id' => $refund_id,
            'refund_amount' => $refund->get_amount(),
        ]);

        // Check if order was synced to SAP.
        $sap_doc_entry = $order->get_meta('_sap_doc_entry');

        if (empty($sap_doc_entry)) {
            $this->logger->debug('Order not synced to SAP, skipping refund sync', [
                'order_id' => $order_id,
            ]);
            return;
        }

        // Queue refund/credit memo creation.
        // This would be handled by a separate refund sync job.
        do_action('sap_wc_sync_order_refund', $order_id, $refund_id, $sap_doc_entry);
    }

    /**
     * Queue an order for SAP sync.
     *
     * @since 1.0.0
     * @param \WC_Order $order Order object.
     * @return void
     */
    private function queue_order(\WC_Order $order): void
    {
        $order_id = $order->get_id();

        // Check if already synced.
        $sap_doc_entry = $order->get_meta('_sap_doc_entry');

        if ($sap_doc_entry) {
            $this->logger->debug('Order already synced to SAP', [
                'order_id' => $order_id,
                'sap_doc_entry' => $sap_doc_entry,
            ]);
            return;
        }

        // Queue for sync.
        $action_id = $this->queue_manager->queue_order_sync($order_id);

        if ($action_id) {
            $order->add_order_note(
                sprintf(
                    /* translators: %d: Action scheduler job ID */
                    __('Order queued for SAP sync (Job #%d)', 'sap-woocommerce-sync'),
                    $action_id
                )
            );
        }
    }

    /**
     * Handle order cancellation.
     *
     * @since 1.0.0
     * @param \WC_Order $order Order object.
     * @return void
     */
    private function handle_cancellation(\WC_Order $order): void
    {
        $order_id = $order->get_id();

        // Cancel any pending sync jobs.
        $cancelled = $this->queue_manager->cancel_order_sync($order_id);

        if ($cancelled > 0) {
            $this->logger->info('Cancelled pending SAP sync jobs', [
                'order_id' => $order_id,
                'count' => $cancelled,
            ]);
        }

        // Check if already synced to SAP.
        $sap_doc_entry = $order->get_meta('_sap_doc_entry');

        if ($sap_doc_entry) {
            // Queue cancellation in SAP.
            do_action('sap_wc_sync_order_cancel', $order_id, $sap_doc_entry);

            $order->add_order_note(
                __('Order cancellation queued for SAP sync', 'sap-woocommerce-sync')
            );
        }
    }

    /**
     * Handle order completion.
     *
     * @since 1.0.0
     * @param \WC_Order $order Order object.
     * @return void
     */
    private function handle_completion(\WC_Order $order): void
    {
        $order_id = $order->get_id();

        // Check if synced to SAP.
        $sap_doc_entry = $order->get_meta('_sap_doc_entry');

        if (!$sap_doc_entry) {
            // Order not in SAP yet, queue for sync.
            $this->queue_order($order);
            return;
        }

        $this->logger->info('Order completed, may trigger delivery note', [
            'order_id' => $order_id,
            'sap_doc_entry' => $sap_doc_entry,
        ]);

        // Optionally trigger delivery note creation.
        // This could be a separate action/job.
        do_action('sap_wc_sync_order_complete', $order_id, $sap_doc_entry);
    }
}
