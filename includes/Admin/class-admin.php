<?php
/**
 * Admin Controller class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/Admin
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Jehankandy\SAP_WooCommerce_Sync\Admin;

use Jehankandy\SAP_WooCommerce_Sync\Plugin;
use Jehankandy\SAP_WooCommerce_Sync\Utilities\Validator;

/**
 * Main admin controller for the plugin.
 *
 * @since 1.0.0
 */
class Admin
{

    /**
     * Plugin instance.
     *
     * @since 1.0.0
     * @var Plugin
     */
    private Plugin $plugin;

    /**
     * Admin page hook suffix.
     *
     * @since 1.0.0
     * @var string
     */
    private string $page_hook = '';

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param Plugin $plugin Plugin instance.
     */
    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Register admin menu.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_menu(): void
    {
        // Main menu under WooCommerce.
        $this->page_hook = add_submenu_page(
            'woocommerce',
            __('SAP Sync', 'sap-woocommerce-sync'),
            __('SAP Sync', 'sap-woocommerce-sync'),
            'manage_woocommerce',
            'sap-wc-sync',
            [$this, 'render_dashboard']
        );

        // Settings page.
        add_submenu_page(
            'woocommerce',
            __('SAP Sync Settings', 'sap-woocommerce-sync'),
            __('SAP Settings', 'sap-woocommerce-sync'),
            'manage_woocommerce',
            'sap-wc-sync-settings',
            [$this, 'render_settings']
        );

        // Logs page (hidden from menu, accessible via dashboard).
        add_submenu_page(
            null,
            __('SAP Sync Logs', 'sap-woocommerce-sync'),
            __('Logs', 'sap-woocommerce-sync'),
            'manage_woocommerce',
            'sap-wc-sync-logs',
            [$this, 'render_logs']
        );
    }

    /**
     * Register settings.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_settings(): void
    {
        register_setting(
            'sap_wc_sync_settings',
            'sap_wc_sync_settings',
            [
                'type' => 'array',
                'sanitize_callback' => [Validator::class, 'sanitize_settings'],
            ]
        );

        // SAP Connection Section.
        add_settings_section(
            'sap_connection',
            __('SAP Connection', 'sap-woocommerce-sync'),
            [$this, 'render_section_connection'],
            'sap-wc-sync-settings'
        );

        add_settings_field(
            'sap_service_url',
            __('Service Layer URL', 'sap-woocommerce-sync'),
            [$this, 'render_field_url'],
            'sap-wc-sync-settings',
            'sap_connection'
        );

        add_settings_field(
            'sap_company_db',
            __('Company Database', 'sap-woocommerce-sync'),
            [$this, 'render_field_company'],
            'sap-wc-sync-settings',
            'sap_connection'
        );

        add_settings_field(
            'sap_username',
            __('Username', 'sap-woocommerce-sync'),
            [$this, 'render_field_username'],
            'sap-wc-sync-settings',
            'sap_connection'
        );

        add_settings_field(
            'sap_password',
            __('Password', 'sap-woocommerce-sync'),
            [$this, 'render_field_password'],
            'sap-wc-sync-settings',
            'sap_connection'
        );

        // Sync Options Section.
        add_settings_section(
            'sync_options',
            __('Sync Options', 'sap-woocommerce-sync'),
            [$this, 'render_section_sync'],
            'sap-wc-sync-settings'
        );

        add_settings_field(
            'stock_sync_interval',
            __('Stock Sync Interval', 'sap-woocommerce-sync'),
            [$this, 'render_field_interval'],
            'sap-wc-sync-settings',
            'sync_options'
        );

        add_settings_field(
            'auto_sync_orders',
            __('Auto Sync Orders', 'sap-woocommerce-sync'),
            [$this, 'render_field_auto_orders'],
            'sap-wc-sync-settings',
            'sync_options'
        );

        add_settings_field(
            'auto_create_customers',
            __('Auto Create Customers', 'sap-woocommerce-sync'),
            [$this, 'render_field_auto_customers'],
            'sap-wc-sync-settings',
            'sync_options'
        );

        // Advanced Section.
        add_settings_section(
            'advanced',
            __('Advanced', 'sap-woocommerce-sync'),
            [$this, 'render_section_advanced'],
            'sap-wc-sync-settings'
        );

        add_settings_field(
            'default_warehouse',
            __('Default Warehouse', 'sap-woocommerce-sync'),
            [$this, 'render_field_warehouse'],
            'sap-wc-sync-settings',
            'advanced'
        );

        add_settings_field(
            'log_level',
            __('Log Level', 'sap-woocommerce-sync'),
            [$this, 'render_field_log_level'],
            'sap-wc-sync-settings',
            'advanced'
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_assets(string $hook): void
    {
        // Only on our pages.
        if (!$this->is_plugin_page($hook)) {
            return;
        }

        wp_enqueue_style(
            'sap-wc-sync-admin',
            SAP_WC_SYNC_URL . 'assets/css/admin.css',
            [],
            SAP_WC_SYNC_VERSION
        );

        wp_enqueue_script(
            'sap-wc-sync-admin',
            SAP_WC_SYNC_URL . 'assets/js/admin.js',
            ['jquery'],
            SAP_WC_SYNC_VERSION,
            true
        );

        wp_localize_script(
            'sap-wc-sync-admin',
            'sapWcSync',
            [
                'nonce' => wp_create_nonce('sap_wc_sync_admin'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'strings' => [
                    'testing' => __('Testing connection...', 'sap-woocommerce-sync'),
                    'success' => __('Connection successful!', 'sap-woocommerce-sync'),
                    'error' => __('Connection failed:', 'sap-woocommerce-sync'),
                    'syncing' => __('Syncing...', 'sap-woocommerce-sync'),
                    'confirmSync' => __('Are you sure you want to run a full sync?', 'sap-woocommerce-sync'),
                ],
            ]
        );
    }

    /**
     * Check if current page is a plugin page.
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook.
     * @return bool True if plugin page.
     */
    private function is_plugin_page(string $hook): bool
    {
        $plugin_pages = [
            'woocommerce_page_sap-wc-sync',
            'woocommerce_page_sap-wc-sync-settings',
            'admin_page_sap-wc-sync-logs',
        ];

        return in_array($hook, $plugin_pages, true);
    }

    /**
     * Render dashboard page.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_dashboard(): void
    {
        $is_configured = $this->plugin->is_configured();
        $settings = $this->plugin->get_settings();
        $queue_manager = $this->plugin->get_queue_manager();

        // Get sync statistics.
        $pending_orders = $queue_manager->get_pending_count('sap-wc-sync-orders');
        $pending_stock = $queue_manager->get_pending_count('sap-wc-sync-stock');
        $failed_jobs = count($queue_manager->get_failed_jobs(10));

        include SAP_WC_SYNC_DIR . 'templates/admin/dashboard.php';
    }

    /**
     * Render settings page.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_settings(): void
    {
        $settings = $this->plugin->get_settings();
        include SAP_WC_SYNC_DIR . 'templates/admin/settings.php';
    }

    /**
     * Render logs page.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_logs(): void
    {
        $logger = $this->plugin->get_logger();
        $logs = $logger->get_logs([
            'per_page' => 50,
            'page' => isset($_GET['paged']) ? absint($_GET['paged']) : 1,
        ]);
        $total = $logger->get_logs_count();

        include SAP_WC_SYNC_DIR . 'templates/admin/logs.php';
    }

    // Settings field renderers.

    /**
     * Render connection section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_section_connection(): void
    {
        echo '<p>' . esc_html__('Configure your SAP Business One Service Layer connection.', 'sap-woocommerce-sync') . '</p>';
    }

    /**
     * Render sync section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_section_sync(): void
    {
        echo '<p>' . esc_html__('Configure synchronization behavior.', 'sap-woocommerce-sync') . '</p>';
    }

    /**
     * Render advanced section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_section_advanced(): void
    {
        echo '<p>' . esc_html__('Advanced configuration options.', 'sap-woocommerce-sync') . '</p>';
    }

    /**
     * Render Service Layer URL field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_field_url(): void
    {
        $settings = $this->plugin->get_settings();
        ?>
        <input type="url" name="sap_wc_sync_settings[sap_service_url]"
            value="<?php echo esc_attr($settings['sap_service_url'] ?? ''); ?>" class="regular-text"
            placeholder="https://sap-server:50000">
        <p class="description">
            <?php esc_html_e('SAP Service Layer URL (e.g., https://your-sap-server:50000)', 'sap-woocommerce-sync'); ?>
        </p>
        <?php
    }

    /**
     * Render Company Database field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_field_company(): void
    {
        $settings = $this->plugin->get_settings();
        ?>
        <input type="text" name="sap_wc_sync_settings[sap_company_db]"
            value="<?php echo esc_attr($settings['sap_company_db'] ?? ''); ?>" class="regular-text" placeholder="SBODEMOUS">
        <p class="description">
            <?php esc_html_e('SAP Business One company database name.', 'sap-woocommerce-sync'); ?>
        </p>
        <?php
    }

    /**
     * Render Username field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_field_username(): void
    {
        $settings = $this->plugin->get_settings();
        ?>
        <input type="text" name="sap_wc_sync_settings[sap_username]"
            value="<?php echo esc_attr($settings['sap_username'] ?? ''); ?>" class="regular-text" autocomplete="off">
        <?php
    }

    /**
     * Render Password field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_field_password(): void
    {
        $has_password = !empty(get_option('sap_wc_sync_credentials'));
        ?>
        <input type="password" name="sap_wc_sync_password" value="" class="regular-text" autocomplete="new-password"
            placeholder="<?php echo $has_password ? '••••••••' : ''; ?>">
        <p class="description">
            <?php if ($has_password): ?>
                <?php esc_html_e('Leave blank to keep existing password.', 'sap-woocommerce-sync'); ?>
            <?php else: ?>
                <?php esc_html_e('Enter your SAP password (will be encrypted).', 'sap-woocommerce-sync'); ?>
            <?php endif; ?>
        </p>
        <?php
    }

    /**
     * Render stock sync interval field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_field_interval(): void
    {
        $settings = $this->plugin->get_settings();
        ?>
        <input type="number" name="sap_wc_sync_settings[stock_sync_interval]"
            value="<?php echo esc_attr($settings['stock_sync_interval'] ?? 5); ?>" min="1" max="1440" class="small-text">
        <?php esc_html_e('minutes', 'sap-woocommerce-sync'); ?>
        <p class="description">
            <?php esc_html_e('How often to sync stock levels from SAP (1-1440 minutes).', 'sap-woocommerce-sync'); ?>
        </p>
        <?php
    }

    /**
     * Render auto sync orders field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_field_auto_orders(): void
    {
        $settings = $this->plugin->get_settings();
        ?>
        <label>
            <input type="checkbox" name="sap_wc_sync_settings[auto_sync_orders]" value="1" <?php checked(!empty($settings['auto_sync_orders'])); ?>>
            <?php esc_html_e('Automatically sync new orders to SAP', 'sap-woocommerce-sync'); ?>
        </label>
        <?php
    }

    /**
     * Render auto create customers field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_field_auto_customers(): void
    {
        $settings = $this->plugin->get_settings();
        ?>
        <label>
            <input type="checkbox" name="sap_wc_sync_settings[auto_create_customers]" value="1" <?php checked(!empty($settings['auto_create_customers'])); ?>>
            <?php esc_html_e('Create new Business Partner in SAP for new customers', 'sap-woocommerce-sync'); ?>
        </label>
        <?php
    }

    /**
     * Render default warehouse field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_field_warehouse(): void
    {
        $settings = $this->plugin->get_settings();
        ?>
        <input type="text" name="sap_wc_sync_settings[default_warehouse]"
            value="<?php echo esc_attr($settings['default_warehouse'] ?? ''); ?>" class="regular-text" placeholder="01">
        <p class="description">
            <?php esc_html_e('Default SAP warehouse code for stock sync.', 'sap-woocommerce-sync'); ?>
        </p>
        <?php
    }

    /**
     * Render log level field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_field_log_level(): void
    {
        $settings = $this->plugin->get_settings();
        $levels = [
            'debug' => __('Debug (All)', 'sap-woocommerce-sync'),
            'info' => __('Info', 'sap-woocommerce-sync'),
            'warning' => __('Warning', 'sap-woocommerce-sync'),
            'error' => __('Error', 'sap-woocommerce-sync'),
            'critical' => __('Critical', 'sap-woocommerce-sync'),
        ];
        ?>
        <select name="sap_wc_sync_settings[log_level]">
            <?php foreach ($levels as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($settings['log_level'] ?? 'info', $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
}
