<?php
/**
 * Order Mapper class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/Mappers
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Jehankandy\SAP_WooCommerce_Sync\Mappers;

use Jehankandy\SAP_WooCommerce_Sync\Utilities\Helper;

/**
 * Maps WooCommerce orders to SAP Sales Order format.
 *
 * @since 1.0.0
 */
class Order_Mapper
{

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
     * @param array<string, mixed> $settings Plugin settings.
     */
    public function __construct(array $settings = [])
    {
        $this->settings = $settings ?: get_option('sap_wc_sync_settings', []);
    }

    /**
     * Map WooCommerce order to SAP Sales Order format.
     *
     * @since 1.0.0
     * @param \WC_Order $order     WooCommerce order.
     * @param string    $card_code SAP Business Partner CardCode.
     * @return array<string, mixed> SAP order data.
     */
    public function map(\WC_Order $order, string $card_code): array
    {
        $sap_order = [
            'CardCode' => $card_code,
            'DocDate' => Helper::format_date_for_sap($order->get_date_created()),
            'DocDueDate' => Helper::format_date_for_sap(time() + (7 * DAY_IN_SECONDS)),
            'NumAtCard' => (string) $order->get_order_number(),
            'Comments' => $this->build_comments($order),
            'DocumentLines' => $this->map_line_items($order),
        ];

        // Add shipping address.
        $ship_to = $this->map_address($order, 'shipping');

        if ($ship_to) {
            $sap_order['ShipToCode'] = $ship_to;
        }

        // Add payment method reference.
        $sap_order['PaymentMethod'] = $this->map_payment_method($order);

        // Allow filtering.
        return apply_filters('sap_wc_sync_mapped_order', $sap_order, $order, $card_code);
    }

    /**
     * Map order line items to SAP DocumentLines format.
     *
     * @since 1.0.0
     * @param \WC_Order $order WooCommerce order.
     * @return array<int, array<string, mixed>>
     */
    private function map_line_items(\WC_Order $order): array
    {
        $lines = [];

        foreach ($order->get_items() as $item) {
            /** @var \WC_Order_Item_Product $item */
            $product = $item->get_product();

            if (!$product) {
                continue;
            }

            $sku = $product->get_sku();

            if (empty($sku)) {
                continue;
            }

            $line = [
                'ItemCode' => Helper::sanitize_item_code($sku),
                'Quantity' => $item->get_quantity(),
                'UnitPrice' => Helper::format_price_for_sap($order->get_item_subtotal($item, false, false)),
                'DiscountPercent' => $this->calculate_discount_percent($item, $order),
            ];

            // Add warehouse if configured.
            if (!empty($this->settings['default_warehouse'])) {
                $line['WarehouseCode'] = $this->settings['default_warehouse'];
            }

            // Add tax code if configured.
            if (!empty($this->settings['default_tax_code'])) {
                $line['TaxCode'] = $this->settings['default_tax_code'];
            }

            // Add line item meta as free text.
            $meta = $this->get_item_meta($item);

            if ($meta) {
                $line['FreeText'] = $meta;
            }

            $lines[] = apply_filters('sap_wc_sync_mapped_line_item', $line, $item, $product, $order);
        }

        // Add shipping as a service line if needed.
        $shipping_line = $this->map_shipping($order);

        if ($shipping_line) {
            $lines[] = $shipping_line;
        }

        return $lines;
    }

    /**
     * Calculate discount percentage for line item.
     *
     * @since 1.0.0
     * @param \WC_Order_Item_Product $item  Order item.
     * @param \WC_Order              $order Order object.
     * @return float Discount percentage.
     */
    private function calculate_discount_percent(\WC_Order_Item_Product $item, \WC_Order $order): float
    {
        $subtotal = (float) $item->get_subtotal();
        $total = (float) $item->get_total();

        if ($subtotal <= 0) {
            return 0.0;
        }

        $discount = $subtotal - $total;

        if ($discount <= 0) {
            return 0.0;
        }

        return round(($discount / $subtotal) * 100, 2);
    }

    /**
     * Map shipping to SAP line item.
     *
     * @since 1.0.0
     * @param \WC_Order $order WooCommerce order.
     * @return array<string, mixed>|null Shipping line or null.
     */
    private function map_shipping(\WC_Order $order): ?array
    {
        $shipping_total = (float) $order->get_shipping_total();

        if ($shipping_total <= 0) {
            return null;
        }

        $shipping_item_code = $this->settings['shipping_item_code'] ?? 'SHIPPING';

        return [
            'ItemCode' => $shipping_item_code,
            'Quantity' => 1,
            'UnitPrice' => Helper::format_price_for_sap($shipping_total),
            'FreeText' => $order->get_shipping_method(),
        ];
    }

    /**
     * Build order comments.
     *
     * @since 1.0.0
     * @param \WC_Order $order WooCommerce order.
     * @return string Comments string.
     */
    private function build_comments(\WC_Order $order): string
    {
        $comments = [];

        $comments[] = sprintf('WooCommerce Order #%s', $order->get_order_number());

        $customer_note = $order->get_customer_note();

        if ($customer_note) {
            $comments[] = 'Customer Note: ' . $customer_note;
        }

        $payment_method = $order->get_payment_method_title();

        if ($payment_method) {
            $comments[] = 'Payment: ' . $payment_method;
        }

        return implode("\n", $comments);
    }

    /**
     * Map address for SAP.
     *
     * @since 1.0.0
     * @param \WC_Order $order WooCommerce order.
     * @param string    $type  Address type (billing or shipping).
     * @return string|null Address code or null.
     */
    private function map_address(\WC_Order $order, string $type): ?string
    {
        // For now, return null - addresses are stored on Business Partner.
        // This could be extended to create address entries.
        return null;
    }

    /**
     * Map payment method.
     *
     * @since 1.0.0
     * @param \WC_Order $order WooCommerce order.
     * @return string|null SAP payment method code or null.
     */
    private function map_payment_method(\WC_Order $order): ?string
    {
        $wc_method = $order->get_payment_method();

        $mapping = apply_filters('sap_wc_sync_payment_method_mapping', [
            'bacs' => 'BT', // Bank Transfer.
            'cheque' => 'CH', // Cheque.
            'cod' => 'CA', // Cash.
            'paypal' => 'PP', // PayPal.
        ]);

        return $mapping[$wc_method] ?? null;
    }

    /**
     * Get formatted item meta.
     *
     * @since 1.0.0
     * @param \WC_Order_Item_Product $item Order item.
     * @return string|null Meta string or null.
     */
    private function get_item_meta(\WC_Order_Item_Product $item): ?string
    {
        $meta = $item->get_formatted_meta_data('_', true);

        if (empty($meta)) {
            return null;
        }

        $parts = [];

        foreach ($meta as $meta_item) {
            $parts[] = $meta_item->display_key . ': ' . wp_strip_all_tags($meta_item->display_value);
        }

        return implode(', ', $parts);
    }
}
