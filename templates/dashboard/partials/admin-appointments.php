<?php
/**
 * Template: "Appointments" admin table — every booking across every doctor
 * and patient, with service, charges, payment mode, and status, plus
 * Edit/View/Print/Delete actions.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $appointments         Rows from Appointments::all_for_admin().
 * @var string $filtered_patient     Name of the patient being filtered to, or '' if unfiltered.
 * @var string $appointments_url     Unfiltered URL of this section, for the filter form and "Clear filter" link.
 * @var array  $status_options       Status slug => label, see Appointments::status_options().
 * @var array  $payment_mode_options Payment mode slug => label, see Appointments::payment_mode_options().
 * @var array  $filters              Active filter values: patient_id, date, status, payment_mode.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_appt_icons = array(
	'edit'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 3.5a1.7 1.7 0 0 1 2.4 2.4L6.5 15.3l-3 .7.7-3 9.3-9.3z"/></svg>',
	'view'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 10s2.7-5.5 8-5.5S18 10 18 10s-2.7 5.5-8 5.5S2 10 2 10z"/><circle cx="10" cy="10" r="2.2"/></svg>',
	'print'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5.5 7V3h9v4"/><rect x="3.5" y="7" width="13" height="6.5" rx="1"/><path d="M5.5 12.5h9V17h-9v-4.5z"/></svg>',
	'delete' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h12M8 6V4.5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1V6M6 6l.6 9a1.5 1.5 0 0 0 1.5 1.4h3.8a1.5 1.5 0 0 0 1.5-1.4L14 6"/></svg>',
);
?>
<div class="dak-dashboard-greeting dak-admin-users-header">
	<div>
		<h1><?php esc_html_e( 'Appointments', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Every appointment booked across the clinic, with service, charges, and payment status.', 'doctor-ak-portal' ); ?></p>
	</div>
	<button type="button" class="dak-button dak-button-primary" id="dak-admin-appointment-add"><?php esc_html_e( '+ Add Appointment', 'doctor-ak-portal' ); ?></button>
</div>

<?php if ( '' !== $filtered_patient ) : ?>
	<div class="dak-alert dak-alert-success">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: patient's name. */
				__( 'Showing appointments for %s.', 'doctor-ak-portal' ),
				$filtered_patient
			)
		);
		?>
		<?php if ( $appointments_url ) : ?>
			<a class="dak-link" href="<?php echo esc_url( $appointments_url ); ?>"><?php esc_html_e( 'Clear filter', 'doctor-ak-portal' ); ?></a>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php
$dak_appt_has_filters = '' !== $filters['date'] || '' !== $filters['status'] || '' !== $filters['payment_mode'];
?>
<section class="dak-dashboard-card">
	<form method="get" action="<?php echo esc_url( $appointments_url ); ?>" class="dak-field-row">
		<?php if ( $filters['patient_id'] > 0 ) : ?>
			<input type="hidden" name="patient_id" value="<?php echo esc_attr( $filters['patient_id'] ); ?>">
		<?php endif; ?>

		<div class="dak-field">
			<label for="dak-admin-appointments-filter-date"><?php esc_html_e( 'Date', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-admin-appointments-filter-date" name="date" value="<?php echo esc_attr( $filters['date'] ); ?>">
		</div>

		<div class="dak-field">
			<label for="dak-admin-appointments-filter-status"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-appointments-filter-status" name="status">
				<option value=""><?php esc_html_e( 'All statuses', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $status_options as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filters['status'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="dak-field">
			<label for="dak-admin-appointments-filter-payment-mode"><?php esc_html_e( 'Payment Mode', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-appointments-filter-payment-mode" name="payment_mode">
				<option value=""><?php esc_html_e( 'All payment modes', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $payment_mode_options as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filters['payment_mode'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="dak-admin-filter-actions">
			<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Filter', 'doctor-ak-portal' ); ?></button>
			<?php if ( $dak_appt_has_filters ) : ?>
				<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $filters['patient_id'] > 0 ? add_query_arg( 'patient_id', $filters['patient_id'], $appointments_url ) : $appointments_url ); ?>"><?php esc_html_e( 'Clear', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	</form>
</section>

<section class="dak-dashboard-card dak-admin-users-card">
<?php if ( empty( $appointments ) ) : ?>
	<p class="dak-empty-state"><?php esc_html_e( 'No appointments have been booked yet.', 'doctor-ak-portal' ); ?></p>
<?php else : ?>
	<div class="dak-table-scroll">
		<table class="dak-admin-users-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Appointment Details', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Date and Time', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Service', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Charges', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Payment Mode', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
					<th class="dak-admin-users-actions-col"><?php esc_html_e( 'Action', 'doctor-ak-portal' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $appointments as $row ) : ?>
					<tr data-appointment-row="<?php echo esc_attr( $row['id'] ); ?>">
						<td data-label="<?php esc_attr_e( 'Appointment Details', 'doctor-ak-portal' ); ?>">
							<div class="dak-admin-appt-details">
								<span class="dak-admin-appt-avatar" aria-hidden="true"><?php echo esc_html( $row['patient_initials'] ); ?></span>
								<span>
									<strong><?php echo esc_html( $row['patient_name'] ); ?></strong><br>
									<span class="dak-clinic-card-meta"><?php echo esc_html( sprintf( /* translators: %s: doctor name. */ __( 'Doctor: Dr. %s', 'doctor-ak-portal' ), $row['doctor_name'] ) ); ?></span>
								</span>
							</div>
						</td>
						<td data-label="<?php esc_attr_e( 'Date and Time', 'doctor-ak-portal' ); ?>"><?php echo esc_html( $row['date'] . ' ' . $row['time'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Service', 'doctor-ak-portal' ); ?>"><?php echo esc_html( '' !== $row['service_name'] ? $row['service_name'] : $row['type_label'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Charges', 'doctor-ak-portal' ); ?>">
							<?php echo $row['charge'] > 0 ? esc_html( 'PKR' . number_format( $row['charge'], 0 ) . '/-' ) : esc_html__( 'Free', 'doctor-ak-portal' ); ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Payment Mode', 'doctor-ak-portal' ); ?>">
							<?php echo esc_html( 'online' === $row['payment_mode'] ? __( 'Online', 'doctor-ak-portal' ) : __( 'Manual', 'doctor-ak-portal' ) ); ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>">
							<span class="dak-status-badge <?php echo esc_attr( $row['status_badge_class'] ); ?>"><?php echo esc_html( $row['status_label'] ); ?></span>
						</td>
						<td class="dak-admin-users-actions-col">
							<div class="dak-admin-users-actions">
								<button
									type="button"
									class="dak-icon-button"
									data-admin-appointment-edit
									data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"
									data-doctor-id="<?php echo esc_attr( $row['doctor_id'] ); ?>"
									data-patient-id="<?php echo esc_attr( $row['patient_id'] ); ?>"
									data-guest-name="<?php echo esc_attr( $row['guest_name'] ); ?>"
									data-guest-email="<?php echo esc_attr( $row['guest_email'] ); ?>"
									data-guest-phone="<?php echo esc_attr( $row['guest_phone'] ); ?>"
									data-type="<?php echo esc_attr( $row['type'] ); ?>"
									data-service-id="<?php echo esc_attr( $row['service_id'] ); ?>"
									data-date="<?php echo esc_attr( $row['date'] ); ?>"
									data-time="<?php echo esc_attr( $row['time'] ); ?>"
									data-status="<?php echo esc_attr( $row['status'] ); ?>"
									data-payment-status="<?php echo esc_attr( $row['payment_status'] ); ?>"
									data-payment-mode="<?php echo esc_attr( $row['payment_mode'] ); ?>"
									data-notes="<?php echo esc_attr( $row['notes'] ); ?>"
									title="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
									aria-label="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
								><?php echo $dak_appt_icons['edit']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
								<button
									type="button"
									class="dak-icon-button"
									data-admin-appointment-view
									data-patient-name="<?php echo esc_attr( $row['patient_name'] ); ?>"
									data-doctor-name="<?php echo esc_attr( $row['doctor_name'] ); ?>"
									data-type-label="<?php echo esc_attr( $row['type_label'] ); ?>"
									data-service-name="<?php echo esc_attr( '' !== $row['service_name'] ? $row['service_name'] : '—' ); ?>"
									data-date="<?php echo esc_attr( $row['date'] ); ?>"
									data-time="<?php echo esc_attr( $row['time'] ); ?>"
									data-charge="<?php echo esc_attr( $row['charge'] > 0 ? 'PKR' . number_format( $row['charge'], 0 ) . '/-' : __( 'Free', 'doctor-ak-portal' ) ); ?>"
									data-payment-mode="<?php echo esc_attr( 'online' === $row['payment_mode'] ? __( 'Online', 'doctor-ak-portal' ) : __( 'Manual', 'doctor-ak-portal' ) ); ?>"
									data-status-label="<?php echo esc_attr( $row['status_label'] ); ?>"
									data-notes="<?php echo esc_attr( '' !== $row['notes'] ? $row['notes'] : '—' ); ?>"
									data-print-url="<?php echo esc_url( \DoctorAKPortal\Frontend\Appointment_Handler::print_url( $row['id'] ) ); ?>"
									title="<?php esc_attr_e( 'View', 'doctor-ak-portal' ); ?>"
									aria-label="<?php esc_attr_e( 'View', 'doctor-ak-portal' ); ?>"
								><?php echo $dak_appt_icons['view']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
								<a
									class="dak-icon-button"
									href="<?php echo esc_url( \DoctorAKPortal\Frontend\Appointment_Handler::print_url( $row['id'] ) ); ?>"
									target="_blank"
									rel="noopener noreferrer"
									title="<?php esc_attr_e( 'Print', 'doctor-ak-portal' ); ?>"
									aria-label="<?php esc_attr_e( 'Print', 'doctor-ak-portal' ); ?>"
								><?php echo $dak_appt_icons['print']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
								<button
									type="button"
									class="dak-icon-button dak-icon-button-danger"
									data-admin-appointment-delete
									data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"
									title="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
									aria-label="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
								><?php echo $dak_appt_icons['delete']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
</section>
