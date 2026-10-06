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
</div>

<div class="dak-list-toolbar">
	<form method="get" action="<?php echo esc_url( $encounters_url ); ?>" class="dak-list-filters">
		<input type="hidden" name="tab" value="encounters">

		<div class="dak-field">
			<label for="dak-doctor-encounters-status"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></label>
			<select id="dak-doctor-encounters-status" name="status">
				<option value=""><?php esc_html_e( 'All statuses', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $dak_status_labels as $dak_status_slug => $dak_status_label ) : ?>
					<option value="<?php echo esc_attr( $dak_status_slug ); ?>" <?php selected( $filters['status'], $dak_status_slug ); ?>><?php echo esc_html( $dak_status_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="dak-field">
			<label for="dak-doctor-encounters-date-from"><?php esc_html_e( 'From', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-doctor-encounters-date-from" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
		</div>

		<div class="dak-field">
			<label for="dak-doctor-encounters-date-to"><?php esc_html_e( 'To', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-doctor-encounters-date-to" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
		</div>

		<div class="dak-list-filter-actions">
			<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Apply', 'doctor-ak-portal' ); ?></button>
			<?php if ( $dak_has_filters ) : ?>
				<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $encounters_url ); ?>"><?php esc_html_e( 'Clear', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	</form>
</div>

<section class="dak-results" id="dak-doctor-encounters-list" aria-labelledby="dak-doctor-encounters-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-doctor-encounters-title"><?php esc_html_e( 'Encounters', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $encounters ) ) ); ?></span></h2>
		<?php if ( ! empty( $encounters ) ) : ?>
			<div class="dak-dashboard-search dak-list-search-box">
				<span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
				<input type="search" data-list-search="#dak-doctor-encounters-list" placeholder="<?php esc_attr_e( 'Search patient or ENC id', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search encounters', 'doctor-ak-portal' ); ?>">
			</div>
		<?php endif; ?>
	</div>

	<?php if ( empty( $encounters ) ) : ?>
		<p class="dak-empty-state">
			<?php
			echo esc_html(
				$dak_has_filters
					? __( 'No encounters match these filters.', 'doctor-ak-portal' )
					: __( 'No encounters yet. Checking a patient in opens one.', 'doctor-ak-portal' )
			);
			?>
		</p>
	<?php else : ?>
		<div class="dak-data-table-wrap">
			<table class="dak-data-table dak-ui-table dak-encounters-table dak-doctor-encounters-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Patient / encounter', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date and time', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Clinic / visit', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $encounters as $row ) : ?>
						<?php
						$dak_appt                   = $row['appointment'];
						// Carries this tab's filters so the encounter's back link returns
						// here, filtered, at this row (see Encounter_Return).
						$dak_encounter_edit_url     = add_query_arg( array_merge( array( 'encounter_id' => $row['id'] ), \DoctorAKPortal\Includes\Encounter_Return::link_args( 'encounters', $filters ) ), $encounter_url );
						// The appointment may have been deleted after the visit; the encounter still records who it was for.
						$dak_encounter_patient_user = empty( $dak_appt['patient_name'] ) && $row['patient_id'] > 0 ? get_userdata( $row['patient_id'] ) : false;
						$dak_encounter_patient_name = ! empty( $dak_appt['patient_name'] ) ? $dak_appt['patient_name'] : ( $dak_encounter_patient_user ? $dak_encounter_patient_user->display_name : __( 'Unknown patient', 'doctor-ak-portal' ) );
						$dak_enc_label              = sprintf( 'ENC-%04d', $row['id'] );
						$dak_place                  = ! empty( $dak_appt['clinic_name'] ) ? $dak_appt['clinic_name'] : ( isset( $dak_appt['type_label'] ) ? $dak_appt['type_label'] : '' );
						$dak_checked_in_ts          = strtotime( (string) $row['checked_in_at'] );
						$dak_is_open                = \DoctorAKPortal\Includes\Encounters::STATUS_OPEN === $row['status'];
						?>
						<tr id="dak-encounter-<?php echo esc_attr( $row['id'] ); ?>" data-row data-encounter-row="<?php echo esc_attr( $row['id'] ); ?>" data-list-search-row data-list-search-text="<?php echo esc_attr( strtolower( $dak_encounter_patient_name . ' ' . $dak_enc_label ) ); ?>">
							<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Patient / encounter', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-primary"><?php echo esc_html( $dak_encounter_patient_name ); ?></span>
									<span class="dak-cell-sub dak-cell-id"><?php echo esc_html( $dak_enc_label ); ?></span>
									<?php if ( '' !== $row['problem_summary'] ) : ?>
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
								<span class="dak-cell-stack">
									<span class="dak-cell-strong is-tabular"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::date( $row['checked_in_at'] ) ); ?></span>
									<span class="dak-cell-sub is-tabular"><?php echo esc_html( false !== $dak_checked_in_ts ? date_i18n( 'h:i A', $dak_checked_in_ts ) : '' ); ?></span>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'Clinic / visit', 'doctor-ak-portal' ); ?>">
								<?php echo esc_html( '' !== $dak_place ? $dak_place : __( 'No clinic', 'doctor-ak-portal' ) ); ?>
							</td>
							<td class="dak-col-status" data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>">
								<span class="dak-status-pill <?php echo $dak_is_open ? 'dak-status-pill-is-active' : 'dak-status-pill-is-neutral'; ?>"><?php echo esc_html( isset( $dak_status_labels[ $row['status'] ] ) ? $dak_status_labels[ $row['status'] ] : $row['status'] ); ?></span>
							</td>
							<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
								<div class="dak-row-actions">
									<a class="dak-text-action" href="<?php echo esc_url( $dak_encounter_edit_url ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: encounter id, 2: patient name. */ __( 'View encounter %1$s for %2$s', 'doctor-ak-portal' ), $dak_enc_label, $dak_encounter_patient_name ) ); ?>"><?php esc_html_e( 'View', 'doctor-ak-portal' ); ?></a>
									<details class="dak-row-menu">
										<summary class="dak-row-menu-toggle" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: patient name. */ __( 'More actions for %s', 'doctor-ak-portal' ), $dak_encounter_patient_name ) ); ?>"><?php esc_html_e( 'More', 'doctor-ak-portal' ); ?></summary>
										<div class="dak-row-menu-panel" role="menu">
											<a class="dak-row-menu-item" role="menuitem" href="<?php echo esc_url( $dak_encounter_edit_url ); ?>"><?php esc_html_e( 'Edit encounter', 'doctor-ak-portal' ); ?></a>
											<a class="dak-row-menu-item" role="menuitem" href="<?php echo esc_url( $row['bill_pdf_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Bill (PDF)', 'doctor-ak-portal' ); ?></a>
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