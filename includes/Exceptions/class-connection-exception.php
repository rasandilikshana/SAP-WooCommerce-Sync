<?php
/**
 * Connection exception class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/Exceptions
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Jehankandy\SAP_WooCommerce_Sync\Exceptions;

/**
 * Exception for SAP connection failures.
 *
 * Thrown when the plugin cannot establish a connection to SAP Service Layer.
 *
 * @since 1.0.0
 */
class Connection_Exception extends SAP_Exception
{

    /**
     * HTTP status code if available.
     *
     * @since 1.0.0
     * @var int
     */
    protected int $http_status = 0;

    /**
     * Create from WP_Error.
     *
     * @since 1.0.0
     * @param \WP_Error $error The WordPress error.
     * @return static
     */
    public static function from_wp_error(\WP_Error $error): static
    {
        $message = $error->get_error_message();
        $code = $error->get_error_code();

        return new static(
            $message,
            (string) $code,
            ['wp_error' => $error]
        );
    }

    /**
     * Create for timeout error.
     *
     * @since 1.0.0
     * @param int $timeout_seconds The timeout duration.
     * @return static
     */
    public static function timeout(int $timeout_seconds): static
    {
        return new static(
            sprintf(
                /* translators: %d: Timeout duration in seconds */
                __('SAP connection timed out after %d seconds.', 'sap-woocommerce-sync'),
                $timeout_seconds
            ),
            'TIMEOUT',
            ['timeout' => $timeout_seconds]
        );
    }

    /**
     * Create for SSL error.
     *
     * @since 1.0.0
     * @param string $details SSL error details.
     * @return static
     */
    public static function ssl_error(string $details): static
    {
        return new static(
            __('SSL certificate verification failed.', 'sap-woocommerce-sync') . ' ' . $details,
            'SSL_ERROR',
            ['details' => $details]
        );
    }

    /**
     * Create for unreachable server.
     *
     * @since 1.0.0
     * @param string $url The SAP URL.
     * @return static
     */
    public static function unreachable(string $url): static
    {
        return new static(
            sprintf(
                /* translators: %s: SAP URL */
                __('Cannot reach SAP server at %s.', 'sap-woocommerce-sync'),
                $url
            ),
            'UNREACHABLE',
            ['url' => $url]
        );
    }

    /**
     * Set HTTP status code.
     *
     * @since 1.0.0
     * @param int $status The HTTP status code.
     * @return static
     */
    public function with_http_status(int $status): static
    {
        $this->http_status = $status;
        return $this;
    }

    /**
     * Get HTTP status code.
     *
     * @since 1.0.0
     * @return int The HTTP status code.
     */
    public function get_http_status(): int
    {
        return $this->http_status;
    }
}
