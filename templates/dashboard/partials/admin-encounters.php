<?php
/**
 * Template: "Encounters" admin section — every clinical encounter (opened
 * by checking a patient in, closed once their visit is documented and
 * billed — see the Encounters class), across every doctor/clinic, newest
 * first. Each row opens the full Encounter detail screen (Problems,
 * Prescription, Bill, Documents & Checkout).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $encounters       Rows from Encounters::all_flat_for_admin(), each with an added 'appointment' sub-array (see Appointments::notification_data()), 'bill_pdf_url' (see Encounter_Handler::bill_pdf_download_url()), and 'prescription_pdf_url' (see Encounter_Handler::prescription_pdf_download_url()).
 * @var string $encounters_url   Unfiltered URL of this section, for the filter form and "Clear" link.
 * @var string $encounter_url    Base URL of the Encounter detail screen — each row links here with `&encounter_id=X`.
 * @var array  $doctors          Doctor users { ID, display_name }, for the filter's Doctor select.
 * @var string $filtered_patient Name of the patient being filtered to (via the Patient directory's "Encounter" action), or '' if unfiltered.
 * @var array  $filters          Active filter values: date_from, date_to, doctor_id, patient_id, status, search, clinic (Clinic_Locations ID), visit ('clinic'|'video'), service (procedure name), payment_status, sort ('desc'|'asc').
 * @var array  $clinic_options   Clinic_Locations ID => name, for the Clinic filter.
 * @var array  $service_options  Procedure (service) names, for the Procedure filter.
 * @var array  $payment_status_options Payment status slug => label.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_status_labels = array(
	\DoctorAKPortal\Includes\Encounters::STATUS_OPEN   => __( 'Open', 'doctor-ak-portal' ),
	\DoctorAKPortal\Includes\Encounters::STATUS_CLOSED => __( 'Closed', 'doctor-ak-portal' ),
);

$dak_visit_options = array(
	'clinic' => __( 'Clinic visit', 'doctor-ak-portal' ),
	'video'  => __( 'Video consultation', 'doctor-ak-portal' ),
);

$dak_format_date = function ( $ymd ) {
	$ts = strtotime( (string) $ymd );

	return false !== $ts ? date_i18n( 'd M Y', $ts ) : (string) $ymd;
};

// ---- Filter state: live filters, chips, Clear all (same as Appointments). --
$dak_live_attrs = ' data-live-filter="doctor_ak_admin_encounters_filter" data-live-filter-target="#dak-admin-section-content" data-live-filter-nonce="dakAdminUsers"';

$dak_current_query = array(
	'search'         => $filters['search'],
	'status'         => $filters['status'],
	'doctor_id'      => $filters['doctor_id'] > 0 ? $filters['doctor_id'] : '',
	'visit'          => $filters['visit'],
	'clinic'         => $filters['clinic'] > 0 ? $filters['clinic'] : '',
	'service'        => $filters['service'],
	'date_from'      => $filters['date_from'],
	'date_to'        => $filters['date_to'],
	'payment_status' => $filters['payment_status'],
	'sort'           => 'asc' === $filters['sort'] ? 'asc' : '',
	'patient_id'     => $filters['patient_id'] > 0 ? $filters['patient_id'] : '',
);

// The current list's link with one filter removed — each chip's target.
$dak_without = function ( $key ) use ( $dak_current_query, $encounters_url ) {
	$query = $dak_current_query;
	unset( $query[ $key ] );

	return add_query_arg( array_filter( $query, 'strlen' ), $encounters_url );
};

$dak_doctor_names = array();

foreach ( $doctors as $dak_doctor_option ) {
	$dak_doctor_names[ (int) $dak_doctor_option->ID ] = $dak_doctor_option->display_name;
}

$dak_chips = array();

if ( '' !== $filters['search'] ) {
	/* translators: %s: search text. */
	$dak_chips[] = array( sprintf( __( 'Search: “%s”', 'doctor-ak-portal' ), $filters['search'] ), $dak_without( 'search' ) );
}

if ( '' !== $filters['status'] ) {
	/* translators: %s: "Open" or "Closed". */
	$dak_chips[] = array( sprintf( __( 'Status: %s', 'doctor-ak-portal' ), $dak_status_labels[ $filters['status'] ] ), $dak_without( 'status' ) );
}

if ( $filters['doctor_id'] > 0 ) {
	$dak_chips[] = array(
		/* translators: %s: doctor name. */
		sprintf( __( 'Doctor: %s', 'doctor-ak-portal' ), isset( $dak_doctor_names[ (int) $filters['doctor_id'] ] ) ? $dak_doctor_names[ (int) $filters['doctor_id'] ] : '#' . (int) $filters['doctor_id'] ),
		$dak_without( 'doctor_id' ),
	);
}

if ( '' !== $filters['visit'] ) {
	/* translators: %s: "Clinic visit" or "Video consultation". */
	$dak_chips[] = array( sprintf( __( 'Visit: %s', 'doctor-ak-portal' ), $dak_visit_options[ $filters['visit'] ] ), $dak_without( 'visit' ) );
}

if ( $filters['clinic'] > 0 ) {
	$dak_chips[] = array(
		/* translators: %s: clinic name. */
		sprintf( __( 'Clinic: %s', 'doctor-ak-portal' ), isset( $clinic_options[ $filters['clinic'] ] ) ? $clinic_options[ $filters['clinic'] ] : '#' . (int) $filters['clinic'] ),
		$dak_without( 'clinic' ),
	);
}

if ( '' !== $filters['service'] ) {
	/* translators: %s: procedure/service name. */
	$dak_chips[] = array( sprintf( __( 'Procedure: %s', 'doctor-ak-portal' ), $filters['service'] ), $dak_without( 'service' ) );
}

if ( '' !== $filters['date_from'] ) {
	/* translators: %s: date. */
	$dak_chips[] = array( sprintf( __( 'From: %s', 'doctor-ak-portal' ), $dak_format_date( $filters['date_from'] ) ), $dak_without( 'date_from' ) );
}

if ( '' !== $filters['date_to'] ) {
	/* translators: %s: date. */
	$dak_chips[] = array( sprintf( __( 'To: %s', 'doctor-ak-portal' ), $dak_format_date( $filters['date_to'] ) ), $dak_without( 'date_to' ) );
}

if ( '' !== $filters['payment_status'] ) {
	$dak_chips[] = array(
		/* translators: %s: payment status. */
		sprintf( __( 'Payment: %s', 'doctor-ak-portal' ), isset( $payment_status_options[ $filters['payment_status'] ] ) ? $payment_status_options[ $filters['payment_status'] ] : $filters['payment_status'] ),
		$dak_without( 'payment_status' ),
	);
}

$dak_has_filters = ! empty( $dak_chips );

// "Clear all" keeps a patient filter — that one has its own "Clear filter" in its banner.
$dak_clear_all_url = add_query_arg( array_filter( array( 'patient_id' => $filters['patient_id'] > 0 ? $filters['patient_id'] : '' ) ), $encounters_url );

$dak_more_active = (int) ( '' !== $filters['date_from'] ) + (int) ( '' !== $filters['date_to'] ) + (int) ( '' !== $filters['payment_status'] );
/* translators: %d: number of active filters inside "More filters". */
$dak_more_label = $dak_more_active > 0 ? sprintf( __( 'More filters (%d)', 'doctor-ak-portal' ), $dak_more_active ) : __( 'More filters', 'doctor-ak-portal' );
?>
<div class="dak-list-page dak-appts-page dak-encounters-page">
<div class="dak-page-head">
	<div>
		<h1><?php esc_html_e( 'Encounters', 'doctor-ak-portal' ); ?></h1>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of encounters. */
					_n( '%d encounter', '%d encounters', count( $encounters ), 'doctor-ak-portal' ),
					count( $encounters )
				)
			);
			?>
		</p>
	</div>
	<button type="button" class="dak-button dak-button-primary" id="dak-admin-add-encounter"><?php esc_html_e( '+ Add Encounter', 'doctor-ak-portal' ); ?></button>
</div>

<?php if ( '' !== $filtered_patient ) : ?>
	<div class="dak-alert dak-alert-success">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: patient's name. */
				__( 'Showing encounters for %s.', 'doctor-ak-portal' ),
				$filtered_patient
			)
		);
		?>
		<a class="dak-link" href="<?php echo esc_url( $encounters_url ); ?>"><?php esc_html_e( 'Clear filter', 'doctor-ak-portal' ); ?></a>
	</div>
<?php endif; ?>

<section class="dak-appts-toolbar" aria-label="<?php esc_attr_e( 'Filter encounters', 'doctor-ak-portal' ); ?>">
	<form
		method="get"
		action="<?php echo esc_url( $encounters_url ); ?>"
		id="dak-enc-filter-form"
		class="dak-appt-filters-form dak-appts-filters"
		<?php echo $dak_live_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string. ?>
	>
		<input type="hidden" name="section" value="encounters">
		<?php if ( $filters['patient_id'] > 0 ) : ?>
			<input type="hidden" name="patient_id" value="<?php echo esc_attr( $filters['patient_id'] ); ?>">
		<?php endif; ?>

		<div class="dak-field dak-appts-filter-search">
			<label for="dak-admin-encounters-search"><?php esc_html_e( 'Search', 'doctor-ak-portal' ); ?></label>
			<input type="search" id="dak-admin-encounters-search" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Name, phone, ENC or APT number…', 'doctor-ak-portal' ); ?>">
		</div>

		<div class="dak-field dak-appts-filter-range">
			<label for="dak-admin-encounters-status"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-encounters-status" name="status">
				<option value=""><?php esc_html_e( 'All statuses', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $dak_status_labels as $dak_status_slug => $dak_status_label ) : ?>
					<option value="<?php echo esc_attr( $dak_status_slug ); ?>" <?php selected( $filters['status'], $dak_status_slug ); ?>><?php echo esc_html( $dak_status_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="dak-field dak-appts-filter-doctor">
			<label for="dak-admin-encounters-doctor"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-encounters-doctor" name="doctor_id" class="dak-select-searchable" data-placeholder="<?php esc_attr_e( 'Search doctors…', 'doctor-ak-portal' ); ?>">
				<option value="0"><?php esc_html_e( 'All doctors', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $doctors as $dak_doctor_option ) : ?>
					<option value="<?php echo esc_attr( $dak_doctor_option->ID ); ?>" <?php selected( $filters['doctor_id'], $dak_doctor_option->ID ); ?>><?php echo esc_html( $dak_doctor_option->display_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="dak-field dak-appts-filter-visit">
			<label for="dak-admin-encounters-visit"><?php esc_html_e( 'Visit type', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-encounters-visit" name="visit">
				<option value=""><?php esc_html_e( 'All visits', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $dak_visit_options as $dak_visit_slug => $dak_visit_label ) : ?>
					<option value="<?php echo esc_attr( $dak_visit_slug ); ?>" <?php selected( $filters['visit'], $dak_visit_slug ); ?>><?php echo esc_html( $dak_visit_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="dak-field dak-appts-filter-clinic">
			<label for="dak-admin-encounters-clinic"><?php esc_html_e( 'Clinic', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-encounters-clinic" name="clinic" class="dak-select-searchable" data-placeholder="<?php esc_attr_e( 'Search clinics…', 'doctor-ak-portal' ); ?>">
				<option value="0"><?php esc_html_e( 'All clinics', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $clinic_options as $dak_clinic_id => $dak_clinic_name ) : ?>
					<option value="<?php echo esc_attr( $dak_clinic_id ); ?>" <?php selected( (int) $filters['clinic'], (int) $dak_clinic_id ); ?>><?php echo esc_html( $dak_clinic_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="dak-field dak-appts-filter-service">
			<label for="dak-admin-encounters-service"><?php esc_html_e( 'Procedure', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-encounters-service" name="service" class="dak-select-searchable" data-placeholder="<?php esc_attr_e( 'Search procedures…', 'doctor-ak-portal' ); ?>">
				<option value=""><?php esc_html_e( 'All procedures', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $service_options as $dak_service_name ) : ?>
					<option value="<?php echo esc_attr( $dak_service_name ); ?>" <?php selected( mb_strtolower( $filters['service'] ), mb_strtolower( $dak_service_name ) ); ?>><?php echo esc_html( $dak_service_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="dak-appts-filter-buttons">
			<button
				type="button"
				class="dak-button dak-button-secondary dak-appts-more-toggle"
				data-dak-disclosure
				aria-expanded="<?php echo $dak_more_active > 0 ? 'true' : 'false'; ?>"
				aria-controls="dak-enc-more-filters"
				data-more-label="<?php echo esc_attr( $dak_more_label ); ?>"
				data-less-label="<?php esc_attr_e( 'Fewer filters', 'doctor-ak-portal' ); ?>"
			><?php echo $dak_more_active > 0 ? esc_html__( 'Fewer filters', 'doctor-ak-portal' ) : esc_html( $dak_more_label ); ?></button>
			<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Apply', 'doctor-ak-portal' ); ?></button>
		</div>

		<div class="dak-appts-more-filters" id="dak-enc-more-filters"<?php echo $dak_more_active > 0 ? '' : ' hidden'; ?>>
			<div class="dak-field">
				<label for="dak-admin-encounters-date-from"><?php esc_html_e( 'From', 'doctor-ak-portal' ); ?></label>
				<input type="date" id="dak-admin-encounters-date-from" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
			</div>

			<div class="dak-field">
				<label for="dak-admin-encounters-date-to"><?php esc_html_e( 'To', 'doctor-ak-portal' ); ?></label>
				<input type="date" id="dak-admin-encounters-date-to" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
			</div>

			<div class="dak-field">
				<label for="dak-admin-encounters-payment-status"><?php esc_html_e( 'Payment status', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-encounters-payment-status" name="payment_status">
					<option value=""><?php esc_html_e( 'All', 'doctor-ak-portal' ); ?></option>
					<?php foreach ( $payment_status_options as $dak_slug => $dak_label ) : ?>
						<option value="<?php echo esc_attr( $dak_slug ); ?>" <?php selected( $filters['payment_status'], $dak_slug ); ?>><?php echo esc_html( $dak_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
	</form>

	<?php if ( ! empty( $dak_chips ) ) : ?>
		<ul class="dak-appts-chips" aria-label="<?php esc_attr_e( 'Active filters', 'doctor-ak-portal' ); ?>">
			<?php foreach ( $dak_chips as $dak_chip ) : ?>
				<li>
					<a class="dak-appts-chip" href="<?php echo esc_url( $dak_chip[1] ); ?>" data-live-filter-clear<?php echo $dak_live_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string. ?>>
						<span><?php echo esc_html( $dak_chip[0] ); ?></span>
						<span class="dak-appts-chip-x" aria-hidden="true">&times;</span>
						<span class="dak-visually-hidden"><?php esc_html_e( '(remove filter)', 'doctor-ak-portal' ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
			<li>
				<a class="dak-appts-clear-all" href="<?php echo esc_url( $dak_clear_all_url ); ?>" data-live-filter-clear<?php echo $dak_live_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string. ?>><?php esc_html_e( 'Clear all', 'doctor-ak-portal' ); ?></a>
			</li>
		</ul>
	<?php endif; ?>
</section>

<section class="dak-results" id="dak-encounters-list" aria-labelledby="dak-encounters-results-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-encounters-results-title"><?php esc_html_e( 'Encounters', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $encounters ) ) ); ?></span></h2>
		<div class="dak-appts-sort">
			<label for="dak-admin-encounters-sort"><?php esc_html_e( 'Sort', 'doctor-ak-portal' ); ?></label>
			<?php // Belongs to the filter form above via form="…", so it auto-applies like every other filter. ?>
			<select id="dak-admin-encounters-sort" name="sort" form="dak-enc-filter-form">
				<option value="desc" <?php selected( $filters['sort'], 'desc' ); ?>><?php esc_html_e( 'Newest first', 'doctor-ak-portal' ); ?></option>
				<option value="asc" <?php selected( $filters['sort'], 'asc' ); ?>><?php esc_html_e( 'Oldest first', 'doctor-ak-portal' ); ?></option>
			</select>
		</div>
	</div>

	<?php if ( empty( $encounters ) ) : ?>
		<p class="dak-empty-state">
			<?php
			echo esc_html(
				$dak_has_filters || $filters['patient_id'] > 0
					? __( 'No encounters match these filters.', 'doctor-ak-portal' )
					: __( 'No encounters have been recorded yet.', 'doctor-ak-portal' )
			);
			?>
		</p>
	<?php else : ?>
		<?php
		// Every link into an encounter carries this list's filters, so the
		// encounter screen's back link returns here, filtered, at this row.
		$dak_return_args = \DoctorAKPortal\Includes\Encounter_Return::link_args( 'encounters', $filters );
		?>
		<div class="dak-data-table-wrap">
			<table class="dak-data-table dak-ui-table dak-encounters-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Patient / encounter', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date and time', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Doctor / clinic', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $encounters as $row ) : ?>
						<?php
						$dak_appt                   = $row['appointment'];
						$dak_encounter_view_url     = add_query_arg( array_merge( array( 'encounter_id' => $row['id'] ), $dak_return_args ), $encounter_url );
						// The appointment may have been deleted after the visit; the encounter still records who it was for.
						$dak_encounter_patient_user = empty( $dak_appt['patient_name'] ) && $row['patient_id'] > 0 ? get_userdata( $row['patient_id'] ) : false;
						$dak_encounter_patient_name = ! empty( $dak_appt['patient_name'] ) ? $dak_appt['patient_name'] : ( $dak_encounter_patient_user ? $dak_encounter_patient_user->display_name : __( 'Unknown patient', 'doctor-ak-portal' ) );
						$dak_encounter_doctor_user  = empty( $dak_appt['doctor_name'] ) && $row['doctor_id'] > 0 ? get_userdata( $row['doctor_id'] ) : false;
						$dak_encounter_doctor_name  = ! empty( $dak_appt['doctor_name'] ) ? $dak_appt['doctor_name'] : ( $dak_encounter_doctor_user ? $dak_encounter_doctor_user->display_name : '' );
						$dak_encounter_place        = ! empty( $dak_appt['clinic_name'] ) ? $dak_appt['clinic_name'] : ( isset( $dak_appt['type_label'] ) ? $dak_appt['type_label'] : '' );
						$dak_is_open                = \DoctorAKPortal\Includes\Encounters::STATUS_OPEN === $row['status'];
						$dak_enc_label              = sprintf( 'ENC-%04d', $row['id'] );

						// No pre-booked appointment behind this visit.
						if ( \DoctorAKPortal\Includes\Encounters::is_walk_in( $row ) ) {
							$dak_enc_label .= ' · ' . __( 'Walk-in', 'doctor-ak-portal' );
						}
						?>
						<tr id="dak-encounter-<?php echo esc_attr( $row['id'] ); ?>" data-row data-encounter-row="<?php echo esc_attr( $row['id'] ); ?>" data-list-search-row data-list-search-text="<?php echo esc_attr( strtolower( $dak_encounter_patient_name . ' ' . $dak_encounter_doctor_name . ' ' . $dak_enc_label ) ); ?>">
							<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Patient / encounter', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-primary"><?php echo esc_html( $dak_encounter_patient_name ); ?></span>
									<span class="dak-cell-sub dak-cell-id"><?php echo esc_html( $dak_enc_label ); ?></span>
									<?php if ( '' !== $row['problem_summary'] ) : ?>
										<?php // Recorded problems, verbatim — closed by default so clinical text never sets the list's column widths. ?>
										<details class="dak-cell-details">
											<summary><?php esc_html_e( 'Problems recorded', 'doctor-ak-portal' ); ?></summary>
											<p class="dak-cell-details-text"><?php echo esc_html( $row['problem_summary'] ); ?></p>
										</details>
									<?php else : ?>
										<span class="dak-cell-note"><?php esc_html_e( 'No problem recorded', 'doctor-ak-portal' ); ?></span>
									<?php endif; ?>
								</span>
							</td>

							<td class="dak-col-nowrap" data-label="<?php esc_attr_e( 'Date and time', 'doctor-ak-portal' ); ?>">
								<?php $dak_checked_in_ts = strtotime( (string) $row['checked_in_at'] ); ?>
								<span class="dak-cell-stack">
									<span class="dak-cell-strong is-tabular"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::date( $row['checked_in_at'] ) ); ?></span>
									<span class="dak-cell-sub is-tabular"><?php echo esc_html( false !== $dak_checked_in_ts ? date_i18n( 'h:i A', $dak_checked_in_ts ) : '' ); ?></span>
								</span>
							</td>

							<td data-label="<?php esc_attr_e( 'Doctor / clinic', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-strong"><?php echo esc_html( '' !== $dak_encounter_doctor_name ? sprintf( 'Dr. %s', $dak_encounter_doctor_name ) : __( 'Unknown doctor', 'doctor-ak-portal' ) ); ?></span>
									<span class="dak-cell-sub"><?php echo esc_html( '' !== $dak_encounter_place ? $dak_encounter_place : __( 'No clinic', 'doctor-ak-portal' ) ); ?></span>
								</span>
							</td>

							<td class="dak-col-status" data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>">
								<span class="dak-status-pill <?php echo $dak_is_open ? 'dak-status-pill-is-active' : 'dak-status-pill-is-neutral'; ?>">
									<?php echo esc_html( isset( $dak_status_labels[ $row['status'] ] ) ? $dak_status_labels[ $row['status'] ] : $row['status'] ); ?>
								</span>
							</td>

							<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
								<div class="dak-row-actions">
									<a class="dak-text-action" href="<?php echo esc_url( $dak_encounter_view_url ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: encounter id, 2: patient name. */ __( 'View encounter %1$s for %2$s', 'doctor-ak-portal' ), $dak_enc_label, $dak_encounter_patient_name ) ); ?>"><?php esc_html_e( 'View', 'doctor-ak-portal' ); ?></a>

									<details class="dak-row-menu">
										<summary class="dak-row-menu-toggle" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: patient name. */ __( 'More actions for %s', 'doctor-ak-portal' ), $dak_encounter_patient_name ) ); ?>"><?php esc_html_e( 'More', 'doctor-ak-portal' ); ?></summary>
										<div class="dak-row-menu-panel" role="menu">
											<a class="dak-row-menu-item" role="menuitem" href="<?php echo esc_url( $dak_encounter_view_url ); ?>"><?php esc_html_e( 'Edit encounter', 'doctor-ak-portal' ); ?></a>
											<a class="dak-row-menu-item" role="menuitem" href="<?php echo esc_url( $row['bill_pdf_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Bill (PDF)', 'doctor-ak-portal' ); ?></a>
											<a class="dak-row-menu-item" role="menuitem" href="<?php echo esc_url( $row['prescription_pdf_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Prescription (PDF)', 'doctor-ak-portal' ); ?></a>
											<hr class="dak-row-menu-sep">
											<button
												type="button"
												class="dak-row-menu-item is-danger"
												role="menuitem"
												data-admin-encounter-delete
												data-encounter-id="<?php echo esc_attr( $row['id'] ); ?>"
											><?php esc_html_e( 'Delete encounter', 'doctor-ak-portal' ); ?></button>
										</div>
									</details>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>
</div>