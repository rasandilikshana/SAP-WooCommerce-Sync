<?php
/**
 * Authentication exception class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/Exceptions
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Rasandilikshana\SAP_WooCommerce_Sync\Exceptions;

/**
 * Exception for SAP authentication failures.
 *
 * Thrown when login fails or session expires.
 *
 * @since 1.0.0
 */
class Authentication_Exception extends SAP_Exception
{

    /**
     * Create for invalid credentials.
     *
     * @since 1.0.0
     * @return static
     */
    public static function invalid_credentials(): static
    {
        return new static(
            __('Invalid SAP credentials. Please check your username and password.', 'sap-woocommerce-sync'),
            'INVALID_CREDENTIALS'
        );
    }

    /**
     * Create for expired session.
     *
     * @since 1.0.0
     * @return static
     */
    public static function session_expired(): static
    {
        return new static(
            __('SAP session has expired. Please re-authenticate.', 'sap-woocommerce-sync'),
            'SESSION_EXPIRED'
        );
    }

    /**
     * Create for missing session.
     *
     * @since 1.0.0
     * @return static
     */
    public static function no_session(): static
    {
        return new static(
            __('No active SAP session. Please configure SAP credentials.', 'sap-woocommerce-sync'),
            'NO_SESSION'
        );
    }

    /**
     * Create for company database not found.
     *
     * @since 1.0.0
     * @param string $company_db The company database name.
     * @return static
     */
    public static function company_not_found(string $company_db): static
    {
        return new static(
            sprintf(
                /* translators: %s: Company database name */
                __('SAP company database "%s" not found.', 'sap-woocommerce-sync'),
                $company_db
            ),
            'COMPANY_NOT_FOUND',
            ['company_db' => $company_db]
        );
    }

    /**
     * Create for license error.
     *
     * @since 1.0.0
     * @return static
     */
    public static function license_error(): static
    {
        return new static(
            __('SAP license error. No available user licenses.', 'sap-woocommerce-sync'),
            'LICENSE_ERROR'
        );
    }

    /**
     * Check if this exception indicates we should retry after re-authentication.
     *
     * @since 1.0.0
     * @return bool True if retry is possible.
     */
    public function is_retryable(): bool
    {
        return in_array(
            $this->sap_error_code,
            ['SESSION_EXPIRED', 'NO_SESSION'],
            true
        );
    }
}
