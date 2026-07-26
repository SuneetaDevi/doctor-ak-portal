<?php
/**
 * Creates and upgrades the plugin's custom database tables.
 *
 * @package DoctorAKPortal\Includes
 */

namespace DoctorAKPortal\Includes;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Db_Installer
 *
 * Owns the `{$prefix}dak_clinics` table (one row per clinic a doctor has
 * added, with that clinic's weekly session schedule stored as a JSON
 * column — see Clinics::save_row()). Also runs the one-time migration that
 * turns an existing doctor's old single clinic_location/availability/
 * video_consultation meta into their first clinic row(s), so upgrading
 * doesn't silently blank out doctors who registered before this table
 * existed.
 */
class Db_Installer {

	/**
	 * Option name tracking the installed schema version, so future schema
	 * changes can re-run dbDelta() on upgrade.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'dak_clinics_db_version';

	/**
	 * Current schema version.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.1.0';

	/**
	 * Option name tracking the installed services-table schema version.
	 *
	 * @var string
	 */
	const SERVICES_DB_VERSION_OPTION = 'dak_services_db_version';

	/**
	 * Current services table schema version.
	 *
	 * @var string
	 */
	const SERVICES_DB_VERSION = '1.0.0';

	/**
	 * Option name tracking the installed notifications-table schema version.
	 *
	 * @var string
	 */
	const NOTIFICATIONS_DB_VERSION_OPTION = 'dak_notifications_db_version';

	/**
	 * Current notifications table schema version.
	 *
	 * @var string
	 */
	const NOTIFICATIONS_DB_VERSION = '1.0.0';

	/**
	 * Option name guarding the one-time legacy-data migration so it never
	 * runs more than once.
	 *
	 * @var string
	 */
	const MIGRATION_OPTION = 'dak_clinics_migrated_legacy_data';

	/**
	 * Creates/updates the table and runs the legacy-data migration.
	 *
	 * Safe to call on every activation: dbDelta() only applies schema
	 * differences, and the migration is guarded by an option flag.
	 *
	 * @return void
	 */
	public static function install() {
		self::create_table();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

		self::create_services_table();
		update_option( self::SERVICES_DB_VERSION_OPTION, self::SERVICES_DB_VERSION );

		self::create_notifications_table();
		update_option( self::NOTIFICATIONS_DB_VERSION_OPTION, self::NOTIFICATIONS_DB_VERSION );

		if ( ! get_option( self::MIGRATION_OPTION ) ) {
			self::migrate_legacy_data();
			update_option( self::MIGRATION_OPTION, 'yes' );
		}
	}

	/**
	 * Safety net for sites where the plugin was already active when this
	 * table was introduced: activation hooks only fire on (re)activation, so
	 * an in-place file update (no deactivate/reactivate) would otherwise
	 * leave the site without the table forever. Cheap to check on every
	 * request (a single autoloaded option comparison); only runs the real
	 * install when the stored version doesn't match.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( self::DB_VERSION === get_option( self::DB_VERSION_OPTION )
			&& self::SERVICES_DB_VERSION === get_option( self::SERVICES_DB_VERSION_OPTION )
			&& self::NOTIFICATIONS_DB_VERSION === get_option( self::NOTIFICATIONS_DB_VERSION_OPTION )
		) {
			return;
		}

		self::install();
	}

	/**
	 * Runs dbDelta() against the clinics table schema.
	 *
	 * @return void
	 */
	private static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = Clinics::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			doctor_id BIGINT UNSIGNED NOT NULL,
			type VARCHAR(20) NOT NULL DEFAULT 'physical',
			name VARCHAR(191) NOT NULL,
			address VARCHAR(255) NOT NULL DEFAULT '',
			city VARCHAR(120) NOT NULL DEFAULT '',
			area VARCHAR(191) NOT NULL DEFAULT '',
			phone VARCHAR(30) NOT NULL DEFAULT '',
			contact_email VARCHAR(191) NOT NULL DEFAULT '',
			sessions LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY doctor_id (doctor_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Runs dbDelta() against the services table schema.
	 *
	 * @return void
	 */
	private static function create_services_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = Services::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			doctor_id BIGINT UNSIGNED NOT NULL,
			type VARCHAR(20) NOT NULL DEFAULT 'clinic',
			name VARCHAR(191) NOT NULL,
			category VARCHAR(191) NOT NULL DEFAULT '',
			charge DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			duration_minutes INT UNSIGNED NOT NULL DEFAULT 0,
			active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY doctor_id (doctor_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Runs dbDelta() against the notifications table schema.
	 *
	 * @return void
	 */
	private static function create_notifications_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = Notification_Center::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			recipient_id BIGINT UNSIGNED NOT NULL,
			type VARCHAR(20) NOT NULL DEFAULT 'booked',
			message VARCHAR(255) NOT NULL DEFAULT '',
			appointment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			is_read TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY recipient_read (recipient_id, is_read)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * One-time migration: for every doctor with legacy meta and no clinic
	 * rows yet, creates a 'physical' clinic from their old clinic_location +
	 * availability, and a 'video' clinic ("Online Consultation") if they had
	 * video_consultation enabled — carrying the same weekly schedule since
	 * the old model didn't distinguish clinic vs. video hours.
	 *
	 * @return void
	 */
	private static function migrate_legacy_data() {
		$doctors = get_users( array( 'role' => Roles::DOCTOR_ROLE ) );

		foreach ( $doctors as $doctor ) {
			if ( ! empty( Clinics::get_for_doctor( $doctor->ID ) ) ) {
				continue;
			}

			$clinic_location    = get_user_meta( $doctor->ID, 'doctor_ak_clinic_location', true );
			$video_consultation = 'yes' === get_user_meta( $doctor->ID, 'doctor_ak_video_consultation', true );

			if ( '' === trim( (string) $clinic_location ) && ! $video_consultation ) {
				continue;
			}

			$legacy_schedule = Availability::get( $doctor->ID );
			$sessions        = Clinics::empty_sessions();

			foreach ( $legacy_schedule as $day => $entry ) {
				if ( ! isset( $sessions[ $day ] ) || empty( $entry['enabled'] ) ) {
					continue;
				}

				$sessions[ $day ] = array(
					'enabled'               => true,
					'start'                 => $entry['start'],
					'end'                   => $entry['end'],
					'slot_duration_minutes' => 30,
				);
			}

			if ( '' !== trim( (string) $clinic_location ) ) {
				Clinics::create(
					$doctor->ID,
					array(
						'type'          => 'physical',
						'name'          => $clinic_location,
						'address'       => $clinic_location,
						'city'          => '',
						'area'          => '',
						'phone'         => '',
						'contact_email' => '',
					),
					$sessions,
					null
				);
			}

			if ( $video_consultation ) {
				Clinics::create(
					$doctor->ID,
					array(
						'type'          => 'video',
						'name'          => __( 'Online Consultation', 'doctor-ak-portal' ),
						'address'       => '',
						'city'          => '',
						'area'          => '',
						'phone'         => '',
						'contact_email' => '',
					),
					$sessions,
					null
				);
			}
		}
	}
}
