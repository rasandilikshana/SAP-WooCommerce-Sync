<?php
/**
 * Custom PSR-4 autoloader fallback.
 *
 * @package    SAP_WooCommerce_Sync
 * @subpackage SAP_WooCommerce_Sync/includes
 * @since      1.0.0
 */

declare(strict_types=1);

namespace Rasandilikshana\SAP_WooCommerce_Sync;

/**
 * PSR-4 Autoloader for plugin classes.
 *
 * This autoloader is used as a fallback when Composer is not available.
 * It follows PSR-4 autoloading standards.
 *
 * @since 1.0.0
 */
class Autoloader
{

    /**
     * The namespace prefix for this autoloader.
     *
     * @since 1.0.0
     * @var string
     */
    private const NAMESPACE_PREFIX = 'Rasandilikshana\\SAP_WooCommerce_Sync\\';

    /**
     * The base directory for the namespace prefix.
     *
     * @since 1.0.0
     * @var string
     */
    private static string $base_dir;

    /**
     * Register the autoloader with SPL autoload stack.
     *
     * @since 1.0.0
     * @return void
     */
    public static function register(): void
    {
        self::$base_dir = SAP_WC_SYNC_DIR . 'includes/';
        spl_autoload_register([self::class, 'autoload']);
    }

    /**
     * Autoload a class.
     *
     * @since 1.0.0
     * @param string $class The fully-qualified class name.
     * @return void
     */
    public static function autoload(string $class): void
    {
        // Check if the class uses our namespace prefix.
        $prefix_length = strlen(self::NAMESPACE_PREFIX);

        if (strncmp(self::NAMESPACE_PREFIX, $class, $prefix_length) !== 0) {
            return;
        }

        // Get the relative class name.
        $relative_class = substr($class, $prefix_length);

        // Convert namespace separators to directory separators.
        $relative_path = str_replace('\\', '/', $relative_class);

        // Convert class name to WordPress file naming convention.
        $file_name = self::class_to_file_name($relative_path);

        // Build the file path.
        $file = self::$base_dir . $file_name;

        // If the file exists, require it.
        if (file_exists($file)) {
            require_once $file;
        }
    }

    /**
     * Convert a class name to WordPress file naming convention.
     *
     * @since 1.0.0
     * @param string $relative_path The relative class path with forward slashes.
     * @return string The file path in WordPress naming convention.
     */
    private static function class_to_file_name(string $relative_path): string
    {
        $parts = explode('/', $relative_path);
        $class_name = array_pop($parts);

        // Convert class name: ClassName → class-classname.php
        // Handle underscores: Class_Name → class-class-name.php
        $file_name = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $class_name));
        $file_name = strtolower(str_replace('_', '-', $file_name));
        $file_name = 'class-' . $file_name . '.php';

        // Rebuild the path with directory structure.
        if (!empty($parts)) {
            return implode('/', $parts) . '/' . $file_name;
        }

        return $file_name;
    }
}
