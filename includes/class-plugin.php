<?php
/**
 * The main plugin class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Jehankandy\SAP_WooCommerce_Sync;

use Jehankandy\SAP_WooCommerce_Sync\Admin\Admin;
use Jehankandy\SAP_WooCommerce_Sync\Data\Database;
use Jehankandy\SAP_WooCommerce_Sync\Queue\Queue_Manager;
use Jehankandy\SAP_WooCommerce_Sync\SAP\Client;
use Jehankandy\SAP_WooCommerce_Sync\SAP\Session_Manager;
use Jehankandy\SAP_WooCommerce_Sync\Sync\Order_Sync;
use Jehankandy\SAP_WooCommerce_Sync\Sync\Stock_Sync;
use Jehankandy\SAP_WooCommerce_Sync\Utilities\Encryption;
use Jehankandy\SAP_WooCommerce_Sync\Utilities\Logger;
use Jehankandy\SAP_WooCommerce_Sync\WooCommerce\Order_Hooks;
use Jehankandy\SAP_WooCommerce_Sync\WooCommerce\Product_Hooks;
use Jehankandy\SAP_WooCommerce_Sync\WooCommerce\Stock_Hooks;

/**
 * Main plugin class implementing Singleton pattern.
 *
 * This is the core class that initializes all plugin components
 * and coordinates their interaction.
 *
 * @since 1.0.0
 */
final class Plugin
{

    /**
     * Single instance of the plugin.
     *
     * @since 1.0.0
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Plugin settings.
     *
     * @since 1.0.0
     * @var array<string, mixed>
     */
    private array $settings = [];

    /**
     * Logger instance.
     *
     * @since 1.0.0
     * @var Logger|null
     */
    private ?Logger $logger = null;

    /**
     * Encryption utility instance.
     *
     * @since 1.0.0
     * @var Encryption|null
     */
    private ?Encryption $encryption = null;

    /**
     * SAP Client instance.
     *
     * @since 1.0.0
     * @var Client|null
     */
    private ?Client $sap_client = null;

    /**
     * Queue Manager instance.
     *
     * @since 1.0.0
     * @var Queue_Manager|null
     */
    private ?Queue_Manager $queue_manager = null;

    /**
     * Get the single instance of the plugin.
     *
     * @since 1.0.0
     * @return Plugin The plugin instance.
     */
    public static function get_instance(): Plugin
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation.
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        $this->load_settings();
        $this->init_components();
        $this->register_hooks();
    }

    /**
     * Prevent cloning of the instance.
     *
     * @since 1.0.0
     * @return void
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserializing of the instance.
     *
     * @since 1.0.0
     * @throws \Exception Always throws to prevent unserializing.
     * @return void
     */
    public function __wakeup(): void
    {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Load plugin settings from database.
     *
     * @since 1.0.0
     * @return void
     */
    private function load_settings(): void
    {
        $defaults = [
            'sap_service_url' => '',
            'sap_company_db' => '',
            'sap_username' => '',
            'stock_sync_interval' => 5, // minutes
            'auto_sync_orders' => true,
            'auto_create_customers' => true,
            'default_warehouse' => '',
            'default_tax_code' => '',
            'log_level' => 'info',
            'log_retention_days' => 30,
        ];

        $saved_settings = get_option('sap_wc_sync_settings', []);
        $this->settings = wp_parse_args($saved_settings, $defaults);
    }

    /**
     * Initialize plugin components.
     *
     * @since 1.0.0
     * @return void
     */
    private function init_components(): void
    {
        // Initialize utilities first.
        $this->encryption = new Encryption();
        $this->logger = new Logger($this->settings['log_level']);

        // Initialize queue manager.
        $this->queue_manager = new Queue_Manager($this->logger);
    }

    /**
     * Register WordPress hooks.
     *
     * @since 1.0.0
     * @return void
     */
    private function register_hooks(): void
    {
        // Admin hooks.
        if (is_admin()) {
            $admin = new Admin($this);
            add_action('admin_menu', [$admin, 'register_menu']);
            add_action('admin_init', [$admin, 'register_settings']);
            add_action('admin_enqueue_scripts', [$admin, 'enqueue_assets']);
        }

        // WooCommerce hooks.
        if ($this->is_configured()) {
            $this->register_woocommerce_hooks();
            $this->register_scheduled_tasks();
        }

        // AJAX handlers.
        add_action('wp_ajax_sap_wc_sync_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_sap_wc_sync_manual_stock_sync', [$this, 'ajax_manual_stock_sync']);
        add_action('wp_ajax_sap_wc_sync_manual_order_sync', [$this, 'ajax_manual_order_sync']);

        // Action Scheduler hooks.
        add_action('sap_wc_sync_order', [$this, 'process_order_sync'], 10, 1);
        add_action('sap_wc_pull_stock', [$this, 'process_stock_pull'], 10, 1);
        add_action('sap_wc_full_stock_sync', [$this, 'process_full_stock_sync'], 10, 0);
    }

    /**
     * Register WooCommerce hooks.
     *
     * @since 1.0.0
     * @return void
     */
    private function register_woocommerce_hooks(): void
    {
        $order_hooks = new Order_Hooks($this->queue_manager, $this->logger, $this->settings);
        $product_hooks = new Product_Hooks($this->logger, $this->settings);
        $stock_hooks = new Stock_Hooks($this->logger, $this->settings);

        // Order events.
        add_action('woocommerce_checkout_order_processed', [$order_hooks, 'on_order_created'], 10, 3);
        add_action('woocommerce_order_status_changed', [$order_hooks, 'on_order_status_changed'], 10, 4);
        add_action('woocommerce_order_refunded', [$order_hooks, 'on_order_refunded'], 10, 2);

        // Stock events.
        add_action('woocommerce_reduce_stock_levels', [$stock_hooks, 'on_stock_reduced'], 10, 1);
    }

    /**
     * Register scheduled tasks.
     *
     * @since 1.0.0
     * @return void
     */
    private function register_scheduled_tasks(): void
    {
        $interval = absint($this->settings['stock_sync_interval']) * MINUTE_IN_SECONDS;

        // Schedule recurring stock sync if not already scheduled.
        if (false === as_has_scheduled_action('sap_wc_full_stock_sync')) {
            as_schedule_recurring_action(
                time(),
                $interval,
                'sap_wc_full_stock_sync',
                [],
                'sap-wc-sync-stock'
            );
        }
    }

    /**
     * Check if plugin is properly configured.
     *
     * @since 1.0.0
     * @return bool True if configured, false otherwise.
     */
    public function is_configured(): bool
    {
        return !empty($this->settings['sap_service_url'])
            && !empty($this->settings['sap_company_db'])
            && !empty($this->settings['sap_username']);
    }

    /**
     * Get plugin settings.
     *
     * @since 1.0.0
     * @param string|null $key Optional specific setting key.
     * @return mixed All settings or specific setting value.
     */
    public function get_settings(?string $key = null): mixed
    {
        if (null === $key) {
            return $this->settings;
        }

        return $this->settings[$key] ?? null;
    }

    /**
     * Get the SAP client instance.
     *
     * @since 1.0.0
     * @return Client|null The SAP client instance or null if not configured.
     */
    public function get_sap_client(): ?Client
    {
        if (null === $this->sap_client && $this->is_configured()) {
            $session_manager = new Session_Manager(
                $this->settings['sap_service_url'],
                $this->settings['sap_company_db'],
                $this->settings['sap_username'],
                $this->get_decrypted_password(),
                $this->logger
            );

            $this->sap_client = new Client(
                $this->settings['sap_service_url'],
                $session_manager,
                $this->logger
            );
        }

        return $this->sap_client;
    }

    /**
     * Get decrypted SAP password.
     *
     * @since 1.0.0
     * @return string The decrypted password.
     */
    private function get_decrypted_password(): string
    {
        $encrypted_password = get_option('sap_wc_sync_credentials', '');

        if (empty($encrypted_password)) {
            return '';
        }

        return $this->encryption->decrypt($encrypted_password);
    }

    /**
     * Get the logger instance.
     *
     * @since 1.0.0
     * @return Logger The logger instance.
     */
    public function get_logger(): Logger
    {
        return $this->logger;
    }

    /**
     * Get the encryption instance.
     *
     * @since 1.0.0
     * @return Encryption The encryption instance.
     */
    public function get_encryption(): Encryption
    {
        return $this->encryption;
    }

    /**
     * Get the queue manager instance.
     *
     * @since 1.0.0
     * @return Queue_Manager The queue manager instance.
     */
    public function get_queue_manager(): Queue_Manager
    {
        return $this->queue_manager;
    }

    /**
     * AJAX handler for testing SAP connection.
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_test_connection(): void
    {
        check_ajax_referer('sap_wc_sync_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied.', 'sap-woocommerce-sync')]);
        }

        $client = $this->get_sap_client();

        if (null === $client) {
            wp_send_json_error(['message' => __('SAP connection not configured.', 'sap-woocommerce-sync')]);
        }

        try {
            $result = $client->test_connection();
            wp_send_json_success($result);
        } catch (\Exception $e) {
            $this->logger->error('Connection test failed: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX handler for manual stock sync.
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_manual_stock_sync(): void
    {
        check_ajax_referer('sap_wc_sync_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied.', 'sap-woocommerce-sync')]);
        }

        // Queue immediate stock sync.
        as_enqueue_async_action(
            'sap_wc_full_stock_sync',
            [],
            'sap-wc-sync-stock'
        );

        wp_send_json_success(['message' => __('Stock sync has been queued.', 'sap-woocommerce-sync')]);
    }

    /**
     * AJAX handler for manual order sync.
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_manual_order_sync(): void
    {
        check_ajax_referer('sap_wc_sync_admin', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied.', 'sap-woocommerce-sync')]);
        }

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;

        if (!$order_id) {
            wp_send_json_error(['message' => __('Invalid order ID.', 'sap-woocommerce-sync')]);
        }

        // Queue order sync.
        as_enqueue_async_action(
            'sap_wc_sync_order',
            ['order_id' => $order_id],
            'sap-wc-sync-orders'
        );

        wp_send_json_success([
            'message' => sprintf(
                /* translators: %d: Order ID */
                __('Order #%d has been queued for sync.', 'sap-woocommerce-sync'),
                $order_id
            ),
        ]);
    }

    /**
     * Process order sync action.
     *
     * @since 1.0.0
     * @param int $order_id The WooCommerce order ID.
     * @return void
     */
    public function process_order_sync(int $order_id): void
    {
        $client = $this->get_sap_client();

        if (null === $client) {
            $this->logger->error(sprintf('Cannot sync order #%d: SAP not configured', $order_id));
            return;
        }

        $order_sync = new Order_Sync($client, $this->logger);
        $order_sync->sync_order($order_id);
    }

    /**
     * Process single product stock pull.
     *
     * @since 1.0.0
     * @param int $product_id The WooCommerce product ID.
     * @return void
     */
    public function process_stock_pull(int $product_id): void
    {
        $client = $this->get_sap_client();

        if (null === $client) {
            $this->logger->error(sprintf('Cannot pull stock for product #%d: SAP not configured', $product_id));
            return;
        }

        $stock_sync = new Stock_Sync($client, $this->logger);
        $stock_sync->sync_product_stock($product_id);
    }

    /**
     * Process full stock sync.
     *
     * @since 1.0.0
     * @return void
     */
    public function process_full_stock_sync(): void
    {
        $client = $this->get_sap_client();

        if (null === $client) {
            $this->logger->error('Cannot run full stock sync: SAP not configured');
            return;
        }

        $stock_sync = new Stock_Sync($client, $this->logger);
        $stock_sync->sync_all_stock();
    }
}
