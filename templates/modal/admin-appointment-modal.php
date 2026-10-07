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
 * @var array $services        [doctor_id][type] => [{id, name, charge, clinic_charges}, ...], see Admin_Dashboard::services_by_doctor_and_type().
 * @var array $clinics         [doctor_id] => [{id, name, place, location_id}, ...] — physical clinics, see Admin_Dashboard::physical_clinics_by_doctor().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-modal" id="dak-admin-appointment-modal" aria-hidden="true" data-services="<?php echo esc_attr( wp_json_encode( $services ) ); ?>" data-clinics="<?php echo esc_attr( wp_json_encode( isset( $clinics ) ? $clinics : array() ) ); ?>">
	<div class="dak-modal-overlay" data-dak-admin-appointment-modal-close></div>

	<?php
	// One dialog, three modes (data-mode on the dialog, set by
	// doctor-ak-admin-appointments.js): "add" and "edit" show the full form;
	// "reschedule" shows a read-only summary plus only the date/time picker
	// ([data-edit-only] sections are hidden, [data-reschedule-only] shown).
	?>
	<div class="dak-modal-dialog dak-modal-dialog-form" role="dialog" aria-modal="true" aria-labelledby="dak-admin-appointment-modal-title" data-mode="add">
		<div class="dak-modal-header">
			<h2 id="dak-admin-appointment-modal-title"><?php esc_html_e( 'Add Appointment', 'doctor-ak-portal' ); ?></h2>
			<p class="dak-modal-subtitle" id="dak-admin-appointment-modal-subtitle" data-reschedule-only hidden><?php esc_html_e( 'Choose a new date and available time.', 'doctor-ak-portal' ); ?></p>
			<button type="button" class="dak-modal-close" data-dak-admin-appointment-modal-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>
		</div>
		<div class="dak-modal-body">
			<div class="dak-alert dak-alert-error dak-hidden" id="dak-admin-appointment-general-error" role="alert"></div>
			<input type="hidden" id="dak-admin-appointment-id" value="0">

			<section class="dak-resched-summary" data-reschedule-only hidden aria-labelledby="dak-resched-summary-title">
				<h3 class="dak-visually-hidden" id="dak-resched-summary-title"><?php esc_html_e( 'Current appointment', 'doctor-ak-portal' ); ?></h3>
				<dl class="dak-resched-summary-list">
					<div>
						<dt><?php esc_html_e( 'Patient', 'doctor-ak-portal' ); ?></dt>
						<dd><span id="dak-resched-patient"></span> <span class="dak-resched-muted" id="dak-resched-label"></span></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></dt>
						<dd id="dak-resched-doctor"></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Service and visit', 'doctor-ak-portal' ); ?></dt>
						<dd id="dak-resched-visit"></dd>
					</div>
					<div data-optional>
						<dt><?php esc_html_e( 'Clinic', 'doctor-ak-portal' ); ?></dt>
						<dd id="dak-resched-clinic"></dd>
					</div>
					<div class="dak-resched-summary-current">
						<dt><?php esc_html_e( 'Current date and time', 'doctor-ak-portal' ); ?></dt>
						<dd id="dak-resched-current"></dd>
					</div>
				</dl>
			</section>

			<fieldset class="dak-form-section" data-edit-only>
				<legend><?php esc_html_e( 'Patient', 'doctor-ak-portal' ); ?></legend>
				<div class="dak-field">
					<label for="dak-admin-appointment-patient"><?php esc_html_e( 'Registered patient', 'doctor-ak-portal' ); ?> <span class="dak-optional"><?php esc_html_e( '(optional)', 'doctor-ak-portal' ); ?></label>
					<select id="dak-admin-appointment-patient" class="dak-select-searchable" data-placeholder="<?php esc_attr_e( 'Search patients…', 'doctor-ak-portal' ); ?>">
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
			</fieldset>
			<fieldset class="dak-form-section" data-edit-only>
				<legend><?php esc_html_e( 'Doctor and services', 'doctor-ak-portal' ); ?></legend>
				<div class="dak-field-row">
					<div class="dak-field">
						<label for="dak-admin-appointment-doctor"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></label>
						<select id="dak-admin-appointment-doctor" class="dak-select-searchable" data-placeholder="<?php esc_attr_e( 'Search doctors…', 'doctor-ak-portal' ); ?>">
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
				<?php // Clinic visits only: which of the chosen doctor's clinics (filled by doctor-ak-admin-appointments.js). ?>
				<div class="dak-field dak-hidden" id="dak-admin-appointment-clinic-field">
					<label for="dak-admin-appointment-clinic"><?php esc_html_e( 'Clinic', 'doctor-ak-portal' ); ?> <span class="dak-required">*</span></label>
					<select id="dak-admin-appointment-clinic" aria-describedby="dak-admin-appointment-clinic-hint" required aria-required="true">
						<option value=""><?php esc_html_e( 'Select a clinic…', 'doctor-ak-portal' ); ?></option>
					</select>
					<span class="dak-field-error" data-field="clinic_id"></span>
					<p class="dak-field-hint" id="dak-admin-appointment-clinic-hint"><?php esc_html_e( 'Services, prices and time slots are shown for this clinic.', 'doctor-ak-portal' ); ?></p>
				</div>
				<p class="dak-alert dak-alert-error dak-hidden" id="dak-admin-appointment-no-clinic-note" role="alert"><?php esc_html_e( 'This doctor has no clinic set up yet, so a clinic visit can’t be booked. Add a clinic for the doctor first, or choose Online (Video).', 'doctor-ak-portal' ); ?></p>
				<p class="dak-field-hint dak-hidden" id="dak-admin-appointment-video-fee-note"><?php esc_html_e( 'Video consultations have no services — they are charged at the doctor\'s video consultation fee.', 'doctor-ak-portal' ); ?></p>
				<div class="dak-field" id="dak-admin-appointment-service-field">
					<label for="dak-admin-appointment-service"><?php esc_html_e( 'Services', 'doctor-ak-portal' ); ?> <span class="dak-required">*</span></label>
					<select id="dak-admin-appointment-service" class="dak-select-searchable" multiple data-placeholder="<?php esc_attr_e( 'Select at least one service…', 'doctor-ak-portal' ); ?>" aria-required="true"></select>
					<span class="dak-field-error" data-field="service_ids"></span>
					<span class="dak-field-hint" id="dak-admin-appointment-service-total"></span>
				</div>
			</fieldset>
			<fieldset class="dak-form-section dak-schedule-section">
				<legend data-edit-only><?php esc_html_e( 'Date and time', 'doctor-ak-portal' ); ?></legend>
				<div class="dak-field">
					<label for="dak-admin-appointment-date" id="dak-admin-appointment-date-label" data-edit-label="<?php esc_attr_e( 'Date', 'doctor-ak-portal' ); ?>" data-reschedule-label="<?php esc_attr_e( 'New appointment date', 'doctor-ak-portal' ); ?>"><?php esc_html_e( 'Date', 'doctor-ak-portal' ); ?></label>
					<input type="date" id="dak-admin-appointment-date" aria-describedby="dak-admin-appointment-date-readout">
					<p class="dak-resched-date-readout" id="dak-admin-appointment-date-readout" data-reschedule-only hidden></p>
					<span class="dak-field-error" data-field="date"></span>
				</div>
				<div class="dak-field">
					<span class="dak-field-label" id="dak-admin-appointment-time-label" data-edit-label="<?php esc_attr_e( 'Time', 'doctor-ak-portal' ); ?>" data-reschedule-label="<?php esc_attr_e( 'Available times', 'doctor-ak-portal' ); ?>"><?php esc_html_e( 'Time', 'doctor-ak-portal' ); ?></span>
					<input type="hidden" id="dak-admin-appointment-time">
					<?php // Directly under the label, so a "time no longer available" message is seen before the (possibly long) list. ?>
					<span class="dak-field-error dak-slot-error" data-field="time" id="dak-admin-appointment-time-error" role="alert"></span>
					<p class="dak-field-hint dak-hidden" id="dak-admin-appointment-slots-hint"><?php esc_html_e( 'Choose a doctor and date to see open slots.', 'doctor-ak-portal' ); ?></p>
					<?php // Loading / empty / error messages for the slot list (polite live region). ?>
					<div class="dak-slot-status" id="dak-admin-appointment-slots-status" role="status" aria-live="polite"></div>
					<div class="dak-slot-groups" id="dak-admin-appointment-slots-groups" role="radiogroup" aria-labelledby="dak-admin-appointment-time-label"></div>
					<p class="dak-empty-state dak-hidden" id="dak-admin-appointment-no-slots"><?php esc_html_e( 'No time slots are configured for this doctor on this date.', 'doctor-ak-portal' ); ?></p>
				</div>
			</fieldset>
			<fieldset class="dak-form-section" data-edit-only>
				<legend><?php esc_html_e( 'Status and payment', 'doctor-ak-portal' ); ?></legend>
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
					<label for="dak-admin-appointment-notes"><?php esc_html_e( 'Notes', 'doctor-ak-portal' ); ?> <span class="dak-optional"><?php esc_html_e( '(optional)', 'doctor-ak-portal' ); ?></label>
					<textarea id="dak-admin-appointment-notes" rows="2"></textarea>
				</div>
			</fieldset>
		</div>
		<div class="dak-modal-footer">
			<?php // Reschedule mode: what changes (Current → New), or what's still missing. ?>
			<div class="dak-resched-compare" id="dak-resched-compare" data-reschedule-only hidden aria-live="polite">
				<p class="dak-resched-compare-hint" id="dak-resched-hint"></p>
				<dl class="dak-resched-compare-list" id="dak-resched-compare-list" hidden>
					<div><dt><?php esc_html_e( 'Current', 'doctor-ak-portal' ); ?></dt><dd id="dak-resched-compare-current"></dd></div>
					<div class="is-new"><dt><?php esc_html_e( 'New', 'doctor-ak-portal' ); ?></dt><dd id="dak-resched-compare-new"></dd></div>
				</dl>
			</div>
			<div class="dak-modal-footer-actions">
				<button type="button" class="dak-button dak-button-secondary" data-dak-admin-appointment-modal-close><?php esc_html_e( 'Cancel', 'doctor-ak-portal' ); ?></button>
				<button type="button" class="dak-button dak-button-primary" id="dak-admin-appointment-save" aria-describedby="dak-resched-hint">
					<span class="dak-button-label"><?php esc_html_e( 'Save Appointment', 'doctor-ak-portal' ); ?></span>
				</button>
			</div>
		</div>
	</div>
</div>

<div class="dak-portal dak-modal" id="dak-admin-appointment-view-modal" aria-hidden="true">
	<div class="dak-modal-overlay" data-dak-admin-appointment-view-modal-close></div>

	<?php // Standard dialog layout: fixed header and footer, the details scroll between them. ?>
	<div class="dak-modal-dialog dak-modal-dialog-form" role="dialog" aria-modal="true" aria-labelledby="dak-admin-appointment-view-modal-title">
		<div class="dak-modal-header">
			<h2 id="dak-admin-appointment-view-modal-title"><?php esc_html_e( 'Appointment details', 'doctor-ak-portal' ); ?></h2>
			<button type="button" class="dak-modal-close" data-dak-admin-appointment-view-modal-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>
		</div>

		<div class="dak-modal-body">
			<?php // Filled by wireView() in doctor-ak-admin-appointments.js from the clicked row's data-* attributes. ?>
			<div class="dak-detail-group">
				<h3 class="dak-detail-group-title"><?php esc_html_e( 'Patient', 'doctor-ak-portal' ); ?></h3>
				<dl class="dak-detail-list">
					<div><dt><?php esc_html_e( 'Name', 'doctor-ak-portal' ); ?></dt><dd id="dak-admin-appointment-view-patient"></dd></div>
					<div><dt><?php esc_html_e( 'Appointment ID', 'doctor-ak-portal' ); ?></dt><dd id="dak-admin-appointment-view-id"></dd></div>
					<div><dt><?php esc_html_e( 'Phone', 'doctor-ak-portal' ); ?></dt><dd id="dak-admin-appointment-view-phone"></dd></div>
					<div data-optional><dt><?php esc_html_e( 'Age', 'doctor-ak-portal' ); ?></dt><dd id="dak-admin-appointment-view-age"></dd></div>
				</dl>
			</div>

			<div class="dak-detail-group">
				<h3 class="dak-detail-group-title"><?php esc_html_e( 'Schedule and visit', 'doctor-ak-portal' ); ?></h3>
				<dl class="dak-detail-list">
					<div><dt><?php esc_html_e( 'Date', 'doctor-ak-portal' ); ?></dt><dd id="dak-admin-appointment-view-date"></dd></div>
					<div><dt><?php esc_html_e( 'Time', 'doctor-ak-portal' ); ?></dt><dd id="dak-admin-appointment-view-time"></dd></div>
					<div><dt><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></dt><dd id="dak-admin-appointment-view-doctor"></dd></div>
					<div><dt><?php esc_html_e( 'Service', 'doctor-ak-portal' ); ?></dt><dd id="dak-admin-appointment-view-service"></dd></div>
					<div><dt><?php esc_html_e( 'Visit type', 'doctor-ak-portal' ); ?></dt><dd id="dak-admin-appointment-view-type"></dd></div>
					<div data-optional><dt><?php esc_html_e( 'Location', 'doctor-ak-portal' ); ?></dt><dd id="dak-admin-appointment-view-location"></dd></div>
					<div><dt><?php esc_html_e( 'Appointment status', 'doctor-ak-portal' ); ?></dt><dd id="dak-admin-appointment-view-status"></dd></div>
				</dl>
			</div>

			<div class="dak-detail-group">
				<h3 class="dak-detail-group-title"><?php esc_html_e( 'Payment', 'doctor-ak-portal' ); ?></h3>
				<dl class="dak-detail-list">
					<div><dt><?php esc_html_e( 'Amount', 'doctor-ak-portal' ); ?></dt><dd id="dak-admin-appointment-view-charge"></dd></div>
					<div><dt><?php esc_html_e( 'Payment status', 'doctor-ak-portal' ); ?></dt><dd id="dak-admin-appointment-view-payment-status"></dd></div>
					<?php // payment_mode is set at booking/edit time only — it isn't updated when a payment is collected later, so it's labelled as such rather than as "paid via". ?>
					<div><dt><?php esc_html_e( 'Payment mode (set at booking)', 'doctor-ak-portal' ); ?></dt><dd id="dak-admin-appointment-view-payment-mode"></dd></div>
					<div data-optional><dt><?php esc_html_e( 'Online payment reference', 'doctor-ak-portal' ); ?></dt><dd id="dak-admin-appointment-view-order"></dd></div>
					<div data-optional><dt><?php esc_html_e( 'Refund', 'doctor-ak-portal' ); ?></dt><dd id="dak-admin-appointment-view-refund"></dd></div>
				</dl>
			</div>

			<div class="dak-detail-group">
				<h3 class="dak-detail-group-title"><?php esc_html_e( 'Notes', 'doctor-ak-portal' ); ?></h3>
				<p class="dak-detail-notes" id="dak-admin-appointment-view-notes"></p>
			</div>
		</div>

		<div class="dak-modal-footer">
			<a class="dak-button dak-button-primary" id="dak-admin-appointment-view-print" href="#" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Print slip', 'doctor-ak-portal' ); ?>
			</a>
		</div>
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
