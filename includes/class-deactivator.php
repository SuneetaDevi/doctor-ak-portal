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
		foreach ( Seo_Pages::classes() as $seo_pages ) {
			wp_clear_scheduled_hook( $seo_pages::cron_hook() );
			// Re-run the one-time setup (URLs, first sync) on the next activation.
			delete_option( $seo_pages::version_option() );
		}
		// Pending review requests keep their due time on the appointment, so
		// the hourly sweep picks them up again after reactivation.
		wp_unschedule_hook( Review_Requests::CRON_HOOK );
		// Queued CRM emails stay queued; the CRM screen or the next
		// scheduled run sends them after reactivation.
		wp_unschedule_hook( Crm_Email::QUEUE_HOOK );
		flush_rewrite_rules();
	}
}
