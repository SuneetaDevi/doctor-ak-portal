<?php
/**
 * Template: "Appointments" admin section — every booking across every
 * doctor and patient, with service, charges, payment status, and
 * Edit/View/Print/Delete/Refund actions, styled as accent-bar cards.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $appointments            Rows from Appointments::all_for_admin().
 * @var string $filtered_patient        Name of the patient being filtered to, or '' if unfiltered.
 * @var string $appointments_url        Unfiltered URL of this section, for the filter form and "Clear filter" link.
 * @var array  $doctors                 Doctor users { ID, display_name }, for the filter's Doctor select.
 * @var array  $payment_status_options  Payment status slug => label.
 * @var array  $filters                 Active filter values: patient_id, date_from, date_to, doctor_id, payment_status.
 * @var bool   $is_receptionist         Whether the viewer is a Receptionist — gets View/Print/Mark Paid only, no Add/Edit/Delete/Refund.
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
	'refund' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10a6 6 0 1 1 1.8 4.3"/><path d="M4 14v-3.5H7.5"/><path d="M10 6.5v4l2.5 1.5"/></svg>',
	'paid'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l3.5 3.5L16 6"/></svg>',
);

$dak_appt_has_filters = '' !== $filters['date_from'] || '' !== $filters['date_to'] || $filters['doctor_id'] > 0 || '' !== $filters['payment_status'];
?>
<div class="dak-dashboard-greeting dak-admin-users-header">
	<div>
		<h1><?php esc_html_e( 'Appointments', 'doctor-ak-portal' ); ?></h1>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of appointments. */
					_n( '%d appointment', '%d appointments', count( $appointments ), 'doctor-ak-portal' ),
					count( $appointments )
				)
			);
			?>
		</p>
	</div>
	<?php if ( ! $is_receptionist ) : ?>
		<button type="button" class="dak-button dak-button-primary" id="dak-admin-appointment-add"><?php esc_html_e( '+ Add Appointment', 'doctor-ak-portal' ); ?></button>
	<?php endif; ?>
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

<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Filters', 'doctor-ak-portal' ); ?></h2>
	</div>

	<form method="get" action="<?php echo esc_url( $appointments_url ); ?>" class="dak-field-row">
		<input type="hidden" name="section" value="appointments">
		<?php if ( $filters['patient_id'] > 0 ) : ?>
			<input type="hidden" name="patient_id" value="<?php echo esc_attr( $filters['patient_id'] ); ?>">
		<?php endif; ?>

		<div class="dak-field">
			<label for="dak-admin-appointments-filter-date-from"><?php esc_html_e( 'From', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-admin-appointments-filter-date-from" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
		</div>

		<div class="dak-field">
			<label for="dak-admin-appointments-filter-date-to"><?php esc_html_e( 'To', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-admin-appointments-filter-date-to" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
		</div>

		<div class="dak-field">
			<label for="dak-admin-appointments-filter-doctor"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-appointments-filter-doctor" name="doctor_id">
				<option value="0"><?php esc_html_e( 'All doctors', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $doctors as $dak_doctor_option ) : ?>
					<option value="<?php echo esc_attr( $dak_doctor_option->ID ); ?>" <?php selected( $filters['doctor_id'], $dak_doctor_option->ID ); ?>><?php echo esc_html( sprintf( 'Dr. %s', $dak_doctor_option->display_name ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="dak-field">
			<label for="dak-admin-appointments-filter-payment-status"><?php esc_html_e( 'Payment status', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-appointments-filter-payment-status" name="payment_status">
				<option value=""><?php esc_html_e( 'All', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $payment_status_options as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filters['payment_status'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
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
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Appointment table', 'doctor-ak-portal' ); ?></h2>
	</div>

	<?php if ( empty( $appointments ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No appointments have been booked yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $appointments as $row ) : ?>
			<div id="dak-appointment-<?php echo esc_attr( $row['id'] ); ?>" class="dak-admin-record-row" data-appointment-row="<?php echo esc_attr( $row['id'] ); ?>">
				<div class="dak-admin-record-row-main">
					<span class="dak-avatar dak-avatar-sm" aria-hidden="true"><?php echo esc_html( $row['patient_initials'] ); ?></span>
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( $row['patient_name'] ); ?></strong>
						<span class="dak-admin-record-row-id"><?php echo esc_html( sprintf( 'APT-%04d', $row['id'] ) ); ?></span>
					</span>
					<span class="dak-admin-record-row-meta">
						<?php echo esc_html( sprintf( /* translators: %s: doctor name. */ __( 'Dr. %s', 'doctor-ak-portal' ), $row['doctor_name'] ) ); ?><br>
						<span class="dak-clinic-card-meta"><?php echo esc_html( $row['date'] . ' &middot; ' . $row['time'] ); ?></span>
					</span>

					<span class="dak-admin-record-row-tags">
						<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( $row['type_label'] ); ?></span>
						<span class="dak-status-pill dak-status-pill-outline dak-status-pill-<?php echo esc_attr( $row['status_badge_class'] ); ?>"><?php echo esc_html( $row['status_label'] ); ?></span>
						<?php if ( ! in_array( $row['status'], array( 'pending_payment', 'paid' ), true ) ) : ?>
							<span class="dak-status-pill dak-status-pill-outline <?php echo $row['is_paid'] ? 'dak-status-pill-is-active' : 'dak-status-pill-is-pending'; ?>">
								<?php echo $row['is_paid'] ? esc_html__( 'Paid', 'doctor-ak-portal' ) : esc_html__( 'Payment Pending', 'doctor-ak-portal' ); ?>
							</span>
						<?php endif; ?>
						<?php if ( 'requested' === $row['refund_status'] ) : ?>
							<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-pending"><?php esc_html_e( 'Refund Requested', 'doctor-ak-portal' ); ?></span>
						<?php elseif ( 'processed' === $row['refund_status'] ) : ?>
							<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-active"><?php esc_html_e( 'Refund Processed', 'doctor-ak-portal' ); ?></span>
						<?php endif; ?>
					</span>

					<span class="dak-admin-record-row-amount">
						<?php echo $row['charge'] > 0 ? esc_html( 'PKR ' . number_format( $row['charge'], 0 ) ) : esc_html__( 'Free', 'doctor-ak-portal' ); ?>
					</span>

					<span class="dak-admin-record-row-actions">
						<?php if ( ! $is_receptionist ) : ?>
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
						<?php endif; ?>
						<?php if ( ! $row['is_paid'] ) : ?>
							<button
								type="button"
								class="dak-icon-button dak-icon-button-success"
								data-admin-appointment-mark-paid
								data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"
								title="<?php esc_attr_e( 'Mark Paid', 'doctor-ak-portal' ); ?>"
								aria-label="<?php esc_attr_e( 'Mark Paid', 'doctor-ak-portal' ); ?>"
							><?php echo $dak_appt_icons['paid']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
						<?php endif; ?>
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
						<?php if ( ! $is_receptionist && 'requested' === $row['refund_status'] ) : ?>
							<button
								type="button"
								class="dak-icon-button"
								data-admin-process-refund
								data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"
								data-patient-name="<?php echo esc_attr( $row['patient_name'] ); ?>"
								data-reason="<?php echo esc_attr( $row['refund_reason'] ); ?>"
								data-charge="<?php echo esc_attr( $row['charge'] ); ?>"
								data-refund-amount="<?php echo esc_attr( $row['refund_amount'] ); ?>"
								title="<?php esc_attr_e( 'Process Refund', 'doctor-ak-portal' ); ?>"
								aria-label="<?php esc_attr_e( 'Process Refund', 'doctor-ak-portal' ); ?>"
							><?php echo $dak_appt_icons['refund']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
						<?php endif; ?>
						<a
							class="dak-icon-button"
							href="<?php echo esc_url( \DoctorAKPortal\Frontend\Appointment_Handler::print_url( $row['id'] ) ); ?>"
							target="_blank"
							rel="noopener noreferrer"
							title="<?php esc_attr_e( 'Print', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'Print', 'doctor-ak-portal' ); ?>"
						><?php echo $dak_appt_icons['print']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
						<?php if ( ! $is_receptionist ) : ?>
							<button
								type="button"
								class="dak-icon-button dak-icon-button-danger"
								data-admin-appointment-delete
								data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"
								title="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
								aria-label="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
							><?php echo $dak_appt_icons['delete']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
						<?php endif; ?>
					</span>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
