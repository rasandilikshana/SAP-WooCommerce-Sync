<?php
/**
 * Validator utility class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/Utilities
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Jehankandy\SAP_WooCommerce_Sync\Utilities;

/**
 * Handles validation for plugin data.
 *
 * @since 1.0.0
 */
class Validator
{

    /**
     * Validate SAP Service Layer URL.
     *
     * @since 1.0.0
     * @param string $url The URL to validate.
     * @return bool|string True if valid, error message otherwise.
     */
    public static function validate_sap_url(string $url): bool|string
    {
        if (empty($url)) {
            return __('SAP Service Layer URL is required.', 'sap-woocommerce-sync');
        }

        $url = filter_var($url, FILTER_VALIDATE_URL);

        if (false === $url) {
            return __('Invalid SAP Service Layer URL format.', 'sap-woocommerce-sync');
        }

        // Must be HTTPS in production.
        if (!str_starts_with($url, 'https://') && !defined('SAP_WC_SYNC_ALLOW_HTTP')) {
            return __('SAP Service Layer URL must use HTTPS.', 'sap-woocommerce-sync');
        }

        return true;
    }

    /**
     * Validate company database name.
     *
     * @since 1.0.0
     * @param string $company_db The company database name.
     * @return bool|string True if valid, error message otherwise.
     */
    public static function validate_company_db(string $company_db): bool|string
    {
        if (empty($company_db)) {
            return __('Company database name is required.', 'sap-woocommerce-sync');
        }

        if (strlen($company_db) > 100) {
            return __('Company database name is too long.', 'sap-woocommerce-sync');
        }

        return true;
    }

    /**
     * Validate SAP username.
     *
     * @since 1.0.0
     * @param string $username The username.
     * @return bool|string True if valid, error message otherwise.
     */
    public static function validate_username(string $username): bool|string
    {
        if (empty($username)) {
            return __('SAP username is required.', 'sap-woocommerce-sync');
        }

        if (strlen($username) > 50) {
            return __('SAP username is too long.', 'sap-woocommerce-sync');
        }

        return true;
    }

    /**
     * Validate sync interval.
     *
     * @since 1.0.0
     * @param int $minutes The interval in minutes.
     * @return bool|string True if valid, error message otherwise.
     */
    public static function validate_sync_interval(int $minutes): bool|string
    {
        if ($minutes < 1) {
            return __('Sync interval must be at least 1 minute.', 'sap-woocommerce-sync');
        }

        if ($minutes > 1440) {
            return __('Sync interval cannot exceed 24 hours (1440 minutes).', 'sap-woocommerce-sync');
        }

        return true;
    }

    /**
     * Validate order data before syncing.
     *
     * @since 1.0.0
     * @param \WC_Order $order The WooCommerce order.
     * @return array<string> Array of validation errors (empty if valid).
     */
    public static function validate_order(\WC_Order $order): array
    {
        $errors = [];

        // Check for items.
        if (count($order->get_items()) === 0) {
            $errors[] = __('Order has no items.', 'sap-woocommerce-sync');
        }

        // Check for billing info.
        if (empty($order->get_billing_email()) && empty($order->get_billing_phone())) {
            $errors[] = __('Order has no contact information.', 'sap-woocommerce-sync');
        }

        // Validate each line item has a SKU.
        foreach ($order->get_items() as $item) {
            /** @var \WC_Order_Item_Product $item */
            $product = $item->get_product();

            if (!$product) {
                $errors[] = sprintf(
                    /* translators: %s: Item name */
                    __('Product not found for item: %s', 'sap-woocommerce-sync'),
                    $item->get_name()
                );
                continue;
            }

            $sku = $product->get_sku();

            if (empty($sku)) {
                $errors[] = sprintf(
                    /* translators: %s: Product name */
                    __('Product "%s" has no SKU.', 'sap-woocommerce-sync'),
                    $product->get_name()
                );
            }
        }

        return $errors;
    }

    /**
     * Validate product for SAP sync.
     *
     * @since 1.0.0
     * @param \WC_Product $product The WooCommerce product.
     * @return array<string> Array of validation errors (empty if valid).
     */
    public static function validate_product(\WC_Product $product): array
    {
        $errors = [];

        if (empty($product->get_sku())) {
            $errors[] = __('Product has no SKU.', 'sap-woocommerce-sync');
        }

        if (!$product->is_in_stock() && $product->get_stock_quantity() === null) {
            // This is okay - might be a non-stock managed product.
        }

        return $errors;
    }

    /**
     * Validate SAP API response.
     *
     * @since 1.0.0
     * @param array<string, mixed> $response The API response.
     * @return bool|string True if valid, error message otherwise.
     */
    public static function validate_sap_response(array $response): bool|string
    {
        // Check for OData error format.
        if (isset($response['error'])) {
            $error_message = $response['error']['message']['value'] ?? $response['error']['message'] ?? 'Unknown error';
            $error_code = $response['error']['code'] ?? '';

            return sprintf('[%s] %s', $error_code, $error_message);
        }

        return true;
    }

    /**
     * Sanitize and validate settings array.
     *
     * @since 1.0.0
     * @param array<string, mixed> $settings The settings to validate.
     * @return array<string, mixed> Sanitized settings.
     */
    public static function sanitize_settings(array $settings): array
    {
        $sanitized = [];

        $sanitized['sap_service_url'] = isset($settings['sap_service_url'])
            ? esc_url_raw(rtrim($settings['sap_service_url'], '/'))
            : '';

        $sanitized['sap_company_db'] = isset($settings['sap_company_db'])
            ? sanitize_text_field($settings['sap_company_db'])
            : '';

        $sanitized['sap_username'] = isset($settings['sap_username'])
            ? sanitize_text_field($settings['sap_username'])
            : '';

        $sanitized['stock_sync_interval'] = isset($settings['stock_sync_interval'])
            ? min(max(absint($settings['stock_sync_interval']), 1), 1440)
            : 5;

        $sanitized['auto_sync_orders'] = !empty($settings['auto_sync_orders']);
        $sanitized['auto_create_customers'] = !empty($settings['auto_create_customers']);

        $sanitized['default_warehouse'] = isset($settings['default_warehouse'])
            ? sanitize_text_field($settings['default_warehouse'])
            : '';

        $sanitized['default_tax_code'] = isset($settings['default_tax_code'])
            ? sanitize_text_field($settings['default_tax_code'])
            : '';

        $valid_log_levels = ['debug', 'info', 'warning', 'error', 'critical'];
        $sanitized['log_level'] = isset($settings['log_level']) && in_array($settings['log_level'], $valid_log_levels, true)
            ? $settings['log_level']
            : 'info';

        $sanitized['log_retention_days'] = isset($settings['log_retention_days'])
            ? min(max(absint($settings['log_retention_days']), 1), 365)
            : 30;

        return $sanitized;
    }
}
