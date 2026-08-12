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
 * @var array  $encounters       Rows from Encounters::all_flat_for_admin(), each with an added 'appointment' sub-array (see Appointments::notification_data()) and 'bill_pdf_url' (see Encounter_Handler::bill_pdf_download_url()).
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
<div class="dak-dashboard-greeting">
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
			<select id="dak-admin-encounters-doctor" name="doctor_id">
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

<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Encounter list', 'doctor-ak-portal' ); ?></h2>
	</div>

	<?php if ( empty( $encounters ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No encounters match these filters.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $encounters as $row ) : ?>
			<?php
			$dak_appt        = $row['appointment'];
			$dak_encounter_edit_url = add_query_arg( 'encounter_id', $row['id'], $encounter_url );
			?>
			<div class="dak-admin-record-row" data-encounter-row="<?php echo esc_attr( $row['id'] ); ?>">
				<div class="dak-admin-record-row-main">
					<span class="dak-avatar dak-avatar-sm" aria-hidden="true">
						<?php if ( ! empty( $dak_appt['patient_avatar_url'] ) ) : ?>
							<img src="<?php echo esc_url( $dak_appt['patient_avatar_url'] ); ?>" alt="">
						<?php else : ?>
							<?php echo esc_html( isset( $dak_appt['patient_initials'] ) ? $dak_appt['patient_initials'] : '?' ); ?>
						<?php endif; ?>
					</span>
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( isset( $dak_appt['patient_name'] ) ? $dak_appt['patient_name'] : __( 'Unknown patient', 'doctor-ak-portal' ) ); ?></strong>
						<span class="dak-admin-record-row-id">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: doctor name, 2: clinic name. */
									__( 'Dr. %1$s · %2$s', 'doctor-ak-portal' ),
									isset( $dak_appt['doctor_name'] ) ? $dak_appt['doctor_name'] : '—',
									! empty( $dak_appt['clinic_label'] ) ? $dak_appt['clinic_label'] : ( isset( $dak_appt['type_label'] ) ? $dak_appt['type_label'] : __( 'No clinic', 'doctor-ak-portal' ) )
								)
							);
							?>
						</span>
					</span>

					<span class="dak-admin-record-row-meta"><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['checked_in_at'] ) ); ?></span>

					<span class="dak-admin-record-row-tags">
						<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( '' !== $row['problem_summary'] ? $row['problem_summary'] : __( 'No problem recorded', 'doctor-ak-portal' ) ); ?></span>
					</span>

					<span class="dak-status-pill dak-status-pill-outline <?php echo \DoctorAKPortal\Includes\Encounters::STATUS_OPEN === $row['status'] ? 'dak-status-pill-is-active' : 'dak-status-pill-is-disabled'; ?>">
						<?php echo esc_html( $dak_status_labels[ $row['status'] ] ); ?>
					</span>

					<span class="dak-admin-record-row-actions">
						<a
							class="dak-icon-button"
							href="<?php echo esc_url( $dak_encounter_edit_url ); ?>"
							title="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
						><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13.5 3.5a1.7 1.7 0 0 1 2.4 2.4L6.5 15.3l-3 .7.7-3 9.3-9.3z"/></svg></a>
						<a
							class="dak-icon-button"
							href="<?php echo esc_url( $row['bill_pdf_url'] ); ?>"
							target="_blank"
							rel="noopener"
							title="<?php esc_attr_e( 'View Billing', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'View Billing', 'doctor-ak-portal' ); ?>"
						><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="14" height="12" rx="1.5"/><path d="M6.5 8h7M6.5 11h7M6.5 14h4"/></svg></a>
						<a
							class="dak-icon-button"
							href="<?php echo esc_url( $dak_encounter_edit_url ); ?>"
							title="<?php esc_attr_e( 'View Encounter Dashboard', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'View Encounter Dashboard', 'doctor-ak-portal' ); ?>"
						><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 10s2.7-5.5 8-5.5S18 10 18 10s-2.7 5.5-8 5.5S2 10 2 10z"/><circle cx="10" cy="10" r="2.2"/></svg></a>
						<button
							type="button"
							class="dak-icon-button dak-icon-button-danger"
							data-admin-encounter-delete
							data-encounter-id="<?php echo esc_attr( $row['id'] ); ?>"
							title="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
						><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h12M8 6V4.5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1V6M6 6l.6 9a1.5 1.5 0 0 0 1.5 1.4h3.8a1.5 1.5 0 0 0 1.5-1.4L14 6"/></svg></button>
					</span>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
