<?php
/**
 * Template: Doctor dashboard "Appointments" tab — every appointment this
 * doctor has ever had, filterable by date range and payment status.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $rows              Rows from Appointments::all_for_admin( [ 'doctor_id' => ... ] ).
 * @var string $appointments_url  Unfiltered URL of this tab, for the filter form and "Reset filters" link.
 * @var array  $range_options     Range slug => label ('', 'upcoming', 'past'), see Appointments::range_options().
 * @var array  $filters           Active filter values: date_from, date_to, payment_status, range, search.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_has_filters = '' !== $filters['date_from'] || '' !== $filters['date_to'] || '' !== $filters['payment_status'] || 'upcoming' !== $filters['range'] || '' !== $filters['search'];
?>
<div class="dak-dashboard-greeting">
	<h1><?php esc_html_e( 'Appointments', 'doctor-ak-portal' ); ?></h1>
	<p>
		<?php
		echo esc_html(
			sprintf(
				/* translators: %d: number of appointments. */
				_n( '%d appointment', '%d appointments', count( $rows ), 'doctor-ak-portal' ),
				count( $rows )
			)
		);
		?>
	</p>
</div>

<section class="dak-dashboard-card dak-appt-filters-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Filters', 'doctor-ak-portal' ); ?></h2>
	</div>

	<form
		method="get"
		action="<?php echo esc_url( $appointments_url ); ?>"
		class="dak-appt-filters-form"
		data-live-filter="doctor_ak_doctor_appointments_filter"
		data-live-filter-target="#dak-doctor-appointments-tab-content"
		data-live-filter-nonce="dakDoctorAppointments"
	>
		<input type="hidden" name="tab" value="appointments">

		<div class="dak-field">
			<label for="dak-doctor-appt-filter-search"><?php esc_html_e( 'Search', 'doctor-ak-portal' ); ?></label>
			<input type="search" id="dak-doctor-appt-filter-search" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Patient or guest name…', 'doctor-ak-portal' ); ?>">
		</div>

		<div class="dak-field">
			<label for="dak-doctor-appt-filter-range"><?php esc_html_e( 'Show', 'doctor-ak-portal' ); ?></label>
			<select id="dak-doctor-appt-filter-range" name="range">
				<?php foreach ( $range_options as $dak_range_slug => $dak_range_label ) : ?>
					<option value="<?php echo esc_attr( $dak_range_slug ); ?>" <?php selected( $filters['range'], $dak_range_slug ); ?>><?php echo esc_html( $dak_range_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="dak-field">
			<label for="dak-doctor-appt-filter-date-from"><?php esc_html_e( 'From', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-doctor-appt-filter-date-from" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
		</div>

		<div class="dak-field">
			<label for="dak-doctor-appt-filter-date-to"><?php esc_html_e( 'To', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-doctor-appt-filter-date-to" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
		</div>

		<div class="dak-field">
			<label for="dak-doctor-appt-filter-payment-status"><?php esc_html_e( 'Payment status', 'doctor-ak-portal' ); ?></label>
			<select id="dak-doctor-appt-filter-payment-status" name="payment_status">
				<option value=""><?php esc_html_e( 'All', 'doctor-ak-portal' ); ?></option>
				<option value="paid" <?php selected( $filters['payment_status'], 'paid' ); ?>><?php esc_html_e( 'Paid', 'doctor-ak-portal' ); ?></option>
				<option value="pending" <?php selected( $filters['payment_status'], 'pending' ); ?>><?php esc_html_e( 'Pending', 'doctor-ak-portal' ); ?></option>
			</select>
		</div>

		<div class="dak-admin-filter-actions">
			<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Filter', 'doctor-ak-portal' ); ?></button>
			<a
				class="dak-button dak-button-secondary"
				href="<?php echo esc_url( $appointments_url ); ?>"
				data-live-filter-clear
				data-live-filter="doctor_ak_doctor_appointments_filter"
				data-live-filter-target="#dak-doctor-appointments-tab-content"
				data-live-filter-nonce="dakDoctorAppointments"
			><?php esc_html_e( 'Reset filters', 'doctor-ak-portal' ); ?></a>
		</div>
	</form>
</section>

<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<div>
			<h2><?php esc_html_e( 'All appointments', 'doctor-ak-portal' ); ?></h2>
			<p class="dak-notifications-card-subtitle"><?php esc_html_e( 'Reschedule closes 30 minutes before start', 'doctor-ak-portal' ); ?></p>
		</div>
	</div>

	<?php if ( empty( $rows ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No appointments match these filters.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $rows as $row ) : ?>
			<div id="dak-appointment-<?php echo esc_attr( $row['id'] ); ?>" class="dak-patient-appt-row">
				<div class="dak-patient-appt-row-top">
					<div class="dak-patient-appt-row-doctor">
						<span class="dak-patient-appt-avatar">
							<?php if ( $row['patient_avatar_url'] ) : ?>
								<img src="<?php echo esc_url( $row['patient_avatar_url'] ); ?>" alt="">
							<?php else : ?>
								<?php echo esc_html( $row['patient_initials'] ); ?>
							<?php endif; ?>
						</span>
						<span class="dak-patient-appt-doctor-info">
							<strong><?php echo esc_html( $row['patient_name'] ); ?></strong>
							<span class="dak-patient-appt-specialty">
								<?php
								echo esc_html( $row['datetime_label'] );
								echo ' &middot; ';
								echo esc_html( $row['type_label'] );
								?>
							</span>
						</span>
					</div>

					<div class="dak-patient-appt-row-actions dak-patient-appt-row-actions-buttons">
						<?php if ( ! empty( $row['video_call']['can_join'] ) ) : ?>
							<a class="dak-button dak-button-primary dak-button-sm" href="<?php echo esc_url( $row['video_call']['room_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Join', 'doctor-ak-portal' ); ?></a>
						<?php endif; ?>
						<?php if ( ! $row['is_paid'] && (float) $row['charge'] > 0 && 'online' === $row['payment_mode'] ) : ?>
							<button type="button" class="dak-button dak-button-primary dak-button-sm" data-doctor-pay-now data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>">
								<?php echo esc_html( sprintf( /* translators: %s: amount. */ __( 'Pay PKR%s', 'doctor-ak-portal' ), number_format( (float) $row['charge'], 0 ) ) ); ?>
							</button>
						<?php elseif ( ! $row['is_paid'] && (float) $row['charge'] > 0 ) : ?>
							<button type="button" class="dak-button dak-button-primary dak-button-sm" data-doctor-mark-paid data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>">
								<?php echo esc_html( sprintf( /* translators: %s: amount. */ __( 'Mark Paid · PKR%s', 'doctor-ak-portal' ), number_format( (float) $row['charge'], 0 ) ) ); ?>
							</button>
						<?php endif; ?>
						<?php if ( in_array( $row['status'], array( 'confirmed', 'paid', 'rescheduled' ), true ) && ( $row['is_paid'] || (float) $row['charge'] <= 0 ) ) : ?>
							<?php if ( ! $row['is_overdue'] ) : ?>
								<button type="button" class="dak-button dak-button-secondary dak-button-sm" data-check-in data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>" title="<?php esc_attr_e( 'Check the patient in and open their encounter', 'doctor-ak-portal' ); ?>"><?php esc_html_e( 'Check In', 'doctor-ak-portal' ); ?></button>
							<?php endif; ?>
							<button type="button" class="dak-button dak-button-secondary dak-button-sm" data-mark-completed data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Mark Completed', 'doctor-ak-portal' ); ?></button>
						<?php elseif ( 'checked_in' === $row['status'] ) : ?>
							<?php
							$dak_open_encounter = \DoctorAKPortal\Includes\Encounters::find_by_appointment( $row['id'], \DoctorAKPortal\Includes\Encounters::STATUS_OPEN );
							$dak_encounter_url  = $dak_open_encounter ? add_query_arg( array( 'tab' => 'encounter', 'encounter_id' => $dak_open_encounter['id'] ), \DoctorAKPortal\Includes\Page_Finder::url_for_shortcode( \DoctorAKPortal\Frontend\Doctor_Dashboard::SHORTCODE_TAG ) ) : '';
							?>
							<?php if ( '' !== $dak_encounter_url ) : ?>
								<a class="dak-button dak-button-primary dak-button-sm" href="<?php echo esc_url( $dak_encounter_url ); ?>"><?php esc_html_e( 'Open Encounter', 'doctor-ak-portal' ); ?></a>
							<?php endif; ?>
						<?php endif; ?>
						<?php
						if ( empty( $row['reschedulable'] ) ) {
							$dak_reschedule_disabled_reason = in_array( $row['status'], array( 'cancelled', 'completed' ), true )
								? __( 'This appointment can no longer be rescheduled.', 'doctor-ak-portal' )
								: __( 'Rescheduling closes 30 minutes before the appointment.', 'doctor-ak-portal' );
						} else {
							$dak_reschedule_disabled_reason = '';
						}
						?>
						<button
							type="button"
							class="dak-button dak-button-secondary dak-button-sm"
							data-reschedule-appointment
							data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"
							data-date="<?php echo esc_attr( $row['date'] ); ?>"
							data-time="<?php echo esc_attr( $row['time'] ); ?>"
							<?php if ( '' !== $dak_reschedule_disabled_reason ) : ?>
								title="<?php echo esc_attr( $dak_reschedule_disabled_reason ); ?>"
							<?php endif; ?>
							<?php disabled( empty( $row['reschedulable'] ) ); ?>
						><?php esc_html_e( 'Reschedule', 'doctor-ak-portal' ); ?></button>
						<?php if ( ! in_array( $row['status'], array( 'cancelled', 'completed' ), true ) ) : ?>
							<button type="button" class="dak-button dak-button-danger-outline dak-button-sm" data-doctor-cancel-appointment data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Cancel', 'doctor-ak-portal' ); ?></button>
						<?php endif; ?>
					</div>
				</div>

				<div class="dak-patient-appt-row-bottom">
					<div class="dak-patient-appt-row-tags">
						<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( $row['type_label'] ); ?></span>
						<span class="dak-status-pill dak-status-pill-outline dak-status-pill-<?php echo esc_attr( $row['status_badge_class'] ); ?>"><?php echo esc_html( $row['status_label'] ); ?></span>
						<?php if ( ! in_array( $row['status'], array( 'pending_payment', 'paid' ), true ) ) : ?>
							<span class="dak-status-pill dak-status-pill-outline <?php echo $row['is_paid'] ? 'dak-status-pill-is-active' : 'dak-status-pill-is-pending'; ?>">
								<?php echo $row['is_paid'] ? esc_html__( 'Paid', 'doctor-ak-portal' ) : esc_html__( 'Payment Pending', 'doctor-ak-portal' ); ?>
							</span>
						<?php endif; ?>
					</div>
					<span class="dak-patient-appt-row-amount"><?php echo esc_html( $row['charge'] > 0 ? 'PKR ' . number_format( (float) $row['charge'], 0 ) : __( 'Free', 'doctor-ak-portal' ) ); ?></span>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
