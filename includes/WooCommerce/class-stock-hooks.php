<?php
/**
 * WooCommerce Stock Hooks class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/WooCommerce
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Jehankandy\SAP_WooCommerce_Sync\WooCommerce;

use Jehankandy\SAP_WooCommerce_Sync\Utilities\Logger;

/**
 * Handles WooCommerce stock events.
 *
 * @since 1.0.0
 */
class Stock_Hooks
{

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
     * @param Logger               $logger   Logger instance.
     * @param array<string, mixed> $settings Plugin settings.
     */
    public function __construct(Logger $logger, array $settings)
    {
        $this->logger = $logger;
        $this->settings = $settings;
    }

    /**
     * Handle stock reduction event.
     *
     * @since 1.0.0
     * @param int $order_id Order ID.
     * @return void
     */
    public function on_stock_reduced(int $order_id): void
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $this->logger->debug('Stock reduced for order', [
            'order_id' => $order_id,
        ]);

        // Trigger stock verification if needed.
        do_action('sap_wc_sync_stock_reduced', $order_id, $order);
    }

    /**
     * Handle low stock notification.
     *
     * @since 1.0.0
     * @param \WC_Product $product Product object.
     * @return void
     */
    public function on_low_stock(\WC_Product $product): void
    {
        $this->logger->warning('Low stock alert', [
            'product_id' => $product->get_id(),
            'sku' => $product->get_sku(),
            'stock' => $product->get_stock_quantity(),
        ]);
    }

    /**
     * Handle no stock notification.
     *
     * @since 1.0.0
     * @param \WC_Product $product Product object.
     * @return void
     */
    public function on_no_stock(\WC_Product $product): void
    {
        $this->logger->warning('Out of stock alert', [
            'product_id' => $product->get_id(),
            'sku' => $product->get_sku(),
        ]);
    }
}
