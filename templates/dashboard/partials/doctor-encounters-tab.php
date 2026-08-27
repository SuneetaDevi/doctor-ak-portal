<?php
/**
 * Template: Doctor dashboard "Encounters" tab — every clinical encounter
 * belonging to this doctor only (opened by checking a patient in, closed
 * once their visit is documented and billed — see the Encounters class),
 * newest first. Each row opens the full Encounter detail screen (Problems,
 * Prescription, Bill, Documents & Checkout). Mirrors the admin Encounters
 * list, minus the Doctor filter (redundant here) and Delete action
 * (destructive — stays administrator-only).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $encounters      Rows from Encounters::all_flat_for_admin( [ 'doctor_id' => ... ] ), each with an added 'appointment' sub-array and 'bill_pdf_url'.
 * @var string $encounters_url  Unfiltered URL of this tab, for the filter form and "Clear" link.
 * @var string $encounter_url   Base URL of the Encounter detail screen — each row links here with `&encounter_id=X`.
 * @var array  $filters         Active filter values: date_from, date_to, status.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_has_filters   = '' !== $filters['date_from'] || '' !== $filters['date_to'] || '' !== $filters['status'];
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

<section class="dak-dashboard-card dak-appt-filters-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Filters', 'doctor-ak-portal' ); ?></h2>
	</div>

	<form method="get" action="<?php echo esc_url( $encounters_url ); ?>" class="dak-appt-filters-form">
		<input type="hidden" name="tab" value="encounters">

		<div class="dak-field">
			<label for="dak-doctor-encounters-date-from"><?php esc_html_e( 'From', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-doctor-encounters-date-from" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
		</div>

		<div class="dak-field">
			<label for="dak-doctor-encounters-date-to"><?php esc_html_e( 'To', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-doctor-encounters-date-to" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
		</div>

		<div class="dak-field">
			<label for="dak-doctor-encounters-status"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></label>
			<select id="dak-doctor-encounters-status" name="status">
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

<section class="dak-dashboard-card" id="dak-doctor-encounters-list">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Encounter list', 'doctor-ak-portal' ); ?></h2>
		<?php if ( ! empty( $encounters ) ) : ?>
			<div class="dak-dashboard-search dak-list-search-box">
				<span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
				<input type="search" data-list-search="#dak-doctor-encounters-list" placeholder="<?php esc_attr_e( 'Search encounters', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search encounters', 'doctor-ak-portal' ); ?>">
			</div>
		<?php endif; ?>
	</div>

	<?php if ( empty( $encounters ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No encounters match these filters.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $encounters as $row ) : ?>
			<?php
			$dak_appt               = $row['appointment'];
			$dak_encounter_edit_url = add_query_arg( 'encounter_id', $row['id'], $encounter_url );
			$dak_encounter_patient_name = isset( $dak_appt['patient_name'] ) ? $dak_appt['patient_name'] : '';
			?>
			<div class="dak-admin-record-row" data-encounter-row="<?php echo esc_attr( $row['id'] ); ?>" data-list-search-row data-list-search-text="<?php echo esc_attr( strtolower( $dak_encounter_patient_name ) ); ?>">
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
							<?php echo esc_html( ! empty( $dak_appt['clinic_label'] ) ? $dak_appt['clinic_label'] : ( isset( $dak_appt['type_label'] ) ? $dak_appt['type_label'] : __( 'No clinic', 'doctor-ak-portal' ) ) ); ?>
						</span>
					</span>

					<span class="dak-admin-record-row-meta"><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['checked_in_at'] ) ); ?></span>

					<span class="dak-admin-record-row-tags">
						<span class="dak-status-pill dak-status-pill-outline dak-status-pill-truncate" title="<?php echo esc_attr( $row['problem_summary'] ); ?>"><?php echo esc_html( '' !== $row['problem_summary'] ? $row['problem_summary'] : __( 'No problem recorded', 'doctor-ak-portal' ) ); ?></span>
					</span>

					<span class="dak-status-pill dak-status-pill-outline <?php echo \DoctorAKPortal\Includes\Encounters::STATUS_OPEN === $row['status'] ? 'dak-status-pill-is-active' : 'dak-status-pill-is-disabled'; ?>">
						<?php echo esc_html( $dak_status_labels[ $row['status'] ] ); ?>
					</span>

					<span class="dak-admin-record-row-actions">
						<a
							class="dak-icon-button"
							href="<?php echo esc_url( $dak_encounter_edit_url ); ?>"
							title="<?php esc_attr_e( 'Open', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'Open', 'doctor-ak-portal' ); ?>"
						><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 10s2.7-5.5 8-5.5S18 10 18 10s-2.7 5.5-8 5.5S2 10 2 10z"/><circle cx="10" cy="10" r="2.2"/></svg></a>
						<a
							class="dak-icon-button"
							href="<?php echo esc_url( $row['bill_pdf_url'] ); ?>"
							target="_blank"
							rel="noopener"
							title="<?php esc_attr_e( 'View Billing', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'View Billing', 'doctor-ak-portal' ); ?>"
						><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="14" height="12" rx="1.5"/><path d="M6.5 8h7M6.5 11h7M6.5 14h4"/></svg></a>
					</span>
				</div>
			</div>
		<?php endforeach; ?>
		<p class="dak-empty-state dak-hidden" data-list-search-empty><?php esc_html_e( 'No encounters match your search.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</section>
