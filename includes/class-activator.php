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

		// Activation runs after 'init', so the SEO page types (/doctor/,
		// /service/, /clinic/) aren't registered yet in this request. Flushing
		// without them saved URL rules that left them out — every such page
		// then 404'd and bounced to its directory. Register them first.
		foreach ( Seo_Pages::classes() as $seo_pages ) {
			$seo_pages::register_post_type();
		}

		flush_rewrite_rules();
	}
}
