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
<div class="dak-dashboard-card-header">
	<h2><?php esc_html_e( 'Patients', 'doctor-ak-portal' ); ?></h2>
	<button type="button" class="dak-button dak-button-secondary dak-button-sm" id="dak-doctor-add-patient-open"><?php esc_html_e( '+ Add Patient', 'doctor-ak-portal' ); ?></button>
</div>

<?php if ( empty( $patients ) ) : ?>
	<p class="dak-empty-state"><?php esc_html_e( 'No patients yet.', 'doctor-ak-portal' ); ?></p>
<?php else : ?>
	<div class="dak-table-wrap">
		<table class="dak-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Email', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Phone', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Clinic', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Last Visit', 'doctor-ak-portal' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $patients as $patient ) : ?>
					<tr>
						<td><?php echo esc_html( $patient['name'] ); ?></td>
						<td><?php echo esc_html( $patient['email'] ); ?></td>
						<td><?php echo esc_html( $patient['phone'] ); ?></td>
						<td><?php echo esc_html( $patient['clinic_name'] ? $patient['clinic_name'] : '—' ); ?></td>
						<td><?php echo esc_html( $patient['last_visit'] ? mysql2date( get_option( 'date_format' ), $patient['last_visit'] ) : '—' ); ?></td>
						<td>
							<button
								type="button"
								class="dak-button dak-button-secondary dak-button-sm"
								data-dak-edit-patient
								data-patient-id="<?php echo esc_attr( $patient['id'] ); ?>"
								data-first-name="<?php echo esc_attr( $patient['first_name'] ); ?>"
								data-last-name="<?php echo esc_attr( $patient['last_name'] ); ?>"
								data-email="<?php echo esc_attr( $patient['email'] ); ?>"
								<?php $dak_edit_patient_phone_parts = \DoctorAKPortal\Includes\Phone::split( $patient['phone'] ); ?>
								data-phone-code="<?php echo esc_attr( $dak_edit_patient_phone_parts['dial_code'] ); ?>"
								data-phone-number="<?php echo esc_attr( $dak_edit_patient_phone_parts['number'] ); ?>"
							><?php esc_html_e( 'Edit', 'doctor-ak-portal' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
