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
	const DB_VERSION = '1.4.0';

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
	const SERVICES_DB_VERSION = '1.1.0';

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
	 * Option name tracking the installed revenue-ledger-table schema version.
	 *
	 * @var string
	 */
	const REVENUE_LEDGER_DB_VERSION_OPTION = 'dak_revenue_ledger_db_version';

	/**
	 * Current revenue-ledger table schema version.
	 *
	 * @var string
	 */
	const REVENUE_LEDGER_DB_VERSION = '1.0.0';

	/**
	 * Option name tracking the installed revenue-settlements-table schema version.
	 *
	 * @var string
	 */
	const REVENUE_SETTLEMENTS_DB_VERSION_OPTION = 'dak_revenue_settlements_db_version';

	/**
	 * Current revenue-settlements table schema version.
	 *
	 * @var string
	 */
	const REVENUE_SETTLEMENTS_DB_VERSION = '1.0.0';

	/**
	 * Option name guarding the one-time legacy-data migration so it never
	 * runs more than once.
	 *
	 * @var string
	 */
	const MIGRATION_OPTION = 'dak_clinics_migrated_legacy_data';

	/**
	 * Option name guarding the one-time revenue-ledger backfill for
	 * already-paid appointments that predate the ledger's existence — see
	 * backfill_revenue_ledger().
	 *
	 * @var string
	 */
	const REVENUE_LEDGER_BACKFILL_OPTION = 'dak_revenue_ledger_backfilled';

	/**
	 * Option name guarding the one-time common-medicines seed so it never
	 * re-inserts rows a site has since edited/deleted.
	 *
	 * @var string
	 */
	const MEDICINES_SEED_OPTION = 'dak_common_medicines_seeded';

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

		self::create_revenue_ledger_table();
		update_option( self::REVENUE_LEDGER_DB_VERSION_OPTION, self::REVENUE_LEDGER_DB_VERSION );

		self::create_revenue_settlements_table();
		update_option( self::REVENUE_SETTLEMENTS_DB_VERSION_OPTION, self::REVENUE_SETTLEMENTS_DB_VERSION );

		if ( ! get_option( self::MIGRATION_OPTION ) ) {
			self::migrate_legacy_data();
			update_option( self::MIGRATION_OPTION, 'yes' );
		}

		if ( ! get_option( self::MEDICINES_SEED_OPTION ) ) {
			self::seed_common_medicines();
			update_option( self::MEDICINES_SEED_OPTION, 'yes' );
		}

		if ( ! get_option( self::REVENUE_LEDGER_BACKFILL_OPTION ) ) {
			self::backfill_revenue_ledger();
			update_option( self::REVENUE_LEDGER_BACKFILL_OPTION, 'yes' );
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
			&& self::REVENUE_LEDGER_DB_VERSION === get_option( self::REVENUE_LEDGER_DB_VERSION_OPTION )
			&& self::REVENUE_SETTLEMENTS_DB_VERSION === get_option( self::REVENUE_SETTLEMENTS_DB_VERSION_OPTION )
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
			doctor_share_percent DECIMAL(5,2) NULL DEFAULT NULL,
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
			description TEXT NULL,
			image_id BIGINT UNSIGNED NULL DEFAULT NULL,
			clinic_location_ids TEXT NULL,
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
	 * Seeds a starting set of common medicines (doctor_id = 0 — visible to
	 * every doctor alongside their own, see Medicines::get_for_doctor()) so
	 * the prescription autocomplete has useful suggestions before any doctor
	 * has typed anything. Runs once, guarded by MEDICINES_SEED_OPTION — a
	 * site is free to edit/deactivate/delete these afterward without them
	 * reappearing.
	 *
	 * @return void
	 */
	private static function seed_common_medicines() {
		global $wpdb;

		$table_name = Medicines::table_name();
		$now        = current_time( 'mysql' );

		$common_medicines = array(
			'Paracetamol',
			'Ibuprofen',
			'Amoxicillin',
			'Azithromycin',
			'Ciprofloxacin',
			'Metronidazole',
			'Omeprazole',
			'Pantoprazole',
			'Ranitidine',
			'Cetirizine',
			'Loratadine',
			'Metformin',
			'Amlodipine',
			'Atorvastatin',
			'Losartan',
			'Aspirin',
			'Diclofenac',
			'Domperidone',
			'Ondansetron',
			'Prednisolone',
			'Salbutamol Inhaler',
			'Multivitamin',
			'ORS (Oral Rehydration Salts)',
			'Vitamin D3',
			'Calcium Carbonate',
		);

		foreach ( $common_medicines as $medicine_name ) {
			$wpdb->insert(
				$table_name,
				array(
					'doctor_id'      => 0,
					'name'           => $medicine_name,
					'default_dosage' => '',
					'active'         => 1,
					'created_at'     => $now,
					'updated_at'     => $now,
				),
				array( '%d', '%s', '%s', '%d', '%s', '%s' )
			);
		}
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
	 * Runs dbDelta() against the revenue-ledger table schema — the
	 * doctor+clinic-wise financial ledger (see Revenue_Ledger). One row per
	 * posted transaction (a paid appointment, or a refund reversal),
	 * snapshotting the gross amount, split percentage, and each side's
	 * share at the moment it was posted, so later changes to a doctor's or
	 * clinic's split never rewrite history.
	 *
	 * @return void
	 */
	private static function create_revenue_ledger_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = Revenue_Ledger::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			doctor_id BIGINT UNSIGNED NOT NULL,
			clinic_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			appointment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			service_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			transaction_type VARCHAR(30) NOT NULL,
			direction VARCHAR(10) NOT NULL DEFAULT 'credit',
			gross_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			platform_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			share_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
			doctor_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			clinic_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			net_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			description VARCHAR(255) NOT NULL DEFAULT '',
			reference VARCHAR(100) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'posted',
			is_legacy TINYINT(1) NOT NULL DEFAULT 0,
			settlement_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			transaction_date DATE NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY doctor_id (doctor_id),
			KEY clinic_id (clinic_id),
			KEY appointment_id (appointment_id),
			KEY transaction_type (transaction_type),
			KEY settlement_id (settlement_id),
			KEY transaction_date (transaction_date)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Runs dbDelta() against the revenue-settlements table schema — one row
	 * per doctor per reviewed period (see Settlement_Manager). Creating a
	 * settlement stamps every Revenue_Ledger row it covers with this row's
	 * id, so a later settlement (or the live "current outstanding" view)
	 * never double-counts already-settled entries.
	 *
	 * @return void
	 */
	private static function create_revenue_settlements_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = Settlement_Manager::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			doctor_id BIGINT UNSIGNED NOT NULL,
			period_start DATE NOT NULL,
			period_end DATE NOT NULL,
			video_earnings DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			clinic_obligations DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			platform_fees DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			adjustments DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			opening_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			closing_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			settlement_direction VARCHAR(30) NOT NULL DEFAULT 'settled',
			settlement_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			settled_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			settled_at DATETIME NULL DEFAULT NULL,
			settled_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY doctor_id (doctor_id),
			KEY settlement_status (settlement_status)
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

	/**
	 * One-time migration: posts a Revenue_Ledger entry for every appointment
	 * that was already `payment_status = paid` before the ledger existed —
	 * otherwise those appointments would simply never appear in the new
	 * doctor+clinic-wise ledger/settlement screens at all. Computed with
	 * each doctor's/clinic's CURRENT split settings (there was never a
	 * historical snapshot to recover — see Revenue_Calculator), and every
	 * backfilled row is flagged `is_legacy = 1` so it's clearly
	 * distinguishable from a real-time entry (which snapshots the split
	 * that was actually in effect the moment the payment was posted).
	 *
	 * Safe to re-run: Revenue_Ledger::post_for_appointment() itself is
	 * idempotent (skips an appointment that already has a non-reversed
	 * ledger row of that transaction type), on top of this whole method
	 * only ever running once per site via REVENUE_LEDGER_BACKFILL_OPTION.
	 *
	 * @return void
	 */
	private static function backfill_revenue_ledger() {
		$appointments = Appointments::all_for_admin(
			array(
				'payment_status' => Appointments::PAYMENT_STATUS_PAID,
				'number'         => 100000,
			)
		);

		foreach ( $appointments as $appointment ) {
			Revenue_Ledger::post_for_appointment( $appointment['id'], true );
		}
	}
}
