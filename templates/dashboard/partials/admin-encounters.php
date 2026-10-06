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

<section class="dak-dashboard-card dak-appt-filters-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Filters', 'doctor-ak-portal' ); ?></h2>
	</div>

	<form method="get" action="<?php echo esc_url( $encounters_url ); ?>" class="dak-appt-filters-form">
		<input type="hidden" name="section" value="encounters">
		<?php if ( $filters['patient_id'] > 0 ) : ?>
			<input type="hidden" name="patient_id" value="<?php echo esc_attr( $filters['patient_id'] ); ?>">
		<?php endif; ?>

		<div class="dak-field">
			<label for="dak-admin-encounters-date-from"><?php esc_html_e( 'From', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-admin-encounters-date-from" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
		</div>

		<div class="dak-field">
			<label for="dak-admin-encounters-date-to"><?php esc_html_e( 'To', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-admin-encounters-date-to" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
		</div>

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

		<div class="dak-admin-filter-actions">
			<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Filter', 'doctor-ak-portal' ); ?></button>
			<?php if ( $dak_has_filters ) : ?>
				<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $encounters_url ); ?>"><?php esc_html_e( 'Clear', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	</form>
</section>

<section class="dak-dashboard-card" id="dak-encounters-list">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Encounter list', 'doctor-ak-portal' ); ?></h2>
		<?php if ( ! empty( $encounters ) ) : ?>
			<div class="dak-dashboard-search dak-list-search-box">
				<span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
				<input type="search" data-list-search="#dak-encounters-list" placeholder="<?php esc_attr_e( 'Search encounters', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search encounters', 'doctor-ak-portal' ); ?>">
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
			<table class="dak-data-table dak-encounters-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Patient / encounter', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date and time', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Doctor / clinic', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-actions"><span class="dak-visually-hidden"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $encounters as $row ) : ?>
						<?php
						$dak_appt                   = $row['appointment'];
						$dak_encounter_view_url     = add_query_arg( array_merge( array( 'encounter_id' => $row['id'] ), $dak_return_args ), $encounter_url );
						$dak_encounter_patient_name = ! empty( $dak_appt['patient_name'] ) ? $dak_appt['patient_name'] : __( 'Unknown patient', 'doctor-ak-portal' );
						$dak_encounter_doctor_name  = isset( $dak_appt['doctor_name'] ) ? $dak_appt['doctor_name'] : '';
						$dak_encounter_place        = ! empty( $dak_appt['clinic_name'] ) ? $dak_appt['clinic_name'] : ( isset( $dak_appt['type_label'] ) ? $dak_appt['type_label'] : '' );
						$dak_is_open                = \DoctorAKPortal\Includes\Encounters::STATUS_OPEN === $row['status'];
						$dak_checked_in_ts          = strtotime( $row['checked_in_at'] );
						?>
						<tr id="dak-encounter-<?php echo esc_attr( $row['id'] ); ?>" data-row data-encounter-row="<?php echo esc_attr( $row['id'] ); ?>" data-list-search-row data-list-search-text="<?php echo esc_attr( strtolower( $dak_encounter_patient_name . ' ' . $dak_encounter_doctor_name . ' ' . sprintf( 'enc-%04d', $row['id'] ) ) ); ?>">
							<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Patient / encounter', 'doctor-ak-portal' ); ?>">
								<div class="dak-cell-identity">
									<span class="dak-avatar dak-avatar-sm" aria-hidden="true">
										<?php if ( ! empty( $dak_appt['patient_avatar_url'] ) ) : ?>
											<img src="<?php echo esc_url( $dak_appt['patient_avatar_url'] ); ?>" alt="">
										<?php else : ?>
											<?php echo esc_html( isset( $dak_appt['patient_initials'] ) ? $dak_appt['patient_initials'] : '?' ); ?>
										<?php endif; ?>
									</span>
									<span class="dak-cell-stack">
										<span class="dak-cell-primary"><?php echo esc_html( $dak_encounter_patient_name ); ?></span>
										<span class="dak-cell-sub is-tabular"><?php echo esc_html( sprintf( 'ENC-%04d', $row['id'] ) ); ?></span>
										<?php if ( '' !== $row['problem_summary'] ) : ?>
											<span class="dak-cell-preview">
												<span class="dak-cell-preview-text" title="<?php echo esc_attr( $row['problem_summary'] ); ?>">
													<?php
													echo esc_html(
														sprintf(
															/* translators: %s: recorded problems, comma-separated, verbatim. */
															__( 'Problems: %s', 'doctor-ak-portal' ),
															$row['problem_summary']
														)
													);
													?>
												</span>
												<a href="<?php echo esc_url( $dak_encounter_view_url ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: encounter id. */ __( 'Read the full record for %s', 'doctor-ak-portal' ), sprintf( 'ENC-%04d', $row['id'] ) ) ); ?>"><?php esc_html_e( 'Read more', 'doctor-ak-portal' ); ?></a>
											</span>
										<?php else : ?>
											<span class="dak-cell-sub"><?php esc_html_e( 'No problem recorded', 'doctor-ak-portal' ); ?></span>
										<?php endif; ?>
									</span>
								</div>
							</td>

							<td class="dak-col-nowrap" data-label="<?php esc_attr_e( 'Date and time', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span><?php echo esc_html( false !== $dak_checked_in_ts ? mysql2date( get_option( 'date_format' ), $row['checked_in_at'] ) : $row['checked_in_at'] ); ?></span>
									<span class="dak-cell-sub is-tabular"><?php echo esc_html( false !== $dak_checked_in_ts ? mysql2date( get_option( 'time_format' ), $row['checked_in_at'] ) : '' ); ?></span>
								</span>
							</td>

							<td data-label="<?php esc_attr_e( 'Doctor / clinic', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span><?php echo esc_html( '' !== $dak_encounter_doctor_name ? sprintf( 'Dr. %s', $dak_encounter_doctor_name ) : __( 'Unknown doctor', 'doctor-ak-portal' ) ); ?></span>
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
									<a class="dak-button dak-button-secondary dak-button-sm" href="<?php echo esc_url( $dak_encounter_view_url ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: encounter id, 2: patient name. */ __( 'View encounter %1$s for %2$s', 'doctor-ak-portal' ), sprintf( 'ENC-%04d', $row['id'] ), $dak_encounter_patient_name ) ); ?>"><?php esc_html_e( 'View encounter', 'doctor-ak-portal' ); ?></a>

									<details class="dak-row-menu">
										<summary class="dak-row-menu-toggle" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: patient name. */ __( 'More actions for %s', 'doctor-ak-portal' ), $dak_encounter_patient_name ) ); ?>"><?php esc_html_e( 'More', 'doctor-ak-portal' ); ?></summary>
										<div class="dak-row-menu-panel" role="menu">
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
		<p class="dak-empty-state dak-hidden" data-list-search-empty><?php esc_html_e( 'No encounters match your search.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</section>
