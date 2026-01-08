<?php
/**
 * Validation exception class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/Exceptions
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Rasandilikshana\SAP_WooCommerce_Sync\Exceptions;

/**
 * Exception for data validation failures.
 *
 * Thrown when data fails validation before being sent to SAP.
 *
 * @since 1.0.0
 */
class Validation_Exception extends SAP_Exception
{

    /**
     * Field-specific errors.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    protected array $field_errors = [];

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param string                $message      Exception message.
     * @param array<string, string> $field_errors Field-specific errors.
     * @param int                   $code         Exception code.
     * @param \Throwable|null       $previous     Previous exception.
     */
    public function __construct(
        string $message = '',
        array $field_errors = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $this->field_errors = $field_errors;

        parent::__construct($message, 'VALIDATION_ERROR', ['field_errors' => $field_errors], $code, $previous);
    }

    /**
     * Create from an array of errors.
     *
     * @since 1.0.0
     * @param array<string> $errors Array of error messages.
     * @return static
     */
    public static function from_errors(array $errors): static
    {
        $message = implode(' ', $errors);
        return new static($message);
    }

    /**
     * Create for missing required field.
     *
     * @since 1.0.0
     * @param string $field_name The field name.
     * @return static
     */
    public static function missing_field(string $field_name): static
    {
        return new static(
            sprintf(
                /* translators: %s: Field name */
                __('Required field "%s" is missing.', 'sap-woocommerce-sync'),
                $field_name
            ),
            [$field_name => __('This field is required.', 'sap-woocommerce-sync')]
        );
    }

    /**
     * Create for invalid field value.
     *
     * @since 1.0.0
     * @param string $field_name The field name.
     * @param string $reason     The reason it's invalid.
     * @return static
     */
    public static function invalid_field(string $field_name, string $reason): static
    {
        return new static(
            sprintf(
                /* translators: 1: Field name, 2: Reason */
                __('Invalid value for "%1$s": %2$s', 'sap-woocommerce-sync'),
                $field_name,
                $reason
            ),
            [$field_name => $reason]
        );
    }

    /**
     * Create for order validation failure.
     *
     * @since 1.0.0
     * @param int           $order_id The order ID.
     * @param array<string> $errors   The validation errors.
     * @return static
     */
    public static function order_invalid(int $order_id, array $errors): static
    {
        return new static(
            sprintf(
                /* translators: 1: Order ID, 2: Errors */
                __('Order #%1$d validation failed: %2$s', 'sap-woocommerce-sync'),
                $order_id,
                implode(', ', $errors)
            )
        );
    }

    /**
     * Create for product validation failure.
     *
     * @since 1.0.0
     * @param int           $product_id The product ID.
     * @param array<string> $errors     The validation errors.
     * @return static
     */
    public static function product_invalid(int $product_id, array $errors): static
    {
        return new static(
            sprintf(
                /* translators: 1: Product ID, 2: Errors */
                __('Product #%1$d validation failed: %2$s', 'sap-woocommerce-sync'),
                $product_id,
                implode(', ', $errors)
            )
        );
    }

    /**
     * Get field-specific errors.
     *
     * @since 1.0.0
     * @return array<string, string> The field errors.
     */
    public function get_field_errors(): array
    {
        return $this->field_errors;
    }

    /**
     * Check if a specific field has an error.
     *
     * @since 1.0.0
     * @param string $field_name The field name.
     * @return bool True if field has error.
     */
    public function has_field_error(string $field_name): bool
    {
        return isset($this->field_errors[$field_name]);
    }
}
