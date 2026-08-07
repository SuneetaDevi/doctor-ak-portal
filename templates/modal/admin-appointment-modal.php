<?php
/**
 * Template: Admin "Add/Edit Appointment" modal for the Appointments table —
 * lets an admin fully manage an appointment (doctor, patient/guest, service,
 * type, date/time, status, payment), via Appointment_Handler's admin AJAX
 * endpoints.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $doctor_options  Doctor user ID => display name.
 * @var array $patient_options Patient user ID => display name.
 * @var array $status_options  Status slug => label, see Appointments::status_options().
 * @var array $services        [doctor_id][type] => [{id, name, charge}, ...], see Admin_Dashboard::services_by_doctor_and_type().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-modal" id="dak-admin-appointment-modal" aria-hidden="true" data-services="<?php echo esc_attr( wp_json_encode( $services ) ); ?>">
	<div class="dak-modal-overlay" data-dak-admin-appointment-modal-close></div>

	<div class="dak-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-admin-appointment-modal-title">
		<button type="button" class="dak-modal-close" data-dak-admin-appointment-modal-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>

		<div class="dak-modal-header">
			<h2 id="dak-admin-appointment-modal-title"><?php esc_html_e( 'Add Appointment', 'doctor-ak-portal' ); ?></h2>
		</div>

		<div class="dak-alert dak-alert-error dak-hidden" id="dak-admin-appointment-general-error" role="alert"></div>

		<input type="hidden" id="dak-admin-appointment-id" value="0">

		<div class="dak-field-row">
			<div class="dak-field">
				<label for="dak-admin-appointment-doctor"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-appointment-doctor">
					<option value=""><?php esc_html_e( 'Select a doctor…', 'doctor-ak-portal' ); ?></option>
					<?php foreach ( $doctor_options as $doctor_id => $doctor_option ) : ?>
						<option value="<?php echo esc_attr( $doctor_id ); ?>" <?php disabled( $doctor_option['is_disabled'] ); ?>><?php echo esc_html( $doctor_option['is_disabled'] ? sprintf( __( '%s (deactivated)', 'doctor-ak-portal' ), $doctor_option['name'] ) : $doctor_option['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<span class="dak-field-error" data-field="doctor_id"></span>
			</div>
			<div class="dak-field">
				<label for="dak-admin-appointment-type"><?php esc_html_e( 'Type', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-appointment-type">
					<option value="clinic"><?php esc_html_e( 'Onsite (Clinic)', 'doctor-ak-portal' ); ?></option>
					<option value="video"><?php esc_html_e( 'Online (Video)', 'doctor-ak-portal' ); ?></option>
				</select>
			</div>
		</div>

		<div class="dak-field">
			<label for="dak-admin-appointment-service"><?php esc_html_e( 'Service (optional)', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-appointment-service">
				<option value=""><?php esc_html_e( 'No service / free booking', 'doctor-ak-portal' ); ?></option>
			</select>
		</div>

		<div class="dak-field">
			<label for="dak-admin-appointment-patient"><?php esc_html_e( 'Registered Patient (optional)', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-appointment-patient">
				<option value=""><?php esc_html_e( '— Guest (enter details below) —', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $patient_options as $patient_id => $patient_name ) : ?>
					<option value="<?php echo esc_attr( $patient_id ); ?>"><?php echo esc_html( $patient_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div id="dak-admin-appointment-guest-fields">
			<div class="dak-field">
				<label for="dak-admin-appointment-guest-name"><?php esc_html_e( 'Guest Name', 'doctor-ak-portal' ); ?></label>
				<input type="text" id="dak-admin-appointment-guest-name">
				<span class="dak-field-error" data-field="guest_name"></span>
			</div>
			<div class="dak-field-row">
				<div class="dak-field">
					<label for="dak-admin-appointment-guest-email"><?php esc_html_e( 'Guest Email', 'doctor-ak-portal' ); ?></label>
					<input type="email" id="dak-admin-appointment-guest-email">
					<span class="dak-field-error" data-field="guest_email"></span>
				</div>
				<div class="dak-field">
					<label for="dak-admin-appointment-guest-phone"><?php esc_html_e( 'Guest Phone', 'doctor-ak-portal' ); ?></label>
					<input type="tel" id="dak-admin-appointment-guest-phone">
				</div>
			</div>
		</div>

		<div class="dak-field-row">
			<div class="dak-field">
				<label for="dak-admin-appointment-date"><?php esc_html_e( 'Date', 'doctor-ak-portal' ); ?></label>
				<input type="date" id="dak-admin-appointment-date">
				<span class="dak-field-error" data-field="date"></span>
			</div>
			<div class="dak-field">
				<label for="dak-admin-appointment-time"><?php esc_html_e( 'Time', 'doctor-ak-portal' ); ?></label>
				<input type="time" id="dak-admin-appointment-time">
				<span class="dak-field-error" data-field="time"></span>
			</div>
		</div>

		<div class="dak-field-row">
			<div class="dak-field">
				<label for="dak-admin-appointment-status"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-appointment-status">
					<?php foreach ( $status_options as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="dak-field">
				<label for="dak-admin-appointment-payment-status"><?php esc_html_e( 'Payment', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-appointment-payment-status">
					<option value="pending"><?php esc_html_e( 'Ask the patient to pay (Pending)', 'doctor-ak-portal' ); ?></option>
					<option value="paid"><?php esc_html_e( "I've collected payment (Paid)", 'doctor-ak-portal' ); ?></option>
				</select>
				<span class="dak-field-error" data-field="payment_status"></span>
			</div>
		</div>

		<div class="dak-field">
			<label for="dak-admin-appointment-payment-mode"><?php esc_html_e( 'Payment Mode', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-appointment-payment-mode">
				<option value="manual"><?php esc_html_e( 'Manual', 'doctor-ak-portal' ); ?></option>
				<option value="online"><?php esc_html_e( 'Online', 'doctor-ak-portal' ); ?></option>
			</select>
		</div>

		<div class="dak-field">
			<label for="dak-admin-appointment-notes"><?php esc_html_e( 'Notes (optional)', 'doctor-ak-portal' ); ?></label>
			<textarea id="dak-admin-appointment-notes" rows="2"></textarea>
		</div>

		<button type="button" class="dak-button dak-button-primary dak-button-block" id="dak-admin-appointment-save">
			<span class="dak-button-label"><?php esc_html_e( 'Save Appointment', 'doctor-ak-portal' ); ?></span>
		</button>
	</div>
</div>

<div class="dak-portal dak-modal" id="dak-admin-appointment-view-modal" aria-hidden="true">
	<div class="dak-modal-overlay" data-dak-admin-appointment-view-modal-close></div>

	<div class="dak-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-admin-appointment-view-modal-title">
		<button type="button" class="dak-modal-close" data-dak-admin-appointment-view-modal-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>

		<div class="dak-modal-header">
			<h2 id="dak-admin-appointment-view-modal-title"><?php esc_html_e( 'Appointment Details', 'doctor-ak-portal' ); ?></h2>
		</div>

		<table class="dak-admin-users-table">
			<tbody>
				<tr><td><?php esc_html_e( 'Patient', 'doctor-ak-portal' ); ?></td><td id="dak-admin-appointment-view-patient"></td></tr>
				<tr><td><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></td><td id="dak-admin-appointment-view-doctor"></td></tr>
				<tr><td><?php esc_html_e( 'Type', 'doctor-ak-portal' ); ?></td><td id="dak-admin-appointment-view-type"></td></tr>
				<tr><td><?php esc_html_e( 'Service', 'doctor-ak-portal' ); ?></td><td id="dak-admin-appointment-view-service"></td></tr>
				<tr><td><?php esc_html_e( 'Date & Time', 'doctor-ak-portal' ); ?></td><td id="dak-admin-appointment-view-datetime"></td></tr>
				<tr><td><?php esc_html_e( 'Charges', 'doctor-ak-portal' ); ?></td><td id="dak-admin-appointment-view-charge"></td></tr>
				<tr><td><?php esc_html_e( 'Payment Mode', 'doctor-ak-portal' ); ?></td><td id="dak-admin-appointment-view-payment-mode"></td></tr>
				<tr><td><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></td><td id="dak-admin-appointment-view-status"></td></tr>
				<tr><td><?php esc_html_e( 'Notes', 'doctor-ak-portal' ); ?></td><td id="dak-admin-appointment-view-notes"></td></tr>
			</tbody>
		</table>

		<a class="dak-button dak-button-primary dak-button-block" id="dak-admin-appointment-view-print" href="#" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Print', 'doctor-ak-portal' ); ?>
		</a>
	</div>
</div>

<div class="dak-portal dak-modal" id="dak-admin-process-refund-modal" aria-hidden="true">
	<div class="dak-modal-overlay" data-dak-admin-process-refund-modal-close></div>

	<div class="dak-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-admin-process-refund-title">
		<button type="button" class="dak-modal-close" data-dak-admin-process-refund-modal-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>

		<div class="dak-modal-header">
			<h2 id="dak-admin-process-refund-title"><?php esc_html_e( 'Process Refund', 'doctor-ak-portal' ); ?></h2>
		</div>

		<div class="dak-alert dak-alert-error dak-hidden" id="dak-admin-process-refund-general-error" role="alert"></div>

		<input type="hidden" id="dak-admin-process-refund-appointment-id" value="0">

		<table class="dak-admin-users-table">
			<tbody>
				<tr><td><?php esc_html_e( 'Patient', 'doctor-ak-portal' ); ?></td><td id="dak-admin-process-refund-patient"></td></tr>
				<tr><td><?php esc_html_e( 'Reason', 'doctor-ak-portal' ); ?></td><td id="dak-admin-process-refund-reason"></td></tr>
				<tr><td><?php esc_html_e( 'Original Charge', 'doctor-ak-portal' ); ?></td><td id="dak-admin-process-refund-charge"></td></tr>
			</tbody>
		</table>

		<div class="dak-field">
			<label for="dak-admin-process-refund-amount"><?php esc_html_e( 'Refund Amount (PKR)', 'doctor-ak-portal' ); ?></label>
			<input type="number" min="0" step="0.01" id="dak-admin-process-refund-amount">
			<span class="dak-field-error" data-field="amount"></span>
			<p class="dak-field-hint"><?php esc_html_e( 'Defaults to the full charge. Enter a lower amount for a partial refund.', 'doctor-ak-portal' ); ?></p>
		</div>

		<button type="button" class="dak-button dak-button-primary dak-button-block" id="dak-admin-process-refund-save">
			<span class="dak-button-label"><?php esc_html_e( 'Process Refund via Swich', 'doctor-ak-portal' ); ?></span>
		</button>
	</div>
</div>
