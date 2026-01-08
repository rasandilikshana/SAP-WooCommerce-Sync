<?php
/**
 * WooCommerce Product Hooks class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/WooCommerce
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Jehankandy\SAP_WooCommerce_Sync\WooCommerce;

use Jehankandy\SAP_WooCommerce_Sync\Utilities\Logger;

/**
 * Handles WooCommerce product events.
 *
 * @since 1.0.0
 */
class Product_Hooks
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
     * Handle product save event.
     *
     * @since 1.0.0
     * @param int $product_id Product ID.
     * @return void
     */
    public function on_product_saved(int $product_id): void
    {
        // Get the product.
        $product = wc_get_product($product_id);

        if (!$product) {
            return;
        }

        // Skip if no SKU.
        if (empty($product->get_sku())) {
            return;
        }

        $this->logger->debug('Product saved', [
            'product_id' => $product_id,
            'sku' => $product->get_sku(),
        ]);

        // Trigger sync action.
        do_action('sap_wc_sync_product_saved', $product_id, $product);
    }

    /**
     * Handle product deletion.
     *
     * @since 1.0.0
     * @param int $product_id Product ID.
     * @return void
     */
    public function on_product_deleted(int $product_id): void
    {
        $this->logger->info('Product deleted', [
            'product_id' => $product_id,
        ]);

        // Clean up mapping.
        do_action('sap_wc_sync_product_deleted', $product_id);
    }
}
