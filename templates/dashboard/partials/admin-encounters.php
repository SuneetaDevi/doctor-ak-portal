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
 * @var array  $filters          Active filter values: date_from, date_to, doctor_id, patient_id, status.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_has_filters = '' !== $filters['date_from'] || '' !== $filters['date_to'] || $filters['doctor_id'] > 0 || '' !== $filters['status'];
$dak_status_labels = array(
	\DoctorAKPortal\Includes\Encounters::STATUS_OPEN   => __( 'Open', 'doctor-ak-portal' ),
	\DoctorAKPortal\Includes\Encounters::STATUS_CLOSED => __( 'Closed', 'doctor-ak-portal' ),
);
?>
<div class="dak-list-page">
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

<div class="dak-list-toolbar">
	<form method="get" action="<?php echo esc_url( $encounters_url ); ?>" class="dak-list-filters">
		<input type="hidden" name="section" value="encounters">
		<?php if ( $filters['patient_id'] > 0 ) : ?>
			<input type="hidden" name="patient_id" value="<?php echo esc_attr( $filters['patient_id'] ); ?>">
		<?php endif; ?>

		<div class="dak-field">
			<label for="dak-admin-encounters-doctor"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-encounters-doctor" name="doctor_id" class="dak-select-searchable" data-placeholder="<?php esc_attr_e( 'Search doctors…', 'doctor-ak-portal' ); ?>">
				<option value="0"><?php esc_html_e( 'All doctors', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $doctors as $dak_doctor_option ) : ?>
					<option value="<?php echo esc_attr( $dak_doctor_option->ID ); ?>" <?php selected( $filters['doctor_id'], $dak_doctor_option->ID ); ?>><?php echo esc_html( $dak_doctor_option->display_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="dak-field">
			<label for="dak-admin-encounters-status"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-encounters-status" name="status">
				<option value=""><?php esc_html_e( 'All statuses', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $dak_status_labels as $dak_status_slug => $dak_status_label ) : ?>
					<option value="<?php echo esc_attr( $dak_status_slug ); ?>" <?php selected( $filters['status'], $dak_status_slug ); ?>><?php echo esc_html( $dak_status_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="dak-field">
			<label for="dak-admin-encounters-date-from"><?php esc_html_e( 'From', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-admin-encounters-date-from" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
		</div>

		<div class="dak-field">
			<label for="dak-admin-encounters-date-to"><?php esc_html_e( 'To', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-admin-encounters-date-to" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
		</div>

		<div class="dak-list-filter-actions">
			<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Apply', 'doctor-ak-portal' ); ?></button>
			<?php if ( $dak_has_filters ) : ?>
				<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $encounters_url ); ?>"><?php esc_html_e( 'Clear', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	</form>
</div>

<section class="dak-results" id="dak-encounters-list" aria-labelledby="dak-encounters-results-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-encounters-results-title"><?php esc_html_e( 'Encounters', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $encounters ) ) ); ?></span></h2>
		<?php if ( ! empty( $encounters ) ) : ?>
			<div class="dak-dashboard-search dak-list-search-box">
				<span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
				<input type="search" data-list-search="#dak-encounters-list" placeholder="<?php esc_attr_e( 'Search patient, doctor or ENC id', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search encounters', 'doctor-ak-portal' ); ?>">
			</div>
		<?php endif; ?>
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
		<p class="dak-empty-state dak-results-empty dak-hidden" data-list-search-empty><?php esc_html_e( 'No encounters match your search.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</section>
</div>