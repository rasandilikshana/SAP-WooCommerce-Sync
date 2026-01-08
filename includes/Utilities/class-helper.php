<?php
/**
 * Helper utility class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/Utilities
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Rasandilikshana\SAP_WooCommerce_Sync\Utilities;

/**
 * General helper functions for the plugin.
 *
 * @since 1.0.0
 */
class Helper
{

    /**
     * Get the plugin instance.
     *
     * @since 1.0.0
     * @return \Rasandilikshana\SAP_WooCommerce_Sync\Plugin|null
     */
    public static function get_plugin(): ?\Rasandilikshana\SAP_WooCommerce_Sync\Plugin
    {
        return \Rasandilikshana\SAP_WooCommerce_Sync\Plugin::get_instance();
    }

    /**
     * Format a date for SAP API.
     *
     * SAP expects dates in format: YYYY-MM-DD
     *
     * @since 1.0.0
     * @param int|string|\DateTimeInterface $date The date to format.
     * @return string Formatted date string.
     */
    public static function format_date_for_sap(mixed $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        if (is_numeric($date)) {
            return gmdate('Y-m-d', (int) $date);
        }

        $timestamp = strtotime((string) $date);
        return $timestamp ? gmdate('Y-m-d', $timestamp) : gmdate('Y-m-d');
    }

    /**
     * Parse a date from SAP API.
     *
     * @since 1.0.0
     * @param string $date The date string from SAP.
     * @return \DateTime|null Parsed DateTime or null on failure.
     */
    public static function parse_date_from_sap(string $date): ?\DateTime
    {
        // SAP sometimes returns dates in /Date(timestamp)/ format.
        if (preg_match('/\/Date\((\d+)\)\//', $date, $matches)) {
            return (new \DateTime())->setTimestamp((int) ($matches[1] / 1000));
        }

        try {
            return new \DateTime($date);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Format a price for SAP.
     *
     * @since 1.0.0
     * @param float|string $price The price to format.
     * @return float Formatted price.
     */
    public static function format_price_for_sap(mixed $price): float
    {
        return round((float) $price, 4);
    }

    /**
     * Sanitize an item code for SAP.
     *
     * @since 1.0.0
     * @param string $item_code The item code.
     * @return string Sanitized item code.
     */
    public static function sanitize_item_code(string $item_code): string
    {
        // SAP item codes are typically alphanumeric with some special chars.
        return preg_replace('/[^a-zA-Z0-9\-_.]/', '', $item_code);
    }

    /**
     * Generate a unique customer card code.
     *
     * @since 1.0.0
     * @param int    $customer_id WooCommerce customer ID.
     * @param string $prefix      Optional prefix.
     * @return string Generated card code.
     */
    public static function generate_card_code(int $customer_id, string $prefix = 'WC'): string
    {
        return sprintf('%s%06d', $prefix, $customer_id);
    }

    /**
     * Get WooCommerce order statuses that should trigger sync.
     *
     * @since 1.0.0
     * @return array<string> Array of status slugs.
     */
    public static function get_syncable_order_statuses(): array
    {
        $statuses = apply_filters(
            'sap_wc_sync_order_statuses',
            ['processing', 'completed']
        );

        return array_map('sanitize_key', $statuses);
    }

    /**
     * Check if an order status is syncable.
     *
     * @since 1.0.0
     * @param string $status The order status to check.
     * @return bool True if syncable, false otherwise.
     */
    public static function is_syncable_status(string $status): bool
    {
        // Remove 'wc-' prefix if present.
        $status = str_replace('wc-', '', $status);
        return in_array($status, self::get_syncable_order_statuses(), true);
    }

    /**
     * Format bytes to human readable size.
     *
     * @since 1.0.0
     * @param int $bytes    The size in bytes.
     * @param int $decimals Number of decimal places.
     * @return string Formatted size string.
     */
    public static function format_bytes(int $bytes, int $decimals = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        return round($bytes / (1024 ** $pow), $decimals) . ' ' . $units[$pow];
    }

    /**
     * Get the current timestamp in MySQL format.
     *
     * @since 1.0.0
     * @return string Current timestamp.
     */
    public static function current_timestamp(): string
    {
        return current_time('mysql', true);
    }

    /**
     * Check if WooCommerce is active.
     *
     * @since 1.0.0
     * @return bool True if WooCommerce is active.
     */
    public static function is_woocommerce_active(): bool
    {
        return class_exists('WooCommerce');
    }

    /**
     * Check if Action Scheduler is available.
     *
     * @since 1.0.0
     * @return bool True if Action Scheduler is available.
     */
    public static function is_action_scheduler_available(): bool
    {
        return function_exists('as_enqueue_async_action');
    }

    /**
     * Mask sensitive data for logging.
     *
     * @since 1.0.0
     * @param string $value  The value to mask.
     * @param int    $reveal Number of characters to reveal at end.
     * @return string Masked value.
     */
    public static function mask_sensitive(string $value, int $reveal = 4): string
    {
        $length = strlen($value);

        if ($length <= $reveal) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - $reveal) . substr($value, -$reveal);
    }
}
