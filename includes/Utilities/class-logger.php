<?php
/**
 * Logger utility class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/Utilities
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Jehankandy\SAP_WooCommerce_Sync\Utilities;

/**
 * Handles logging for the plugin.
 *
 * Logs are stored in the database and optionally to the WooCommerce logs.
 * Supports multiple log levels: DEBUG, INFO, WARNING, ERROR, CRITICAL.
 *
 * @since 1.0.0
 */
class Logger
{

    /**
     * Log level constants.
     */
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';
    public const CRITICAL = 'critical';

    /**
     * Log level priorities (lower = more verbose).
     *
     * @since 1.0.0
     * @var array<string, int>
     */
    private const LEVEL_PRIORITY = [
        self::DEBUG => 0,
        self::INFO => 1,
        self::WARNING => 2,
        self::ERROR => 3,
        self::CRITICAL => 4,
    ];

    /**
     * Current log level threshold.
     *
     * @since 1.0.0
     * @var string
     */
    private string $level;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param string $level Minimum log level to record.
     */
    public function __construct(string $level = self::INFO)
    {
        $this->level = $level;
    }

    /**
     * Log a debug message.
     *
     * @since 1.0.0
     * @param string               $message The log message.
     * @param array<string, mixed> $context Additional context data.
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Log an info message.
     *
     * @since 1.0.0
     * @param string               $message The log message.
     * @param array<string, mixed> $context Additional context data.
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * Log a warning message.
     *
     * @since 1.0.0
     * @param string               $message The log message.
     * @param array<string, mixed> $context Additional context data.
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * Log an error message.
     *
     * @since 1.0.0
     * @param string               $message The log message.
     * @param array<string, mixed> $context Additional context data.
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * Log a critical message.
     *
     * @since 1.0.0
     * @param string               $message The log message.
     * @param array<string, mixed> $context Additional context data.
     * @return void
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * Log a message at the specified level.
     *
     * @since 1.0.0
     * @param string               $level   The log level.
     * @param string               $message The log message.
     * @param array<string, mixed> $context Additional context data.
     * @return void
     */
    public function log(string $level, string $message, array $context = []): void
    {
        // Check if this level should be logged.
        if (!$this->should_log($level)) {
            return;
        }

        // Interpolate context into message if placeholders exist.
        $message = $this->interpolate($message, $context);

        // Log to database.
        $this->log_to_database($level, $message, $context);

        // Also log to WooCommerce logger if available.
        $this->log_to_woocommerce($level, $message, $context);
    }

    /**
     * Check if a message at the given level should be logged.
     *
     * @since 1.0.0
     * @param string $level The log level to check.
     * @return bool True if should log, false otherwise.
     */
    private function should_log(string $level): bool
    {
        $level_priority = self::LEVEL_PRIORITY[$level] ?? 0;
        $current_priority = self::LEVEL_PRIORITY[$this->level] ?? 0;

        return $level_priority >= $current_priority;
    }

    /**
     * Interpolate context values into the message.
     *
     * @since 1.0.0
     * @param string               $message The message with placeholders.
     * @param array<string, mixed> $context The context values.
     * @return string The interpolated message.
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];

        foreach ($context as $key => $val) {
            if (is_string($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = (string) $val;
            } elseif (is_array($val) || is_object($val)) {
                $replace['{' . $key . '}'] = wp_json_encode($val);
            } else {
                $replace['{' . $key . '}'] = (string) $val;
            }
        }

        return strtr($message, $replace);
    }

    /**
     * Log to the database.
     *
     * @since 1.0.0
     * @param string               $level   The log level.
     * @param string               $message The log message.
     * @param array<string, mixed> $context Additional context data.
     * @return void
     */
    private function log_to_database(string $level, string $message, array $context): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sap_wc_sync_log';

        // Check if table exists.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $table
            )
        );

        if (!$table_exists) {
            return;
        }

        // Extract specific fields from context if available.
        $sync_type = $context['sync_type'] ?? 'log';
        $wc_id = $context['wc_id'] ?? null;
        $sap_id = $context['sap_id'] ?? null;

        // Remove extracted fields from context.
        unset($context['sync_type'], $context['wc_id'], $context['sap_id']);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $table,
            [
                'sync_type' => $sync_type,
                'wc_id' => $wc_id,
                'sap_id' => $sap_id,
                'status' => $level,
                'direction' => $context['direction'] ?? 'internal',
                'message' => $message,
                'request_data' => isset($context['request']) ? wp_json_encode($context['request']) : null,
                'response_data' => isset($context['response']) ? wp_json_encode($context['response']) : null,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Log to WooCommerce logger.
     *
     * @since 1.0.0
     * @param string               $level   The log level.
     * @param string               $message The log message.
     * @param array<string, mixed> $context Additional context data.
     * @return void
     */
    private function log_to_woocommerce(string $level, string $message, array $context): void
    {
        if (!function_exists('wc_get_logger')) {
            return;
        }

        $logger = wc_get_logger();

        // Map our levels to WC levels.
        $wc_level = match ($level) {
            self::DEBUG => 'debug',
            self::INFO => 'info',
            self::WARNING => 'warning',
            self::ERROR => 'error',
            self::CRITICAL => 'critical',
            default => 'info',
        };

        $logger->log(
            $wc_level,
            $message,
            [
                'source' => 'sap-woocommerce-sync',
                'context' => $context,
            ]
        );
    }

    /**
     * Get logs from the database.
     *
     * @since 1.0.0
     * @param array<string, mixed> $args Query arguments.
     * @return array<int, object> Array of log entries.
     */
    public function get_logs(array $args = []): array
    {
        global $wpdb;

        $defaults = [
            'sync_type' => '',
            'status' => '',
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);
        $table = $wpdb->prefix . 'sap_wc_sync_log';
        $where = [];
        $values = [];

        if (!empty($args['sync_type'])) {
            $where[] = 'sync_type = %s';
            $values[] = $args['sync_type'];
        }

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = (absint($args['page']) - 1) * absint($args['per_page']);

        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']) ?: 'created_at DESC';
        $values[] = absint($args['per_page']);
        $values[] = $offset;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d",
                ...$values
            )
        );

        return $results ?: [];
    }

    /**
     * Get total log count.
     *
     * @since 1.0.0
     * @param array<string, mixed> $args Query arguments.
     * @return int Total count.
     */
    public function get_logs_count(array $args = []): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sap_wc_sync_log';
        $where = [];
        $values = [];

        if (!empty($args['sync_type'])) {
            $where[] = 'sync_type = %s';
            $values[] = $args['sync_type'];
        }

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if (!empty($values)) {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} {$where_clause}",
                    ...$values
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        }

        return absint($count);
    }

    /**
     * Delete old logs.
     *
     * @since 1.0.0
     * @param int $days Number of days to keep.
     * @return int Number of deleted rows.
     */
    public function cleanup_old_logs(int $days = 30): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sap_wc_sync_log';
        $cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                $cutoff
            )
        );

        return absint($deleted);
    }
}
