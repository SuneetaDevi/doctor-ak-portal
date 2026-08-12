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
	const DB_VERSION = '1.3.0';

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
	 * Option name tracking the installed clinic-locations-table schema version.
	 *
	 * @var string
	 */
	const CLINIC_LOCATIONS_DB_VERSION_OPTION = 'dak_clinic_locations_db_version';

	/**
	 * Current clinic-locations table schema version.
	 *
	 * @var string
	 */
	const CLINIC_LOCATIONS_DB_VERSION = '1.0.0';

	/**
	 * Option name tracking the installed encounters-table schema version.
	 *
	 * @var string
	 */
	const ENCOUNTERS_DB_VERSION_OPTION = 'dak_encounters_db_version';

	/**
	 * Current encounters table schema version.
	 *
	 * @var string
	 */
	const ENCOUNTERS_DB_VERSION = '1.0.0';

	/**
	 * Option name tracking the installed medicines-table schema version.
	 *
	 * @var string
	 */
	const MEDICINES_DB_VERSION_OPTION = 'dak_medicines_db_version';

	/**
	 * Current medicines table schema version.
	 *
	 * @var string
	 */
	const MEDICINES_DB_VERSION = '1.0.0';

	/**
	 * Option name tracking the installed encounter-problems-table schema version.
	 *
	 * @var string
	 */
	const ENCOUNTER_PROBLEMS_DB_VERSION_OPTION = 'dak_encounter_problems_db_version';

	/**
	 * Current encounter-problems table schema version.
	 *
	 * @var string
	 */
	const ENCOUNTER_PROBLEMS_DB_VERSION = '1.0.0';

	/**
	 * Option name tracking the installed encounter-prescriptions-table schema version.
	 *
	 * @var string
	 */
	const ENCOUNTER_PRESCRIPTIONS_DB_VERSION_OPTION = 'dak_encounter_prescriptions_db_version';

	/**
	 * Current encounter-prescriptions table schema version.
	 *
	 * @var string
	 */
	const ENCOUNTER_PRESCRIPTIONS_DB_VERSION = '1.0.0';

	/**
	 * Option name tracking the installed encounter-bill-items-table schema version.
	 *
	 * @var string
	 */
	const ENCOUNTER_BILL_ITEMS_DB_VERSION_OPTION = 'dak_encounter_bill_items_db_version';

	/**
	 * Current encounter-bill-items table schema version.
	 *
	 * @var string
	 */
	const ENCOUNTER_BILL_ITEMS_DB_VERSION = '1.0.0';

	/**
	 * Option name tracking the installed encounter-reports-table schema version.
	 *
	 * @var string
	 */
	const ENCOUNTER_REPORTS_DB_VERSION_OPTION = 'dak_encounter_reports_db_version';

	/**
	 * Current encounter-reports table schema version.
	 *
	 * @var string
	 */
	const ENCOUNTER_REPORTS_DB_VERSION = '1.0.0';

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

		self::create_clinic_locations_table();
		update_option( self::CLINIC_LOCATIONS_DB_VERSION_OPTION, self::CLINIC_LOCATIONS_DB_VERSION );

		self::create_encounters_table();
		update_option( self::ENCOUNTERS_DB_VERSION_OPTION, self::ENCOUNTERS_DB_VERSION );

		self::create_medicines_table();
		update_option( self::MEDICINES_DB_VERSION_OPTION, self::MEDICINES_DB_VERSION );

		self::create_encounter_problems_table();
		update_option( self::ENCOUNTER_PROBLEMS_DB_VERSION_OPTION, self::ENCOUNTER_PROBLEMS_DB_VERSION );

		self::create_encounter_prescriptions_table();
		update_option( self::ENCOUNTER_PRESCRIPTIONS_DB_VERSION_OPTION, self::ENCOUNTER_PRESCRIPTIONS_DB_VERSION );

		self::create_encounter_bill_items_table();
		update_option( self::ENCOUNTER_BILL_ITEMS_DB_VERSION_OPTION, self::ENCOUNTER_BILL_ITEMS_DB_VERSION );

		self::create_encounter_reports_table();
		update_option( self::ENCOUNTER_REPORTS_DB_VERSION_OPTION, self::ENCOUNTER_REPORTS_DB_VERSION );

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
			&& self::CLINIC_LOCATIONS_DB_VERSION === get_option( self::CLINIC_LOCATIONS_DB_VERSION_OPTION )
			&& self::ENCOUNTERS_DB_VERSION === get_option( self::ENCOUNTERS_DB_VERSION_OPTION )
			&& self::MEDICINES_DB_VERSION === get_option( self::MEDICINES_DB_VERSION_OPTION )
			&& self::ENCOUNTER_PROBLEMS_DB_VERSION === get_option( self::ENCOUNTER_PROBLEMS_DB_VERSION_OPTION )
			&& self::ENCOUNTER_PRESCRIPTIONS_DB_VERSION === get_option( self::ENCOUNTER_PRESCRIPTIONS_DB_VERSION_OPTION )
			&& self::ENCOUNTER_BILL_ITEMS_DB_VERSION === get_option( self::ENCOUNTER_BILL_ITEMS_DB_VERSION_OPTION )
			&& self::ENCOUNTER_REPORTS_DB_VERSION === get_option( self::ENCOUNTER_REPORTS_DB_VERSION_OPTION )
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
			country VARCHAR(120) NOT NULL DEFAULT '',
			city VARCHAR(120) NOT NULL DEFAULT '',
			area VARCHAR(191) NOT NULL DEFAULT '',
			phone VARCHAR(30) NOT NULL DEFAULT '',
			contact_email VARCHAR(191) NOT NULL DEFAULT '',
			clinic_location_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			sessions LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY doctor_id (doctor_id),
			KEY clinic_location_id (clinic_location_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Runs dbDelta() against the clinic-locations table schema — the
	 * admin-managed master list of physical clinics (Country/City/Area/Name)
	 * doctors get aligned to from the "Doctor Sessions" admin form, see
	 * Clinic_Locations. Independent of the per-doctor `dak_clinics` table
	 * above, which still stores each doctor's own weekly session schedule at
	 * a clinic (referencing it via `clinic_location_id`).
	 *
	 * @return void
	 */
	private static function create_clinic_locations_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = Clinic_Locations::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			country VARCHAR(120) NOT NULL DEFAULT '',
			city VARCHAR(120) NOT NULL DEFAULT '',
			area VARCHAR(191) NOT NULL DEFAULT '',
			name VARCHAR(191) NOT NULL,
			address VARCHAR(255) NOT NULL DEFAULT '',
			phone VARCHAR(30) NOT NULL DEFAULT '',
			contact_email VARCHAR(191) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY country_city_area (country, city, area)
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
	 * Runs dbDelta() against the encounters table schema — one row per
	 * check-in, see Encounters.
	 *
	 * @return void
	 */
	private static function create_encounters_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = Encounters::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			appointment_id BIGINT UNSIGNED NOT NULL,
			doctor_id BIGINT UNSIGNED NOT NULL,
			patient_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			clinic_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'open',
			legacy_note TEXT NOT NULL,
			checked_in_at DATETIME NOT NULL,
			checked_in_by BIGINT UNSIGNED NOT NULL,
			closed_at DATETIME NULL,
			closed_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY appointment_id (appointment_id),
			KEY doctor_id (doctor_id),
			KEY patient_id (patient_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Runs dbDelta() against the medicines table schema — a doctor-scoped
	 * master list, see Medicines.
	 *
	 * @return void
	 */
	private static function create_medicines_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = Medicines::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			doctor_id BIGINT UNSIGNED NOT NULL,
			name VARCHAR(191) NOT NULL,
			default_dosage VARCHAR(191) NOT NULL DEFAULT '',
			active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY doctor_id (doctor_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Runs dbDelta() against the encounter-problems table schema, see
	 * Encounter_Problems.
	 *
	 * @return void
	 */
	private static function create_encounter_problems_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = Encounter_Problems::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			encounter_id BIGINT UNSIGNED NOT NULL,
			description VARCHAR(255) NOT NULL,
			notes TEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY encounter_id (encounter_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Runs dbDelta() against the encounter-prescriptions table schema, see
	 * Encounter_Prescriptions.
	 *
	 * @return void
	 */
	private static function create_encounter_prescriptions_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = Encounter_Prescriptions::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			encounter_id BIGINT UNSIGNED NOT NULL,
			medicine_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			medicine_name VARCHAR(191) NOT NULL,
			dosage VARCHAR(191) NOT NULL DEFAULT '',
			frequency VARCHAR(191) NOT NULL DEFAULT '',
			duration VARCHAR(191) NOT NULL DEFAULT '',
			instructions VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY encounter_id (encounter_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Runs dbDelta() against the encounter-bill-items table schema, see
	 * Encounter_Bill_Items.
	 *
	 * @return void
	 */
	private static function create_encounter_bill_items_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = Encounter_Bill_Items::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			encounter_id BIGINT UNSIGNED NOT NULL,
			description VARCHAR(255) NOT NULL,
			amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY encounter_id (encounter_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Runs dbDelta() against the encounter-reports table schema — uploaded
	 * lab/scan files attached to a clinical encounter (Media Library
	 * attachment IDs, not raw file storage — see Encounter_Reports).
	 *
	 * @return void
	 */
	private static function create_encounter_reports_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = Encounter_Reports::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			encounter_id BIGINT UNSIGNED NOT NULL,
			attachment_id BIGINT UNSIGNED NOT NULL,
			uploaded_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY encounter_id (encounter_id)
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

				// The old single-block-per-day schedule becomes that day's
				// "morning" period; afternoon/evening stay empty (doctors
				// can split it up later from the Clinics tab if they want a
				// break).
				$sessions[ $day ]['morning'] = array(
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
						'country'       => '',
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
						'country'       => '',
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
