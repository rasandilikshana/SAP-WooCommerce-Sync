<?php
/**
 * SAP HTTP Client class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/SAP
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Jehankandy\SAP_WooCommerce_Sync\SAP;

use Jehankandy\SAP_WooCommerce_Sync\Exceptions\Authentication_Exception;
use Jehankandy\SAP_WooCommerce_Sync\Exceptions\Connection_Exception;
use Jehankandy\SAP_WooCommerce_Sync\Exceptions\SAP_Exception;
use Jehankandy\SAP_WooCommerce_Sync\Utilities\Logger;

/**
 * HTTP Client for SAP Service Layer API.
 *
 * Provides methods for GET, POST, PATCH, DELETE requests
 * with automatic session management and retry logic.
 *
 * @since 1.0.0
 */
class Client
{

    /**
     * SAP Service Layer base URL.
     *
     * @since 1.0.0
     * @var string
     */
    private string $base_url;

    /**
     * Session manager instance.
     *
     * @since 1.0.0
     * @var Session_Manager
     */
    private Session_Manager $session_manager;

    /**
     * Logger instance.
     *
     * @since 1.0.0
     * @var Logger
     */
    private Logger $logger;

    /**
     * Default request timeout in seconds.
     *
     * @since 1.0.0
     * @var int
     */
    private int $timeout = 60;

    /**
     * Maximum retry attempts.
     *
     * @since 1.0.0
     * @var int
     */
    private int $max_retries = 3;

    /**
     * API version (v1 or v2).
     *
     * @since 1.0.0
     * @var string
     */
    private string $api_version = 'v1';

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param string          $base_url        SAP Service Layer URL.
     * @param Session_Manager $session_manager Session manager instance.
     * @param Logger          $logger          Logger instance.
     */
    public function __construct(
        string $base_url,
        Session_Manager $session_manager,
        Logger $logger
    ) {
        $this->base_url = rtrim($base_url, '/');
        $this->session_manager = $session_manager;
        $this->logger = $logger;
    }

    /**
     * Set the API version.
     *
     * @since 1.0.0
     * @param string $version API version (v1 or v2).
     * @return static
     */
    public function set_api_version(string $version): static
    {
        $this->api_version = $version;
        return $this;
    }

    /**
     * Set request timeout.
     *
     * @since 1.0.0
     * @param int $seconds Timeout in seconds.
     * @return static
     */
    public function set_timeout(int $seconds): static
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Perform a GET request.
     *
     * @since 1.0.0
     * @param string               $endpoint API endpoint (e.g., 'Items').
     * @param array<string, mixed> $params   Query parameters.
     * @return array<string, mixed> Response data.
     * @throws SAP_Exception On API error.
     * @throws Connection_Exception On connection error.
     */
    public function get(string $endpoint, array $params = []): array
    {
        return $this->request('GET', $endpoint, [], $params);
    }

    /**
     * Perform a POST request.
     *
     * @since 1.0.0
     * @param string               $endpoint API endpoint.
     * @param array<string, mixed> $data     Request body data.
     * @return array<string, mixed> Response data.
     * @throws SAP_Exception On API error.
     * @throws Connection_Exception On connection error.
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Perform a PATCH request.
     *
     * @since 1.0.0
     * @param string               $endpoint API endpoint.
     * @param array<string, mixed> $data     Request body data.
     * @return array<string, mixed> Response data.
     * @throws SAP_Exception On API error.
     * @throws Connection_Exception On connection error.
     */
    public function patch(string $endpoint, array $data = []): array
    {
        return $this->request('PATCH', $endpoint, $data);
    }

    /**
     * Perform a DELETE request.
     *
     * @since 1.0.0
     * @param string $endpoint API endpoint.
     * @return array<string, mixed> Response data.
     * @throws SAP_Exception On API error.
     * @throws Connection_Exception On connection error.
     */
    public function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Perform an HTTP request.
     *
     * @since 1.0.0
     * @param string               $method   HTTP method.
     * @param string               $endpoint API endpoint.
     * @param array<string, mixed> $data     Request body data.
     * @param array<string, mixed> $params   Query parameters.
     * @return array<string, mixed> Response data.
     * @throws SAP_Exception On API error.
     * @throws Connection_Exception On connection error.
     */
    private function request(
        string $method,
        string $endpoint,
        array $data = [],
        array $params = []
    ): array {
        $attempt = 0;

        while ($attempt < $this->max_retries) {
            $attempt++;

            try {
                return $this->execute_request($method, $endpoint, $data, $params);
            } catch (Authentication_Exception $e) {
                // Session expired - refresh and retry.
                if ($e->is_retryable() && $attempt < $this->max_retries) {
                    $this->logger->warning('Session expired, refreshing and retrying', [
                        'attempt' => $attempt,
                    ]);
                    $this->session_manager->refresh();
                    continue;
                }
                throw $e;
            } catch (Connection_Exception $e) {
                // Network error - retry with backoff.
                if ($attempt < $this->max_retries) {
                    $wait_seconds = pow(2, $attempt);
                    $this->logger->warning('Connection error, retrying', [
                        'attempt' => $attempt,
                        'wait_seconds' => $wait_seconds,
                        'error' => $e->getMessage(),
                    ]);
                    sleep($wait_seconds);
                    continue;
                }
                throw $e;
            }
        }

        throw new Connection_Exception(__('Maximum retry attempts exceeded.', 'sap-woocommerce-sync'));
    }

    /**
     * Execute a single HTTP request.
     *
     * @since 1.0.0
     * @param string               $method   HTTP method.
     * @param string               $endpoint API endpoint.
     * @param array<string, mixed> $data     Request body data.
     * @param array<string, mixed> $params   Query parameters.
     * @return array<string, mixed> Response data.
     * @throws SAP_Exception On API error.
     * @throws Authentication_Exception On auth error.
     * @throws Connection_Exception On connection error.
     */
    private function execute_request(
        string $method,
        string $endpoint,
        array $data = [],
        array $params = []
    ): array {
        // Get session.
        $session = $this->session_manager->get_session();

        // Build URL.
        $url = $this->build_url($endpoint, $params);

        // Prepare request arguments.
        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Cookie' => $this->session_manager->format_cookies($session),
            ],
            'sslverify' => !defined('SAP_WC_SYNC_SKIP_SSL_VERIFY'),
        ];

        // Add body for POST/PATCH.
        if (in_array($method, ['POST', 'PATCH'], true) && !empty($data)) {
            $args['body'] = wp_json_encode($data);
        }

        $this->logger->debug('SAP API request', [
            'method' => $method,
            'url' => $url,
            'request' => $data,
        ]);

        // Make request.
        $response = wp_remote_request($url, $args);

        // Check for WP_Error.
        if (is_wp_error($response)) {
            throw Connection_Exception::from_wp_error($response);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body, true) ?: [];

        $this->logger->debug('SAP API response', [
            'status' => $status_code,
            'response' => $response_data,
        ]);

        // Handle different status codes.
        return $this->handle_response($status_code, $response_data, $method);
    }

    /**
     * Handle response status codes.
     *
     * @since 1.0.0
     * @param int                  $status_code   HTTP status code.
     * @param array<string, mixed> $response_data Response data.
     * @param string               $method        HTTP method.
     * @return array<string, mixed> Response data.
     * @throws SAP_Exception On API error.
     * @throws Authentication_Exception On auth error.
     */
    private function handle_response(int $status_code, array $response_data, string $method): array
    {
        // Success responses.
        if ($status_code >= 200 && $status_code < 300) {
            return $response_data;
        }

        // No content (common for DELETE).
        if (204 === $status_code) {
            return ['success' => true];
        }

        // Authentication errors.
        if (401 === $status_code) {
            throw Authentication_Exception::session_expired();
        }

        if (403 === $status_code) {
            throw new Authentication_Exception(
                __('Access forbidden. Check user permissions.', 'sap-woocommerce-sync')
            );
        }

        // Not found.
        if (404 === $status_code) {
            throw new SAP_Exception(
                __('Resource not found.', 'sap-woocommerce-sync'),
                'NOT_FOUND'
            );
        }

        // SAP error response.
        if (isset($response_data['error'])) {
            throw SAP_Exception::from_response($response_data);
        }

        // Generic error.
        throw new SAP_Exception(
            sprintf(
                /* translators: %d: HTTP status code */
                __('Unexpected response status: %d', 'sap-woocommerce-sync'),
                $status_code
            ),
            (string) $status_code
        );
    }

    /**
     * Build request URL.
     *
     * @since 1.0.0
     * @param string               $endpoint API endpoint.
     * @param array<string, mixed> $params   Query parameters.
     * @return string Complete URL.
     */
    private function build_url(string $endpoint, array $params = []): string
    {
        // Remove leading slash from endpoint.
        $endpoint = ltrim($endpoint, '/');

        // Build base URL with API version.
        $url = sprintf('%s/b1s/%s/%s', $this->base_url, $this->api_version, $endpoint);

        // Add query parameters.
        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }

        return $url;
    }

    /**
     * Test the SAP connection.
     *
     * @since 1.0.0
     * @return array<string, mixed> Connection info.
     * @throws SAP_Exception On API error.
     * @throws Connection_Exception On connection error.
     */
    public function test_connection(): array
    {
        try {
            // Try to get the company info.
            $session = $this->session_manager->get_session();

            return [
                'success' => true,
                'message' => __('Connection successful!', 'sap-woocommerce-sync'),
                'session' => !empty($session['B1SESSION']),
                'company_db' => true,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get items (products) from SAP.
     *
     * @since 1.0.0
     * @param Request_Builder|null $query Optional query builder.
     * @return array<string, mixed> Response data.
     */
    public function get_items(?Request_Builder $query = null): array
    {
        $params = $query ? $query->build() : [];
        return $this->get('Items', $params);
    }

    /**
     * Get a single item by ItemCode.
     *
     * @since 1.0.0
     * @param string $item_code The item code.
     * @return array<string, mixed> Item data.
     */
    public function get_item(string $item_code): array
    {
        $endpoint = sprintf("Items('%s')", $item_code);
        return $this->get($endpoint);
    }

    /**
     * Update an item.
     *
     * @since 1.0.0
     * @param string               $item_code The item code.
     * @param array<string, mixed> $data      Update data.
     * @return array<string, mixed> Response data.
     */
    public function update_item(string $item_code, array $data): array
    {
        $endpoint = sprintf("Items('%s')", $item_code);
        return $this->patch($endpoint, $data);
    }

    /**
     * Get orders from SAP.
     *
     * @since 1.0.0
     * @param Request_Builder|null $query Optional query builder.
     * @return array<string, mixed> Response data.
     */
    public function get_orders(?Request_Builder $query = null): array
    {
        $params = $query ? $query->build() : [];
        return $this->get('Orders', $params);
    }

    /**
     * Get a single order by DocEntry.
     *
     * @since 1.0.0
     * @param int $doc_entry The document entry number.
     * @return array<string, mixed> Order data.
     */
    public function get_order(int $doc_entry): array
    {
        $endpoint = sprintf('Orders(%d)', $doc_entry);
        return $this->get($endpoint);
    }

    /**
     * Create a new order.
     *
     * @since 1.0.0
     * @param array<string, mixed> $order_data Order data.
     * @return array<string, mixed> Created order data.
     */
    public function create_order(array $order_data): array
    {
        return $this->post('Orders', $order_data);
    }

    /**
     * Cancel an order.
     *
     * @since 1.0.0
     * @param int $doc_entry The document entry number.
     * @return array<string, mixed> Response data.
     */
    public function cancel_order(int $doc_entry): array
    {
        $endpoint = sprintf('Orders(%d)/Cancel', $doc_entry);
        return $this->post($endpoint);
    }

    /**
     * Get business partners from SAP.
     *
     * @since 1.0.0
     * @param Request_Builder|null $query Optional query builder.
     * @return array<string, mixed> Response data.
     */
    public function get_business_partners(?Request_Builder $query = null): array
    {
        $params = $query ? $query->build() : [];
        return $this->get('BusinessPartners', $params);
    }

    /**
     * Get a single business partner by CardCode.
     *
     * @since 1.0.0
     * @param string $card_code The card code.
     * @return array<string, mixed> Business partner data.
     */
    public function get_business_partner(string $card_code): array
    {
        $endpoint = sprintf("BusinessPartners('%s')", $card_code);
        return $this->get($endpoint);
    }

    /**
     * Create a new business partner.
     *
     * @since 1.0.0
     * @param array<string, mixed> $data Business partner data.
     * @return array<string, mixed> Created business partner data.
     */
    public function create_business_partner(array $data): array
    {
        return $this->post('BusinessPartners', $data);
    }

    /**
     * Update a business partner.
     *
     * @since 1.0.0
     * @param string               $card_code The card code.
     * @param array<string, mixed> $data      Update data.
     * @return array<string, mixed> Response data.
     */
    public function update_business_partner(string $card_code, array $data): array
    {
        $endpoint = sprintf("BusinessPartners('%s')", $card_code);
        return $this->patch($endpoint, $data);
    }
}
