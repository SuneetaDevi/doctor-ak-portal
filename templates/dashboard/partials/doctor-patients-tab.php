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
<div class="dak-dashboard-greeting dak-admin-users-header">
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

<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Patient list', 'doctor-ak-portal' ); ?></h2>
		<div class="dak-dashboard-search dak-patient-list-search">
			<span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
			<input type="search" id="dak-patient-list-search" placeholder="<?php esc_attr_e( 'Search patients', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Search patients', 'doctor-ak-portal' ); ?>">
		</div>
	</div>

	<?php if ( empty( $patients ) ) : ?>
		<p class="dak-empty-state" id="dak-patient-list-empty"><?php esc_html_e( 'No patients yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div id="dak-patient-list">
			<?php foreach ( $patients as $patient ) : ?>
				<div class="dak-admin-record-row" data-patient-search-row data-patient-search-text="<?php echo esc_attr( strtolower( $patient['name'] . ' ' . $patient['email'] ) ); ?>">
					<div class="dak-admin-record-row-main">
						<span class="dak-avatar dak-avatar-sm" aria-hidden="true">
							<?php if ( $patient['avatar_url'] ) : ?>
								<img src="<?php echo esc_url( $patient['avatar_url'] ); ?>" alt="">
							<?php else : ?>
								<?php
								$dak_patient_name_parts = preg_split( '/\s+/', trim( $patient['name'] ) );
								$dak_patient_initials    = '';
								foreach ( array_slice( $dak_patient_name_parts, 0, 2 ) as $dak_name_part ) {
									if ( '' !== $dak_name_part ) {
										$dak_patient_initials .= mb_strtoupper( mb_substr( $dak_name_part, 0, 1 ) );
									}
								}
								echo esc_html( '' !== $dak_patient_initials ? $dak_patient_initials : '?' );
								?>
							<?php endif; ?>
						</span>
						<span class="dak-admin-record-row-info">
							<strong><?php echo esc_html( $patient['name'] ); ?></strong>
							<span class="dak-admin-record-row-id"><?php echo esc_html( $patient['email'] ); ?></span>
						</span>
						<span class="dak-admin-record-row-tags">
							<span class="dak-status-pill dak-status-pill-outline">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: number of visits. */
										_n( '%d visit', '%d visits', $patient['visit_count'], 'doctor-ak-portal' ),
										$patient['visit_count']
									)
								);
								?>
							</span>
							<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-active">
								<?php echo esc_html( sprintf( /* translators: %s: registration date. */ __( 'Since %s', 'doctor-ak-portal' ), $patient['registered_date'] ) ); ?>
							</span>
						</span>
						<span class="dak-admin-record-row-actions">
							<button
								type="button"
								class="dak-icon-button"
								data-dak-edit-patient
								data-patient-id="<?php echo esc_attr( $patient['id'] ); ?>"
								data-first-name="<?php echo esc_attr( $patient['first_name'] ); ?>"
								data-last-name="<?php echo esc_attr( $patient['last_name'] ); ?>"
								data-email="<?php echo esc_attr( $patient['email'] ); ?>"
								<?php $dak_edit_patient_phone_parts = \DoctorAKPortal\Includes\Phone::split( $patient['phone'] ); ?>
								data-phone-code="<?php echo esc_attr( $dak_edit_patient_phone_parts['dial_code'] ); ?>"
								data-phone-number="<?php echo esc_attr( $dak_edit_patient_phone_parts['number'] ); ?>"
								title="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
								aria-label="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
							><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 3.5a1.7 1.7 0 0 1 2.4 2.4L6.5 15.3l-3 .7.7-3 9.3-9.3z"/></svg></button>
						</span>
					</div>
				</div>
			<?php endforeach; ?>
			<p class="dak-empty-state dak-hidden" id="dak-patient-list-no-results"><?php esc_html_e( 'No patients match your search.', 'doctor-ak-portal' ); ?></p>
		</div>
	<?php endif; ?>
</section>
