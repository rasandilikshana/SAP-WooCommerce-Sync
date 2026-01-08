<?php
/**
 * SAP Session Manager class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/SAP
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Rasandilikshana\SAP_WooCommerce_Sync\SAP;

use Rasandilikshana\SAP_WooCommerce_Sync\Exceptions\Authentication_Exception;
use Rasandilikshana\SAP_WooCommerce_Sync\Exceptions\Connection_Exception;
use Rasandilikshana\SAP_WooCommerce_Sync\Utilities\Logger;

/**
 * Manages SAP Service Layer sessions.
 *
 * Handles authentication, session caching, and auto-refresh
 * for SAP B1SESSION and ROUTEID cookies.
 *
 * @since 1.0.0
 */
class Session_Manager
{

    /**
     * SAP Service Layer base URL.
     *
     * @since 1.0.0
     * @var string
     */
    private string $base_url;

    /**
     * SAP Company database name.
     *
     * @since 1.0.0
     * @var string
     */
    private string $company_db;

    /**
     * SAP username.
     *
     * @since 1.0.0
     * @var string
     */
    private string $username;

    /**
     * SAP password.
     *
     * @since 1.0.0
     * @var string
     */
    private string $password;

    /**
     * Logger instance.
     *
     * @since 1.0.0
     * @var Logger
     */
    private Logger $logger;

    /**
     * Session timeout in minutes.
     *
     * @since 1.0.0
     * @var int
     */
    private int $session_timeout = 30;

    /**
     * Transient key for session storage.
     *
     * @since 1.0.0
     * @var string
     */
    private string $transient_key;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param string $base_url   SAP Service Layer URL.
     * @param string $company_db Company database name.
     * @param string $username   SAP username.
     * @param string $password   SAP password.
     * @param Logger $logger     Logger instance.
     */
    public function __construct(
        string $base_url,
        string $company_db,
        string $username,
        string $password,
        Logger $logger
    ) {
        $this->base_url = rtrim($base_url, '/');
        $this->company_db = $company_db;
        $this->username = $username;
        $this->password = $password;
        $this->logger = $logger;

        // Create unique transient key based on credentials hash.
        $this->transient_key = 'sap_wc_sync_session_' . md5($this->base_url . $this->company_db . $this->username);
    }

    /**
     * Get active session cookies.
     *
     * Returns cached session if valid, otherwise creates new session.
     *
     * @since 1.0.0
     * @return array{B1SESSION: string, ROUTEID: string} Session cookies.
     * @throws Authentication_Exception If login fails.
     * @throws Connection_Exception If connection fails.
     */
    public function get_session(): array
    {
        // Try to get cached session.
        $cached = $this->get_cached_session();

        if ($cached) {
            $this->logger->debug('Using cached SAP session');
            return $cached;
        }

        // Create new session.
        $this->logger->debug('Creating new SAP session');
        return $this->login();
    }

    /**
     * Get cached session if valid.
     *
     * @since 1.0.0
     * @return array{B1SESSION: string, ROUTEID: string}|null Session cookies or null.
     */
    private function get_cached_session(): ?array
    {
        $cached = get_transient($this->transient_key);

        if (!$cached || !is_array($cached)) {
            return null;
        }

        // Verify required keys exist.
        if (empty($cached['B1SESSION']) || empty($cached['ROUTEID'])) {
            return null;
        }

        return [
            'B1SESSION' => $cached['B1SESSION'],
            'ROUTEID' => $cached['ROUTEID'],
        ];
    }

    /**
     * Perform login to SAP Service Layer.
     *
     * @since 1.0.0
     * @return array{B1SESSION: string, ROUTEID: string} Session cookies.
     * @throws Authentication_Exception If login fails.
     * @throws Connection_Exception If connection fails.
     */
    public function login(): array
    {
        $login_url = $this->base_url . '/b1s/v1/Login';

        $body = wp_json_encode([
            'CompanyDB' => $this->company_db,
            'UserName' => $this->username,
            'Password' => $this->password,
        ]);

        $this->logger->debug('Attempting SAP login', [
            'url' => $login_url,
            'company_db' => $this->company_db,
            'username' => $this->username,
        ]);

        $response = wp_remote_post(
            $login_url,
            [
                'timeout' => 30,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => $body,
                'sslverify' => !defined('SAP_WC_SYNC_SKIP_SSL_VERIFY'),
            ]
        );

        // Check for WP_Error.
        if (is_wp_error($response)) {
            $this->logger->error('SAP login connection failed', [
                'error' => $response->get_error_message(),
            ]);
            throw Connection_Exception::from_wp_error($response);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Check for authentication failure.
        if (401 === $status_code || 403 === $status_code) {
            $this->logger->error('SAP login failed: Invalid credentials');
            throw Authentication_Exception::invalid_credentials();
        }

        // Check for other errors.
        if ($status_code >= 400) {
            $error_message = $data['error']['message']['value'] ?? $data['error']['message'] ?? 'Login failed';
            $this->logger->error('SAP login failed', [
                'status' => $status_code,
                'message' => $error_message,
            ]);
            throw new Authentication_Exception($error_message);
        }

        // Extract cookies from response headers.
        $cookies = $this->extract_cookies($response);
        $b1session = $cookies['B1SESSION'] ?? '';
        $routeid = $cookies['ROUTEID'] ?? '';

        if (empty($b1session)) {
            $this->logger->error('SAP login failed: No B1SESSION cookie received');
            throw new Authentication_Exception(__('No session cookie received from SAP.', 'sap-woocommerce-sync'));
        }

        // Get session timeout from response.
        $session_timeout = $data['SessionTimeout'] ?? $this->session_timeout;

        // Cache session with 5-minute buffer before expiry.
        $cache_duration = max(1, ((int) $session_timeout - 5) * MINUTE_IN_SECONDS);

        $session = [
            'B1SESSION' => $b1session,
            'ROUTEID' => $routeid,
        ];

        set_transient($this->transient_key, $session, $cache_duration);

        $this->logger->info('SAP login successful', [
            'session_timeout' => $session_timeout,
            'cache_duration' => $cache_duration,
        ]);

        return $session;
    }

    /**
     * Extract cookies from WordPress HTTP response.
     *
     * @since 1.0.0
     * @param array $response The wp_remote response.
     * @return array<string, string> Extracted cookies.
     */
    private function extract_cookies(array $response): array
    {
        $cookies = [];

        // Get cookies from response.
        $response_cookies = wp_remote_retrieve_cookies($response);

        foreach ($response_cookies as $cookie) {
            if ($cookie instanceof \WP_Http_Cookie) {
                $cookies[$cookie->name] = $cookie->value;
            }
        }

        // If no cookies found via WP method, try parsing headers.
        if (empty($cookies)) {
            $headers = wp_remote_retrieve_headers($response);

            if ($headers instanceof \Requests_Utility_CaseInsensitiveDictionary || is_array($headers)) {
                $set_cookie = $headers['set-cookie'] ?? [];

                if (!is_array($set_cookie)) {
                    $set_cookie = [$set_cookie];
                }

                foreach ($set_cookie as $cookie_string) {
                    if (preg_match('/^([^=]+)=([^;]+)/', $cookie_string, $matches)) {
                        $cookies[$matches[1]] = $matches[2];
                    }
                }
            }
        }

        return $cookies;
    }

    /**
     * Perform logout from SAP Service Layer.
     *
     * @since 1.0.0
     * @return bool True on success, false on failure.
     */
    public function logout(): bool
    {
        $session = $this->get_cached_session();

        if (!$session) {
            return true; // No session to logout.
        }

        $logout_url = $this->base_url . '/b1s/v1/Logout';

        $response = wp_remote_post(
            $logout_url,
            [
                'timeout' => 10,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Cookie' => $this->format_cookies($session),
                ],
                'sslverify' => !defined('SAP_WC_SYNC_SKIP_SSL_VERIFY'),
            ]
        );

        // Clear cached session regardless of result.
        delete_transient($this->transient_key);

        if (is_wp_error($response)) {
            $this->logger->warning('SAP logout failed', [
                'error' => $response->get_error_message(),
            ]);
            return false;
        }

        $this->logger->debug('SAP logout successful');
        return true;
    }

    /**
     * Force refresh the session.
     *
     * @since 1.0.0
     * @return array{B1SESSION: string, ROUTEID: string} New session cookies.
     * @throws Authentication_Exception If login fails.
     * @throws Connection_Exception If connection fails.
     */
    public function refresh(): array
    {
        // Delete cached session.
        delete_transient($this->transient_key);

        // Create new session.
        return $this->login();
    }

    /**
     * Format cookies for HTTP header.
     *
     * @since 1.0.0
     * @param array<string, string> $cookies The cookies to format.
     * @return string Formatted cookie string.
     */
    public function format_cookies(array $cookies): string
    {
        $parts = [];

        foreach ($cookies as $name => $value) {
            $parts[] = $name . '=' . $value;
        }

        return implode('; ', $parts);
    }

    /**
     * Check if session is currently cached.
     *
     * @since 1.0.0
     * @return bool True if session is cached.
     */
    public function has_cached_session(): bool
    {
        return null !== $this->get_cached_session();
    }

    /**
     * Clear the cached session.
     *
     * @since 1.0.0
     * @return void
     */
    public function clear_session(): void
    {
        delete_transient($this->transient_key);
    }
}
