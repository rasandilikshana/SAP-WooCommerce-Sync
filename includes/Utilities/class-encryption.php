<?php
/**
 * Encryption utility class.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes/Utilities
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Rasandilikshana\SAP_WooCommerce_Sync\Utilities;

/**
 * Handles encryption and decryption of sensitive data.
 *
 * Uses OpenSSL for encryption with AES-256-CBC cipher.
 * Falls back to base64 encoding if OpenSSL is not available.
 *
 * @since 1.0.0
 */
class Encryption
{

    /**
     * The cipher method to use.
     *
     * @since 1.0.0
     * @var string
     */
    private const CIPHER_METHOD = 'aes-256-cbc';

    /**
     * The encryption key.
     *
     * @since 1.0.0
     * @var string
     */
    private string $key;

    /**
     * Whether OpenSSL is available.
     *
     * @since 1.0.0
     * @var bool
     */
    private bool $openssl_available;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->openssl_available = extension_loaded('openssl');
        $this->key = $this->get_encryption_key();
    }

    /**
     * Get the encryption key.
     *
     * Uses LOGGED_IN_KEY if available, otherwise generates a key.
     *
     * @since 1.0.0
     * @return string The encryption key.
     */
    private function get_encryption_key(): string
    {
        // Try to use WordPress salt.
        if (defined('LOGGED_IN_KEY') && LOGGED_IN_KEY) {
            return hash('sha256', LOGGED_IN_KEY . 'sap_wc_sync', true);
        }

        // Fallback to a stored key.
        $stored_key = get_option('sap_wc_sync_encryption_key');

        if (!$stored_key) {
            $stored_key = wp_generate_password(64, true, true);
            update_option('sap_wc_sync_encryption_key', $stored_key);
        }

        return hash('sha256', $stored_key, true);
    }

    /**
     * Encrypt a string.
     *
     * @since 1.0.0
     * @param string $plaintext The string to encrypt.
     * @return string The encrypted string (base64 encoded).
     */
    public function encrypt(string $plaintext): string
    {
        if (empty($plaintext)) {
            return '';
        }

        if (!$this->openssl_available) {
            // Fallback: simple obfuscation (not secure, but better than plaintext).
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
            return 'fallback:' . base64_encode($plaintext);
        }

        // Generate random IV.
        $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv = openssl_random_pseudo_bytes($iv_length);

        // Encrypt.
        $encrypted = openssl_encrypt(
            $plaintext,
            self::CIPHER_METHOD,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if (false === $encrypted) {
            return '';
        }

        // Combine IV and encrypted data, then base64 encode.
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a string.
     *
     * @since 1.0.0
     * @param string $encrypted The encrypted string (base64 encoded).
     * @return string The decrypted string.
     */
    public function decrypt(string $encrypted): string
    {
        if (empty($encrypted)) {
            return '';
        }

        // Check for fallback encoding.
        if (str_starts_with($encrypted, 'fallback:')) {
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
            return base64_decode(substr($encrypted, 9));
        }

        if (!$this->openssl_available) {
            return '';
        }

        // Decode base64.
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        $data = base64_decode($encrypted);

        if (false === $data) {
            return '';
        }

        // Extract IV.
        $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);

        // Decrypt.
        $decrypted = openssl_decrypt(
            $encrypted,
            self::CIPHER_METHOD,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return false === $decrypted ? '' : $decrypted;
    }

    /**
     * Check if a string is encrypted by this class.
     *
     * @since 1.0.0
     * @param string $value The string to check.
     * @return bool True if encrypted, false otherwise.
     */
    public function is_encrypted(string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        // Check for fallback prefix.
        if (str_starts_with($value, 'fallback:')) {
            return true;
        }

        // Try to decode base64 and check if it looks like our format.
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
        $decoded = base64_decode($value, true);

        if (false === $decoded) {
            return false;
        }

        $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);

        return strlen($decoded) > $iv_length;
    }
}
