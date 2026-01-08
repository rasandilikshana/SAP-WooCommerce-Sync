<?php
/**
 * Order Sync Handler class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/Sync
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Jehankandy\SAP_WooCommerce_Sync\Sync;

use Jehankandy\SAP_WooCommerce_Sync\Exceptions\SAP_Exception;
use Jehankandy\SAP_WooCommerce_Sync\Exceptions\Validation_Exception;
use Jehankandy\SAP_WooCommerce_Sync\Mappers\Order_Mapper;
use Jehankandy\SAP_WooCommerce_Sync\SAP\Client;
use Jehankandy\SAP_WooCommerce_Sync\SAP\Response_Parser;
use Jehankandy\SAP_WooCommerce_Sync\Utilities\Logger;
use Jehankandy\SAP_WooCommerce_Sync\Utilities\Validator;

/**
 * Handles order synchronization from WooCommerce to SAP.
 *
 * @since 1.0.0
 */
class Order_Sync
{

    /**
     * SAP client instance.
     *
     * @since 1.0.0
     * @var Client
     */
    private Client $client;

    /**
     * Logger instance.
     *
     * @since 1.0.0
     * @var Logger
     */
    private Logger $logger;

    /**
     * Order mapper instance.
     *
     * @since 1.0.0
     * @var Order_Mapper|null
     */
    private ?Order_Mapper $mapper = null;

    /**
     * Customer sync instance.
     *
     * @since 1.0.0
     * @var Customer_Sync|null
     */
    private ?Customer_Sync $customer_sync = null;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param Client $client SAP client instance.
     * @param Logger $logger Logger instance.
     */
    public function __construct(Client $client, Logger $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Set order mapper.
     *
     * @since 1.0.0
     * @param Order_Mapper $mapper Mapper instance.
     * @return static
     */
    public function set_mapper(Order_Mapper $mapper): static
    {
        $this->mapper = $mapper;
        return $this;
    }

    /**
     * Set customer sync.
     *
     * @since 1.0.0
     * @param Customer_Sync $customer_sync Customer sync instance.
     * @return static
     */
    public function set_customer_sync(Customer_Sync $customer_sync): static
    {
        $this->customer_sync = $customer_sync;
        return $this;
    }

    /**
     * Sync a WooCommerce order to SAP.
     *
     * @since 1.0.0
     * @param int $order_id WooCommerce order ID.
     * @return bool True on success.
     * @throws SAP_Exception On SAP error.
     * @throws Validation_Exception On validation error.
     */
    public function sync_order(int $order_id): bool
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            $this->logger->error('Order not found', [
                'order_id' => $order_id,
            ]);
            return false;
        }

        // Check if already synced.
        $sap_doc_entry = $order->get_meta('_sap_doc_entry');

        if ($sap_doc_entry) {
            $this->logger->info('Order already synced to SAP', [
                'order_id' => $order_id,
                'sap_doc_entry' => $sap_doc_entry,
            ]);
            return true;
        }

        // Validate order.
        $validation_errors = Validator::validate_order($order);

        if (!empty($validation_errors)) {
            throw Validation_Exception::order_invalid($order_id, $validation_errors);
        }

        $this->logger->info('Starting order sync', [
            'order_id' => $order_id,
            'total' => $order->get_total(),
        ]);

        try {
            // Ensure customer exists in SAP.
            $card_code = $this->ensure_customer($order);

            // Map order to SAP format.
            $sap_order = $this->get_mapper()->map($order, $card_code);

            // Create order in SAP.
            $response = $this->client->create_order($sap_order);

            // Parse response.
            $parsed = Response_Parser::parse_order($response);

            // Save SAP reference to order.
            $order->update_meta_data('_sap_doc_entry', $parsed['doc_entry']);
            $order->update_meta_data('_sap_doc_num', $parsed['doc_num']);
            $order->update_meta_data('_sap_synced_at', current_time('mysql'));
            $order->save();

            // Add order note.
            $order->add_order_note(
                sprintf(
                    /* translators: 1: SAP DocNum, 2: SAP DocEntry */
                    __('Order synced to SAP. DocNum: %1$s, DocEntry: %2$s', 'sap-woocommerce-sync'),
                    $parsed['doc_num'],
                    $parsed['doc_entry']
                )
            );

            // Update mapping table.
            $this->update_order_mapping($order_id, $parsed);

            $this->logger->info('Order synced successfully', [
                'order_id' => $order_id,
                'sap_doc_entry' => $parsed['doc_entry'],
                'sap_doc_num' => $parsed['doc_num'],
            ]);

            return true;
        } catch (SAP_Exception $e) {
            $this->logger->error('SAP order sync failed', [
                'order_id' => $order_id,
                'sap_error' => $e->get_sap_error_code(),
                'message' => $e->getMessage(),
            ]);

            $order->add_order_note(
                sprintf(
                    /* translators: %s: Error message */
                    __('SAP sync failed: %s', 'sap-woocommerce-sync'),
                    $e->getMessage()
                )
            );

            throw $e;
        }
    }

    /**
     * Ensure customer exists in SAP.
     *
     * @since 1.0.0
     * @param \WC_Order $order WooCommerce order.
     * @return string SAP CardCode.
     */
    private function ensure_customer(\WC_Order $order): string
    {
        // Check if customer has SAP card code.
        $customer_id = $order->get_customer_id();

        if ($customer_id) {
            $card_code = get_user_meta($customer_id, '_sap_card_code', true);

            if ($card_code) {
                return $card_code;
            }
        }

        // Use customer sync to find or create.
        if ($this->customer_sync) {
            return $this->customer_sync->ensure_customer($order);
        }

        // Fallback: use default walk-in customer.
        $settings = get_option('sap_wc_sync_settings', []);
        $default_code = $settings['default_customer_code'] ?? 'WALKIN';

        return $default_code;
    }

    /**
     * Get order mapper.
     *
     * @since 1.0.0
     * @return Order_Mapper
     */
    private function get_mapper(): Order_Mapper
    {
        if (null === $this->mapper) {
            $this->mapper = new Order_Mapper();
        }
        return $this->mapper;
    }

    /**
     * Update order mapping in database.
     *
     * @since 1.0.0
     * @param int                  $order_id WooCommerce order ID.
     * @param array<string, mixed> $sap_data SAP response data.
     * @return void
     */
    private function update_order_mapping(int $order_id, array $sap_data): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sap_wc_order_map';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->replace(
            $table,
            [
                'wc_order_id' => $order_id,
                'sap_doc_entry' => $sap_data['doc_entry'],
                'sap_doc_num' => $sap_data['doc_num'],
                'sap_doc_type' => 'Orders',
                'sync_status' => 'synced',
                'synced_at' => current_time('mysql', true),
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s']
        );
    }
}
