<?php
/**
 * Base SAP exception class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/Exceptions
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Rasandilikshana\SAP_WooCommerce_Sync\Exceptions;

/**
 * Base exception for all SAP-related errors.
 *
 * @since 1.0.0
 */
class SAP_Exception extends \Exception
{

    /**
     * SAP error code.
     *
     * @since 1.0.0
     * @var string
     */
    protected string $sap_error_code = '';

    /**
     * Additional context data.
     *
     * @since 1.0.0
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param string          $message        Exception message.
     * @param string          $sap_error_code SAP error code.
     * @param array           $context        Additional context.
     * @param int             $code           Exception code.
     * @param \Throwable|null $previous       Previous exception.
     */
    public function __construct(
        string $message = '',
        string $sap_error_code = '',
        array $context = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $this->sap_error_code = $sap_error_code;
        $this->context = $context;

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the SAP error code.
     *
     * @since 1.0.0
     * @return string The SAP error code.
     */
    public function get_sap_error_code(): string
    {
        return $this->sap_error_code;
    }

    /**
     * Get the context data.
     *
     * @since 1.0.0
     * @return array<string, mixed> The context data.
     */
    public function get_context(): array
    {
        return $this->context;
    }

    /**
     * Create from SAP API error response.
     *
     * @since 1.0.0
     * @param array<string, mixed> $response The SAP error response.
     * @return static
     */
    public static function from_response(array $response): static
    {
        $error = $response['error'] ?? [];

        $message = $error['message']['value']
            ?? $error['message']
            ?? __('Unknown SAP error', 'sap-woocommerce-sync');

        $code = $error['code'] ?? '';

        return new static($message, $code, ['response' => $response]);
    }
}
