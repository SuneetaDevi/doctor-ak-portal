<?php
/**
 * Fired during plugin activation.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Activator
 *
 * Handles everything that needs to happen on plugin activation.
 */
class Activator {

	/**
	 * Runs on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		Db_Installer::install();
		Roles::add_roles();

		if ( ! wp_next_scheduled( Notifications::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', Notifications::CRON_HOOK );
		}

		flush_rewrite_rules();
	}
}
