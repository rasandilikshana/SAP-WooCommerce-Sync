<?php
/**
 * SAP Response Parser class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/SAP
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Rasandilikshana\SAP_WooCommerce_Sync\SAP;

/**
 * Parses and normalizes SAP Service Layer API responses.
 *
 * @since 1.0.0
 */
class Response_Parser
{

    /**
     * Parse a collection response.
     *
     * @since 1.0.0
     * @param array<string, mixed> $response The API response.
     * @return array{items: array, count: int|null, next_link: string|null}
     */
    public static function parse_collection(array $response): array
    {
        $items = $response['value'] ?? [];

        // Handle OData v4 count.
        $count = $response['@odata.count'] ?? $response['odata.count'] ?? null;

        // Handle next link for pagination.
        $next_link = $response['@odata.nextLink'] ?? $response['odata.nextLink'] ?? null;

        return [
            'items' => $items,
            'count' => $count ? (int) $count : null,
            'next_link' => $next_link,
        ];
    }

    /**
     * Parse a single entity response.
     *
     * @since 1.0.0
     * @param array<string, mixed> $response The API response.
     * @return array<string, mixed>
     */
    public static function parse_entity(array $response): array
    {
        // Remove OData metadata.
        unset(
            $response['@odata.context'],
            $response['@odata.etag'],
            $response['odata.metadata']
        );

        return $response;
    }

    /**
     * Extract error message from response.
     *
     * @since 1.0.0
     * @param array<string, mixed> $response The API response.
     * @return array{code: string, message: string}
     */
    public static function parse_error(array $response): array
    {
        $error = $response['error'] ?? [];

        // OData v3 format.
        if (isset($error['message']['value'])) {
            return [
                'code' => $error['code'] ?? 'UNKNOWN',
                'message' => $error['message']['value'],
            ];
        }

        // OData v4 format.
        if (isset($error['message']) && is_string($error['message'])) {
            return [
                'code' => $error['code'] ?? 'UNKNOWN',
                'message' => $error['message'],
            ];
        }

        return [
            'code' => 'UNKNOWN',
            'message' => __('Unknown error occurred.', 'sap-woocommerce-sync'),
        ];
    }

    /**
     * Check if response contains an error.
     *
     * @since 1.0.0
     * @param array<string, mixed> $response The API response.
     * @return bool True if error present.
     */
    public static function has_error(array $response): bool
    {
        return isset($response['error']);
    }

    /**
     * Extract item stock quantities from response.
     *
     * @since 1.0.0
     * @param array<string, mixed> $item_data SAP item data.
     * @return array{total: float, by_warehouse: array}
     */
    public static function parse_item_stock(array $item_data): array
    {
        $total = (float) ($item_data['QuantityOnStock'] ?? 0);
        $by_warehouse = [];

        // Parse warehouse-specific stock if available.
        if (isset($item_data['ItemWarehouseInfoCollection'])) {
            foreach ($item_data['ItemWarehouseInfoCollection'] as $warehouse) {
                $warehouse_code = $warehouse['WarehouseCode'] ?? '';
                $in_stock = (float) ($warehouse['InStock'] ?? 0);
                $committed = (float) ($warehouse['Committed'] ?? 0);
                $available = $in_stock - $committed;

                if ($warehouse_code) {
                    $by_warehouse[$warehouse_code] = [
                        'in_stock' => $in_stock,
                        'committed' => $committed,
                        'available' => $available,
                    ];
                }
            }
        }

        return [
            'total' => $total,
            'by_warehouse' => $by_warehouse,
        ];
    }

    /**
     * Parse order response to extract key fields.
     *
     * @since 1.0.0
     * @param array<string, mixed> $order_data SAP order data.
     * @return array<string, mixed> Parsed order data.
     */
    public static function parse_order(array $order_data): array
    {
        return [
            'doc_entry' => $order_data['DocEntry'] ?? null,
            'doc_num' => $order_data['DocNum'] ?? null,
            'doc_status' => $order_data['DocumentStatus'] ?? null,
            'doc_date' => $order_data['DocDate'] ?? null,
            'doc_due_date' => $order_data['DocDueDate'] ?? null,
            'card_code' => $order_data['CardCode'] ?? null,
            'card_name' => $order_data['CardName'] ?? null,
            'doc_total' => (float) ($order_data['DocTotal'] ?? 0),
            'doc_currency' => $order_data['DocCurrency'] ?? null,
            'lines' => $order_data['DocumentLines'] ?? [],
            'comments' => $order_data['Comments'] ?? null,
        ];
    }

    /**
     * Parse business partner response.
     *
     * @since 1.0.0
     * @param array<string, mixed> $bp_data SAP business partner data.
     * @return array<string, mixed> Parsed business partner data.
     */
    public static function parse_business_partner(array $bp_data): array
    {
        return [
            'card_code' => $bp_data['CardCode'] ?? null,
            'card_name' => $bp_data['CardName'] ?? null,
            'card_type' => $bp_data['CardType'] ?? null,
            'email' => $bp_data['EmailAddress'] ?? null,
            'phone' => $bp_data['Phone1'] ?? null,
            'address' => $bp_data['Address'] ?? null,
            'city' => $bp_data['City'] ?? null,
            'country' => $bp_data['Country'] ?? null,
            'zip_code' => $bp_data['ZipCode'] ?? null,
            'valid' => 'tYES' === ($bp_data['Valid'] ?? 'tYES'),
        ];
    }

    /**
     * Normalize boolean values from SAP.
     *
     * SAP uses 'tYES'/'tNO' or 'Y'/'N' for booleans.
     *
     * @since 1.0.0
     * @param mixed $value The value to normalize.
     * @return bool Normalized boolean.
     */
    public static function normalize_boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtoupper($value), ['TYES', 'Y', 'YES', '1', 'TRUE'], true);
        }

        return (bool) $value;
    }

    /**
     * Extract document lines from order/invoice.
     *
     * @since 1.0.0
     * @param array<string, mixed> $document SAP document data.
     * @return array<int, array<string, mixed>> Parsed document lines.
     */
    public static function parse_document_lines(array $document): array
    {
        $lines = $document['DocumentLines'] ?? [];
        $parsed = [];

        foreach ($lines as $line) {
            $parsed[] = [
                'line_num' => $line['LineNum'] ?? null,
                'item_code' => $line['ItemCode'] ?? null,
                'item_name' => $line['ItemDescription'] ?? null,
                'quantity' => (float) ($line['Quantity'] ?? 0),
                'unit_price' => (float) ($line['UnitPrice'] ?? 0),
                'line_total' => (float) ($line['LineTotal'] ?? 0),
                'warehouse' => $line['WarehouseCode'] ?? null,
                'tax_code' => $line['TaxCode'] ?? null,
            ];
        }

        return $parsed;
    }
}
