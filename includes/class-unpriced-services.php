<?php
/**
 * Finds services with no price set and emails the administrators about
 * them — see Services::is_priced().
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Unpriced_Services
 *
 * A service saved without a price used to appear to patients as "Free".
 * Such services are now hidden from every patient-facing list and can't be
 * booked (that part is Services::is_priced(), applied where services are
 * read). This class tells the administrators: check() looks at every
 * active service of every public doctor, and emails once about each one
 * that has no price. It remembers what it has reported (OPTION), so the
 * same service isn't emailed again on every check; when a service gets a
 * price it's forgotten, so if it ever loses its price again it's reported
 * again.
 *
 * Runs on the hourly notifications cron (so services that were already
 * unpriced before this existed are found), and at the end of any request
 * that saved a service (queue()).
 */
class Unpriced_Services {

	/**
	 * Service IDs already reported: array( service_id => 1 ).
	 */
	const OPTION = 'dak_unpriced_services_reported';

	/**
	 * Whether a check is due at the end of this request.
	 *
	 * @var bool
	 */
	private static $queued = false;

	/**
	 * Runs a check at the end of this request (a service was saved).
	 *
	 * @return void
	 */
	public static function queue() {
		self::$queued = true;
	}

	/**
	 * Runs the queued check. Hooked to 'shutdown'.
	 *
	 * @return void
	 */
	public static function process_queue() {
		if ( self::$queued ) {
			self::$queued = false;
			self::check();
		}
	}

	/**
	 * Finds unpriced services and emails about the ones not reported yet.
	 * Hooked to the hourly notifications cron.
	 *
	 * @return array[] The newly reported services.
	 */
	public static function check() {
		$reported = get_option( self::OPTION, array() );
		$reported = is_array( $reported ) ? $reported : array();
		$current  = array();
		$new      = array();
		$public   = array();

		foreach ( Services::all_active_clinic_services() as $service ) {
			if ( Services::is_priced( $service ) ) {
				continue;
			}

			$doctor_id = (int) $service['doctor_id'];

			// A pending or deactivated doctor's services aren't shown anyway.
			if ( ! isset( $public[ $doctor_id ] ) ) {
				$public[ $doctor_id ] = Appointments::is_public_doctor( $doctor_id );
			}

			if ( ! $public[ $doctor_id ] ) {
				continue;
			}

			$current[ (int) $service['id'] ] = 1;

			if ( empty( $reported[ (int) $service['id'] ] ) ) {
				$new[] = $service;
			}
		}

		// Only what's still unpriced is remembered — a service that gets a
		// price (or is deleted) is dropped, and reported again if it ever
		// loses its price.
		update_option( self::OPTION, $current, false );

		if ( $new ) {
			Notifications::notify_unpriced_services( $new );
		}

		return $new;
	}
}
