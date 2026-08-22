<?php
/**
 * Template: Patient dashboard "Appointments" tab — every appointment this
 * patient has ever booked, filterable by date and status. Styled the same
 * as the admin portal's appointment rows (accent-bar card, avatar + info +
 * meta + tags + amount + actions).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $rows            Rows from Appointments::all_for_admin( [ 'patient_id' => ... ] ).
 * @var array  $status_options  Status slug => label, see Appointments::status_options().
 * @var array  $range_options   Range slug => label ('', 'upcoming', 'past'), see Appointments::range_options().
 * @var string $selected_date   'YYYY-MM-DD', or '' if unfiltered.
 * @var string $selected_status Status slug, or '' if unfiltered.
 * @var string $selected_range  Range slug ('', 'upcoming', 'past').
 * @var string $selected_search Doctor/service name search term, or '' if unfiltered.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="dak-dashboard-card dak-appt-filters-card">
	<form
		method="get"
		class="dak-appt-filters-form"
		data-live-filter="doctor_ak_patient_appointments_filter"
		data-live-filter-target="#dak-patient-appointments-tab-content"
		data-live-filter-nonce="dakPatientDashboard"
	>
		<input type="hidden" name="tab" value="appointments">
		<div class="dak-field">
			<label for="dak-patient-appt-filter-search"><?php esc_html_e( 'Search', 'doctor-ak-portal' ); ?></label>
			<input type="search" id="dak-patient-appt-filter-search" name="search" value="<?php echo esc_attr( $selected_search ); ?>" placeholder="<?php esc_attr_e( 'Doctor or service name…', 'doctor-ak-portal' ); ?>">
		</div>
		<div class="dak-field">
			<label for="dak-patient-appt-filter-range"><?php esc_html_e( 'Show', 'doctor-ak-portal' ); ?></label>
			<select id="dak-patient-appt-filter-range" name="range">
				<?php foreach ( $range_options as $range_slug => $range_label ) : ?>
					<option value="<?php echo esc_attr( $range_slug ); ?>" <?php selected( $selected_range, $range_slug ); ?>><?php echo esc_html( $range_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="dak-field">
			<label for="dak-patient-appt-filter-date"><?php esc_html_e( 'Date', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-patient-appt-filter-date" name="date" value="<?php echo esc_attr( $selected_date ); ?>">
		</div>
		<div class="dak-field">
			<label for="dak-patient-appt-filter-status"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></label>
			<select id="dak-patient-appt-filter-status" name="status">
				<option value=""><?php esc_html_e( 'All statuses', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $status_options as $status_slug => $status_label ) : ?>
					<option value="<?php echo esc_attr( $status_slug ); ?>" <?php selected( $selected_status, $status_slug ); ?>><?php echo esc_html( $status_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Filter', 'doctor-ak-portal' ); ?></button>
		<?php if ( '' !== $selected_date || '' !== $selected_status || 'upcoming' !== $selected_range || '' !== $selected_search ) : ?>
			<a
				class="dak-link"
				href="?tab=appointments"
				data-live-filter-clear
				data-live-filter="doctor_ak_patient_appointments_filter"
				data-live-filter-target="#dak-patient-appointments-tab-content"
				data-live-filter-nonce="dakPatientDashboard"
			><?php esc_html_e( 'Clear filters', 'doctor-ak-portal' ); ?></a>
		<?php endif; ?>
	</form>
</section>

<section class="dak-dashboard-card">
	<?php if ( empty( $rows ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No appointments match these filters.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $rows as $row ) : ?>
			<div id="dak-appointment-<?php echo esc_attr( $row['id'] ); ?>" class="dak-admin-record-row" data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>">
				<div class="dak-admin-record-row-main">
					<span class="dak-avatar dak-avatar-sm" aria-hidden="true">
						<?php if ( $row['doctor_avatar_url'] ) : ?>
							<img src="<?php echo esc_url( $row['doctor_avatar_url'] ); ?>" alt="">
						<?php else : ?>
							<?php echo esc_html( mb_strtoupper( mb_substr( $row['doctor_name'], 0, 1 ) ) ); ?>
						<?php endif; ?>
					</span>

					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( sprintf( 'Dr. %s', $row['doctor_name'] ) ); ?></strong>
						<span class="dak-admin-record-row-id"><?php echo esc_html( '' !== $row['service_name'] ? $row['service_name'] : $row['type_label'] ); ?></span>
					</span>

					<span class="dak-admin-record-row-meta">
						<?php echo esc_html( $row['datetime_label'] ); ?>
					</span>

					<span class="dak-admin-record-row-tags">
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
						<?php echo $row['charge'] > 0 ? esc_html( 'PKR ' . number_format( (float) $row['charge'], 0 ) ) : esc_html__( 'Free', 'doctor-ak-portal' ); ?>
					</span>

					<span class="dak-admin-record-row-actions">
						<?php if ( ! empty( $row['video_call']['can_join'] ) ) : ?>
							<a class="dak-status-pill dak-status-pill-action" href="<?php echo esc_url( $row['video_call']['room_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Join Call', 'doctor-ak-portal' ); ?></a>
						<?php endif; ?>
						<?php if ( ! $row['is_paid'] && (float) $row['charge'] > 0 && ! in_array( $row['status'], array( 'cancelled', 'completed' ), true ) ) : ?>
							<button type="button" class="dak-status-pill dak-status-pill-action" data-pay-now data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>">
								<?php echo esc_html( sprintf( /* translators: %s: amount. */ __( 'Pay PKR%s', 'doctor-ak-portal' ), number_format( (float) $row['charge'], 0 ) ) ); ?>
							</button>
						<?php endif; ?>
						<?php if ( 'cancelled' === $row['status'] && $row['is_paid'] && 'online' === $row['payment_mode'] && '' === $row['refund_status'] ) : ?>
							<button type="button" class="dak-status-pill dak-status-pill-action" data-request-refund data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Request Refund', 'doctor-ak-portal' ); ?></button>
						<?php endif; ?>
						<?php if ( ! empty( $row['reschedulable'] ) ) : ?>
							<button
								type="button"
								class="dak-icon-button"
								data-reschedule-appointment
								data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"
								data-date="<?php echo esc_attr( $row['date'] ); ?>"
								data-time="<?php echo esc_attr( $row['time'] ); ?>"
								title="<?php esc_attr_e( 'Reschedule', 'doctor-ak-portal' ); ?>"
								aria-label="<?php esc_attr_e( 'Reschedule', 'doctor-ak-portal' ); ?>"
							><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 10a6 6 0 1 1 1.8 4.3"/><path d="M4 14v-3.5H7.5"/><path d="M10 6.5v4l2.5 1.5"/></svg></button>
						<?php endif; ?>
					</span>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
