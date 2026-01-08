<?php
/**
 * Customer Sync Handler class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/Sync
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Jehankandy\SAP_WooCommerce_Sync\Sync;

use Jehankandy\SAP_WooCommerce_Sync\Exceptions\SAP_Exception;
use Jehankandy\SAP_WooCommerce_Sync\Mappers\Customer_Mapper;
use Jehankandy\SAP_WooCommerce_Sync\SAP\Client;
use Jehankandy\SAP_WooCommerce_Sync\SAP\Request_Builder;
use Jehankandy\SAP_WooCommerce_Sync\SAP\Response_Parser;
use Jehankandy\SAP_WooCommerce_Sync\Utilities\Helper;
use Jehankandy\SAP_WooCommerce_Sync\Utilities\Logger;

/**
 * Handles customer synchronization between WooCommerce and SAP.
 *
 * @since 1.0.0
 */
class Customer_Sync
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
     * @param Client               $client   SAP client instance.
     * @param Logger               $logger   Logger instance.
     * @param array<string, mixed> $settings Plugin settings.
     */
    public function __construct(Client $client, Logger $logger, array $settings = [])
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->settings = $settings;
    }

    /**
     * Ensure customer exists in SAP.
     *
     * @since 1.0.0
     * @param \WC_Order $order WooCommerce order.
     * @return string SAP CardCode.
     * @throws SAP_Exception On SAP error.
     */
    public function ensure_customer(\WC_Order $order): string
    {
        $email = $order->get_billing_email();

        // Try to find existing customer by email.
        $existing = $this->find_by_email($email);

        if ($existing) {
            $this->update_local_mapping($order, $existing);
            return $existing;
        }

        // Check if auto-create is enabled.
        if (empty($this->settings['auto_create_customers'])) {
            // Use default customer.
            return $this->settings['default_customer_code'] ?? 'WALKIN';
        }

        // Create new customer in SAP.
        return $this->create_customer($order);
    }

    /**
     * Find customer in SAP by email.
     *
     * @since 1.0.0
     * @param string $email Customer email.
     * @return string|null SAP CardCode or null if not found.
     */
    public function find_by_email(string $email): ?string
    {
        try {
            $query = Request_Builder::create()
                ->select(['CardCode', 'CardName', 'EmailAddress'])
                ->where_equals('EmailAddress', $email)
                ->where_equals('CardType', 'cCustomer')
                ->top(1);

            $response = $this->client->get_business_partners($query);
            $data = Response_Parser::parse_collection($response);

            if (!empty($data['items'])) {
                return $data['items'][0]['CardCode'];
            }

            return null;
        } catch (SAP_Exception $e) {
            $this->logger->warning('Failed to search for customer by email', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Find customer in SAP by CardCode.
     *
     * @since 1.0.0
     * @param string $card_code SAP CardCode.
     * @return array<string, mixed>|null Customer data or null if not found.
     */
    public function find_by_code(string $card_code): ?array
    {
        try {
            $response = $this->client->get_business_partner($card_code);
            return Response_Parser::parse_business_partner($response);
        } catch (SAP_Exception $e) {
            if ('NOT_FOUND' === $e->get_sap_error_code()) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Create customer in SAP.
     *
     * @since 1.0.0
     * @param \WC_Order $order WooCommerce order.
     * @return string SAP CardCode.
     * @throws SAP_Exception On SAP error.
     */
    public function create_customer(\WC_Order $order): string
    {
        $mapper = new Customer_Mapper();
        $customer = $mapper->map($order);
        $card_code = $customer['CardCode'];

        $this->logger->info('Creating customer in SAP', [
            'card_code' => $card_code,
            'email' => $order->get_billing_email(),
        ]);

        try {
            $response = $this->client->create_business_partner($customer);
            $created = Response_Parser::parse_business_partner($response);

            // Update local mapping.
            $this->update_local_mapping($order, $created['card_code'] ?? $card_code);
            $this->save_customer_mapping($order, $card_code);

            $this->logger->info('Customer created in SAP', [
                'card_code' => $card_code,
            ]);

            return $card_code;
        } catch (SAP_Exception $e) {
            $this->logger->error('Failed to create customer in SAP', [
                'card_code' => $card_code,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update local user meta with SAP card code.
     *
     * @since 1.0.0
     * @param \WC_Order $order     WooCommerce order.
     * @param string    $card_code SAP CardCode.
     * @return void
     */
    private function update_local_mapping(\WC_Order $order, string $card_code): void
    {
        $customer_id = $order->get_customer_id();

        if ($customer_id) {
            update_user_meta($customer_id, '_sap_card_code', $card_code);
        }
    }

    /**
     * Save customer mapping to database.
     *
     * @since 1.0.0
     * @param \WC_Order $order     WooCommerce order.
     * @param string    $card_code SAP CardCode.
     * @return void
     */
    private function save_customer_mapping(\WC_Order $order, string $card_code): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'sap_wc_customer_map';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->replace(
            $table,
            [
                'wc_customer_id' => $order->get_customer_id() ?: 0,
                'wc_email' => $order->get_billing_email(),
                'sap_card_code' => $card_code,
                'sap_card_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'sync_status' => 'synced',
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
    }
}
