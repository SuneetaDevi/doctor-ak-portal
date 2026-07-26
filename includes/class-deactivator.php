<?php
/**
 * Fired during plugin deactivation.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivator
 *
 * Handles everything that needs to happen on plugin deactivation.
 * Deactivation intentionally does not remove roles, users or data —
 * that is reserved for uninstall.php so deactivating never causes data loss.
 */
class Deactivator {

	/**
	 * Runs on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( Notifications::CRON_HOOK );
		wp_clear_scheduled_hook( Notifications::VIDEO_LINK_CRON_HOOK );
		flush_rewrite_rules();
	}
}
