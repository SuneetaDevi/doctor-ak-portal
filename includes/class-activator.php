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
		Page_Installer::maybe_install();

		if ( ! wp_next_scheduled( Notifications::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', Notifications::CRON_HOOK );
		}

		// Registered explicitly here (rather than relying on the plugin's
		// normal 'cron_schedules' hook registration having already run) so
		// this custom interval is guaranteed known before we schedule
		// against it.
		add_filter( 'cron_schedules', array( 'DoctorAKPortal\\Includes\\Notifications', 'add_cron_interval' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- the interval itself is defined once in Notifications::add_cron_interval(), not here.

		if ( ! wp_next_scheduled( Notifications::VIDEO_LINK_CRON_HOOK ) ) {
			wp_schedule_event( time(), 'doctor_ak_five_minutes', Notifications::VIDEO_LINK_CRON_HOOK );
		}

		flush_rewrite_rules();
	}
}
