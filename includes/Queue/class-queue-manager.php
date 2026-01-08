<?php
/**
 * Queue Manager class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/Queue
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Jehankandy\SAP_WooCommerce_Sync\Queue;

use Jehankandy\SAP_WooCommerce_Sync\Utilities\Logger;

/**
 * Manages background job queuing using Action Scheduler.
 *
 * @since 1.0.0
 */
class Queue_Manager
{

    /**
     * Action Scheduler group for orders.
     *
     * @since 1.0.0
     * @var string
     */
    public const GROUP_ORDERS = 'sap-wc-sync-orders';

    /**
     * Action Scheduler group for stock.
     *
     * @since 1.0.0
     * @var string
     */
    public const GROUP_STOCK = 'sap-wc-sync-stock';

    /**
     * Action Scheduler group for products.
     *
     * @since 1.0.0
     * @var string
     */
    public const GROUP_PRODUCTS = 'sap-wc-sync-products';

    /**
     * Maximum retry attempts.
     *
     * @since 1.0.0
     * @var int
     */
    public const MAX_RETRIES = 5;

    /**
     * Logger instance.
     *
     * @since 1.0.0
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param Logger $logger Logger instance.
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Queue an order for sync.
     *
     * @since 1.0.0
     * @param int $order_id    WooCommerce order ID.
     * @param int $delay       Delay in seconds before processing.
     * @param int $retry_count Current retry count.
     * @return int|null Action ID or null on failure.
     */
    public function queue_order_sync(int $order_id, int $delay = 0, int $retry_count = 0): ?int
    {
        if (!$this->is_action_scheduler_available()) {
            $this->logger->error('Action Scheduler not available for order sync', [
                'order_id' => $order_id,
            ]);
            return null;
        }

        $args = [
            'order_id' => $order_id,
            'retry_count' => $retry_count,
        ];

        // Check if already scheduled.
        if ($this->is_order_scheduled($order_id)) {
            $this->logger->debug('Order already scheduled for sync', [
                'order_id' => $order_id,
            ]);
            return null;
        }

        $timestamp = time() + $delay;

        $action_id = as_schedule_single_action(
            $timestamp,
            'sap_wc_sync_order',
            $args,
            self::GROUP_ORDERS
        );

        $this->logger->info('Order queued for SAP sync', [
            'order_id' => $order_id,
            'action_id' => $action_id,
            'delay' => $delay,
        ]);

        return $action_id;
    }

    /**
     * Queue a product for stock sync.
     *
     * @since 1.0.0
     * @param int $product_id WooCommerce product ID.
     * @param int $delay      Delay in seconds.
     * @return int|null Action ID or null on failure.
     */
    public function queue_stock_pull(int $product_id, int $delay = 0): ?int
    {
        if (!$this->is_action_scheduler_available()) {
            return null;
        }

        $args = ['product_id' => $product_id];

        $action_id = as_schedule_single_action(
            time() + $delay,
            'sap_wc_pull_stock',
            $args,
            self::GROUP_STOCK
        );

        $this->logger->debug('Stock pull queued', [
            'product_id' => $product_id,
            'action_id' => $action_id,
        ]);

        return $action_id;
    }

    /**
     * Queue a full stock synchronization.
     *
     * @since 1.0.0
     * @return int|null Action ID or null on failure.
     */
    public function queue_full_stock_sync(): ?int
    {
        if (!$this->is_action_scheduler_available()) {
            return null;
        }

        $action_id = as_enqueue_async_action(
            'sap_wc_full_stock_sync',
            [],
            self::GROUP_STOCK
        );

        $this->logger->info('Full stock sync queued', [
            'action_id' => $action_id,
        ]);

        return $action_id;
    }

    /**
     * Reschedule a failed job with exponential backoff.
     *
     * @since 1.0.0
     * @param string               $hook        Action hook name.
     * @param array<string, mixed> $args        Action arguments.
     * @param int                  $retry_count Current retry count.
     * @param string               $group       Action group.
     * @return bool True if rescheduled, false if max retries exceeded.
     */
    public function reschedule_with_backoff(
        string $hook,
        array $args,
        int $retry_count,
        string $group
    ): bool {
        if ($retry_count >= self::MAX_RETRIES) {
            $this->logger->error('Max retries exceeded, moving to dead letter', [
                'hook' => $hook,
                'args' => $args,
                'retry_count' => $retry_count,
            ]);

            $this->add_to_dead_letter($hook, $group, $args, 'Max retries exceeded');
            return false;
        }

        // Calculate delay with exponential backoff (1, 2, 4, 8, 16 minutes).
        $delay_minutes = pow(2, $retry_count);
        $delay_seconds = $delay_minutes * MINUTE_IN_SECONDS;

        // Update retry count in args.
        $args['retry_count'] = $retry_count + 1;

        as_schedule_single_action(
            time() + $delay_seconds,
            $hook,
            $args,
            $group
        );

        $this->logger->warning('Job rescheduled with backoff', [
            'hook' => $hook,
            'retry_count' => $retry_count + 1,
            'delay' => $delay_seconds,
        ]);

        return true;
    }

    /**
     * Add a failed job to the dead letter queue.
     *
     * @since 1.0.0
     * @param string               $job_type      Job type/hook.
     * @param string               $job_group     Job group.
     * @param array<string, mixed> $payload       Job payload.
     * @param string               $error_message Error message.
     * @return bool True on success.
     */
    public function add_to_dead_letter(
        string $job_type,
        string $job_group,
        array $payload,
        string $error_message
    ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'sap_wc_failed_jobs';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert(
            $table,
            [
                'job_type' => $job_type,
                'job_group' => $job_group,
                'payload' => wp_json_encode($payload),
                'error_message' => $error_message,
                'attempts' => $payload['retry_count'] ?? 0,
                'max_attempts' => self::MAX_RETRIES,
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d']
        );

        return false !== $result;
    }

    /**
     * Retry a failed job from dead letter queue.
     *
     * @since 1.0.0
     * @param int $job_id Dead letter job ID.
     * @return bool True on success.
     */
    public function retry_dead_letter(int $job_id): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sap_wc_failed_jobs';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $job = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d AND resolved_at IS NULL",
                $job_id
            )
        );

        if (!$job) {
            return false;
        }

        $payload = json_decode($job->payload, true) ?: [];
        $payload['retry_count'] = 0; // Reset retry count.

        // Re-queue the job.
        as_enqueue_async_action(
            $job->job_type,
            $payload,
            $job->job_group
        );

        // Mark as resolved.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->update(
            $table,
            [
                'resolved_at' => current_time('mysql', true),
                'resolution' => 'retried',
            ],
            ['id' => $job_id],
            ['%s', '%s'],
            ['%d']
        );

        $this->logger->info('Dead letter job retried', [
            'job_id' => $job_id,
            'job_type' => $job->job_type,
        ]);

        return true;
    }

    /**
     * Check if an order is already scheduled.
     *
     * @since 1.0.0
     * @param int $order_id WooCommerce order ID.
     * @return bool True if scheduled.
     */
    public function is_order_scheduled(int $order_id): bool
    {
        if (!$this->is_action_scheduler_available()) {
            return false;
        }

        return as_has_scheduled_action(
            'sap_wc_sync_order',
            ['order_id' => $order_id],
            self::GROUP_ORDERS
        );
    }

    /**
     * Cancel pending sync for an order.
     *
     * @since 1.0.0
     * @param int $order_id WooCommerce order ID.
     * @return int Number of unscheduled actions.
     */
    public function cancel_order_sync(int $order_id): int
    {
        if (!$this->is_action_scheduler_available()) {
            return 0;
        }

        return as_unschedule_all_actions(
            'sap_wc_sync_order',
            ['order_id' => $order_id],
            self::GROUP_ORDERS
        );
    }

    /**
     * Get pending jobs count by group.
     *
     * @since 1.0.0
     * @param string $group Action group.
     * @return int Pending count.
     */
    public function get_pending_count(string $group): int
    {
        if (!$this->is_action_scheduler_available()) {
            return 0;
        }

        return as_get_scheduled_actions(
            [
                'group' => $group,
                'status' => \ActionScheduler_Store::STATUS_PENDING,
                'per_page' => -1,
            ],
            'count'
        );
    }

    /**
     * Get failed jobs from dead letter queue.
     *
     * @since 1.0.0
     * @param int $limit Maximum jobs to return.
     * @return array<int, object> Failed jobs.
     */
    public function get_failed_jobs(int $limit = 50): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sap_wc_failed_jobs';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE resolved_at IS NULL ORDER BY failed_at DESC LIMIT %d",
                $limit
            )
        );

        return $results ?: [];
    }

    /**
     * Check if Action Scheduler is available.
     *
     * @since 1.0.0
     * @return bool True if available.
     */
    private function is_action_scheduler_available(): bool
    {
        return function_exists('as_schedule_single_action');
    }
}
