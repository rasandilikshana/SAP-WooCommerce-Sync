<?php
/**
 * Stock Sync Handler class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/Sync
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Jehankandy\SAP_WooCommerce_Sync\Sync;

use Jehankandy\SAP_WooCommerce_Sync\SAP\Client;
use Jehankandy\SAP_WooCommerce_Sync\SAP\Request_Builder;
use Jehankandy\SAP_WooCommerce_Sync\SAP\Response_Parser;
use Jehankandy\SAP_WooCommerce_Sync\Utilities\Logger;

/**
 * Handles stock synchronization from SAP to WooCommerce.
 *
 * @since 1.0.0
 */
class Stock_Sync
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
     * Batch size for processing.
     *
     * @since 1.0.0
     * @var int
     */
    private int $batch_size = 50;

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
     * Sync stock for all mapped products.
     *
     * @since 1.0.0
     * @return array{synced: int, failed: int, skipped: int}
     */
    public function sync_all_stock(): array
    {
        $this->logger->info('Starting full stock sync');

        $stats = [
            'synced' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        // Get all mapped products.
        $products = $this->get_mapped_products();

        if (empty($products)) {
            $this->logger->info('No mapped products found for stock sync');
            return $stats;
        }

        // Process in batches.
        $batches = array_chunk($products, $this->batch_size);

        foreach ($batches as $batch) {
            $batch_stats = $this->sync_batch($batch);
            $stats['synced'] += $batch_stats['synced'];
            $stats['failed'] += $batch_stats['failed'];
            $stats['skipped'] += $batch_stats['skipped'];
        }

        $this->logger->info('Full stock sync completed', $stats);

        return $stats;
    }

    /**
     * Sync stock for a single product.
     *
     * @since 1.0.0
     * @param int $product_id WooCommerce product ID.
     * @return bool True on success.
     */
    public function sync_product_stock(int $product_id): bool
    {
        $product = wc_get_product($product_id);

        if (!$product) {
            $this->logger->error('Product not found', [
                'product_id' => $product_id,
            ]);
            return false;
        }

        $sku = $product->get_sku();

        if (empty($sku)) {
            $this->logger->warning('Product has no SKU', [
                'product_id' => $product_id,
            ]);
            return false;
        }

        try {
            $sap_item = $this->client->get_item($sku);
            $stock = Response_Parser::parse_item_stock($sap_item);

            $this->update_product_stock($product, $stock['total']);

            $this->logger->info('Stock synced for product', [
                'product_id' => $product_id,
                'sku' => $sku,
                'stock' => $stock['total'],
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to sync stock for product', [
                'product_id' => $product_id,
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync a batch of products.
     *
     * @since 1.0.0
     * @param array<int, object> $products Batch of product mappings.
     * @return array{synced: int, failed: int, skipped: int}
     */
    private function sync_batch(array $products): array
    {
        $stats = [
            'synced' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        // Build list of item codes to fetch.
        $item_codes = array_map(
            fn($p) => $p->sap_item_code,
            $products
        );

        try {
            // Fetch all items in one request.
            $query = Request_Builder::create()
                ->select(['ItemCode', 'QuantityOnStock', 'ItemWarehouseInfoCollection'])
                ->where_in('ItemCode', $item_codes);

            $response = $this->client->get_items($query);
            $items = Response_Parser::parse_collection($response)['items'];

            // Index by ItemCode.
            $items_by_code = [];
            foreach ($items as $item) {
                $items_by_code[$item['ItemCode']] = $item;
            }

            // Update each product.
            foreach ($products as $mapping) {
                $item = $items_by_code[$mapping->sap_item_code] ?? null;

                if (!$item) {
                    $this->logger->warning('SAP item not found', [
                        'item_code' => $mapping->sap_item_code,
                    ]);
                    $stats['skipped']++;
                    continue;
                }

                $product = wc_get_product($mapping->wc_product_id);

                if (!$product) {
                    $stats['skipped']++;
                    continue;
                }

                $stock = Response_Parser::parse_item_stock($item);
                $this->update_product_stock($product, $stock['total']);
                $stats['synced']++;
            }
        } catch (\Exception $e) {
            $this->logger->error('Batch stock sync failed', [
                'error' => $e->getMessage(),
                'batch_count' => count($products),
            ]);
            $stats['failed'] = count($products);
        }

        return $stats;
    }

    /**
     * Update WooCommerce product stock.
     *
     * @since 1.0.0
     * @param \WC_Product $product   Product instance.
     * @param float       $quantity  Stock quantity.
     * @return void
     */
    private function update_product_stock(\WC_Product $product, float $quantity): void
    {
        // Only update if stock management is enabled.
        if (!$product->get_manage_stock()) {
            // Enable stock management if not already.
            $product->set_manage_stock(true);
        }

        $current_stock = $product->get_stock_quantity();

        // Only update if different.
        if (abs((float) $current_stock - $quantity) < 0.001) {
            return;
        }

        $product->set_stock_quantity($quantity);
        $product->set_stock_status($quantity > 0 ? 'instock' : 'outofstock');
        $product->save();

        $this->logger->debug('Product stock updated', [
            'product_id' => $product->get_id(),
            'old_stock' => $current_stock,
            'new_stock' => $quantity,
        ]);
    }

    /**
     * Get all mapped products from database.
     *
     * @since 1.0.0
     * @return array<int, object>
     */
    private function get_mapped_products(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sap_wc_product_map';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            "SELECT wc_product_id, sap_item_code FROM {$table} WHERE sync_enabled = 1"
        );

        return $results ?: [];
    }
}
