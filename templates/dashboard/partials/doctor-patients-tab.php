<?php
/**
 * Template: Doctor dashboard "Patients" tab — every patient belonging to
 * this doctor (Appointments::patients_for_doctor()), with an Edit action
 * that opens the shared Add/Edit Patient modal in edit mode.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $patients Rows from Appointments::patients_for_doctor().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-list-page">
<div class="dak-page-head">
	<div>
		<h1><?php esc_html_e( 'Patients', 'doctor-ak-portal' ); ?></h1>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of patients. */
					_n( '%d patient under your care', '%d patients under your care', count( $patients ), 'doctor-ak-portal' ),
					count( $patients )
				)
			);
			?>
		</p>
	</div>
	<button type="button" class="dak-button dak-button-primary" id="dak-doctor-add-patient-open"><?php esc_html_e( '+ Add Patient', 'doctor-ak-portal' ); ?></button>
</div>

<section class="dak-results" aria-labelledby="dak-doctor-patients-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-doctor-patients-title"><?php esc_html_e( 'Your patients', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $patients ) ) ); ?></span></h2>
		<div class="dak-dashboard-search dak-list-search-box dak-patient-list-search">
			<span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
			<input type="search" id="dak-patient-list-search" placeholder="<?php esc_attr_e( 'Search name or email', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search patients', 'doctor-ak-portal' ); ?>">
		</div>
	</div>

	<?php if ( empty( $patients ) ) : ?>
		<p class="dak-empty-state" id="dak-patient-list-empty"><?php esc_html_e( 'No patients yet. Patients appear here once they book with you, or use “+ Add Patient”.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div id="dak-patient-list">
			<div class="dak-data-table-wrap">
				<table class="dak-data-table dak-ui-table dak-doctor-patients-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Patient', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Phone', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Visits', 'doctor-ak-portal' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Patient since', 'doctor-ak-portal' ); ?></th>
							<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $patients as $patient ) : ?>
							<?php $dak_edit_patient_phone_parts = \DoctorAKPortal\Includes\Phone::split( $patient['phone'] ); ?>
							<tr id="dak-patient-<?php echo esc_attr( $patient['id'] ); ?>" data-row data-patient-search-row data-patient-search-text="<?php echo esc_attr( strtolower( $patient['name'] . ' ' . $patient['email'] ) ); ?>">
								<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Patient', 'doctor-ak-portal' ); ?>">
									<span class="dak-cell-stack">
										<span class="dak-cell-primary"><?php echo esc_html( $patient['name'] ); ?></span>
										<span class="dak-cell-sub dak-cell-email"><?php echo \DoctorAKPortal\Includes\Dashboard_Format::email_html( $patient['email'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside email_html(). ?></span>
									</span>
								</td>
								<td class="dak-col-nowrap is-tabular" data-label="<?php esc_attr_e( 'Phone', 'doctor-ak-portal' ); ?>"><?php if ( '' !== (string) $patient['phone'] ) : ?><?php echo esc_html( $patient['phone'] ); ?><?php else : ?><span class="dak-cell-sub"><?php esc_html_e( 'Not set', 'doctor-ak-portal' ); ?></span><?php endif; ?></td>
								<td class="is-tabular" data-label="<?php esc_attr_e( 'Visits', 'doctor-ak-portal' ); ?>"><?php echo esc_html( number_format_i18n( $patient['visit_count'] ) ); ?></td>
								<td class="dak-col-nowrap" data-label="<?php esc_attr_e( 'Patient since', 'doctor-ak-portal' ); ?>"><span class="dak-cell-sub is-tabular"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::date( $patient['registered_date'], $patient['registered_date'] ) ); ?></span></td>
								<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
									<div class="dak-row-actions">
										<button
											type="button"
											class="dak-text-action"
											data-dak-edit-patient
											data-patient-id="<?php echo esc_attr( $patient['id'] ); ?>"
											data-first-name="<?php echo esc_attr( $patient['first_name'] ); ?>"
											data-last-name="<?php echo esc_attr( $patient['last_name'] ); ?>"
											data-email="<?php echo esc_attr( $patient['email'] ); ?>"
											data-phone-code="<?php echo esc_attr( $dak_edit_patient_phone_parts['dial_code'] ); ?>"
											data-phone-number="<?php echo esc_attr( $dak_edit_patient_phone_parts['number'] ); ?>"
											data-clinic-location-id="<?php echo esc_attr( $patient['clinic_location_id'] ); ?>"
											aria-label="<?php echo esc_attr( sprintf( /* translators: %s: patient name. */ __( 'Edit %s', 'doctor-ak-portal' ), $patient['name'] ) ); ?>"
										><?php esc_html_e( 'Edit', 'doctor-ak-portal' ); ?></button>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="dak-empty-state dak-results-empty dak-hidden" id="dak-patient-list-no-results"><?php esc_html_e( 'No patients match your search.', 'doctor-ak-portal' ); ?></p>
		</div>
	<?php endif; ?>
</section>
</div>