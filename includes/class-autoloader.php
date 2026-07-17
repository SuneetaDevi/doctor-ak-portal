<?php
/**
 * PSR-4 style autoloader for the Doctor AK Portal plugin.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Autoloader
 *
 * Maps namespaced class names to their file locations following the
 * WordPress "class-{class-name}.php" file naming convention.
 */
class Autoloader {

	/**
	 * Registers the autoloader with the SPL autoload stack.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Resolves a fully qualified class name to a file path and requires it.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	public static function autoload( $class_name ) {
		$prefix = __NAMESPACE__ . '\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( $prefix ) );
		$parts          = explode( '\\', $relative_class );
		$class_short    = array_pop( $parts );

		$sub_path = '';
		if ( ! empty( $parts ) ) {
			$sub_path = strtolower( implode( '/', $parts ) ) . '/';
		}

		$file_name = 'class-' . self::to_kebab_case( $class_short ) . '.php';
		$file_path = DOCTOR_AK_PORTAL_PATH . $sub_path . $file_name;

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}

	/**
	 * Converts a CamelCase class name to kebab-case.
	 *
	 * @param string $class_short Unqualified class name.
	 * @return string
	 */
	private static function to_kebab_case( $class_short ) {
		$class_short = str_replace( '_', '-', $class_short );
		$kebab       = preg_replace( '/(?<!^)(?<!-)[A-Z]/', '-$0', $class_short );

		return strtolower( $kebab );
	}
}
