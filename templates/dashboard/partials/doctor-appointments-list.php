<?php
/**
 * Template: Doctor dashboard "Appointments" tab — every appointment this
 * doctor has ever had, filterable by date and status.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $rows            Rows from Appointments::all_for_admin( [ 'doctor_id' => ... ] ).
 * @var array  $status_options  Status slug => label, see Appointments::status_options().
 * @var string $selected_date   'YYYY-MM-DD', or '' if unfiltered.
 * @var string $selected_status Status slug, or '' if unfiltered.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="dak-dashboard-card dak-appt-filters-card">
	<form method="get" class="dak-appt-filters-form">
		<input type="hidden" name="tab" value="appointments">
		<div class="dak-field">
			<label for="dak-doctor-appt-filter-date"><?php esc_html_e( 'Date', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-doctor-appt-filter-date" name="date" value="<?php echo esc_attr( $selected_date ); ?>">
		</div>
		<div class="dak-field">
			<label for="dak-doctor-appt-filter-status"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></label>
			<select id="dak-doctor-appt-filter-status" name="status">
				<option value=""><?php esc_html_e( 'All statuses', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $status_options as $status_slug => $status_label ) : ?>
					<option value="<?php echo esc_attr( $status_slug ); ?>" <?php selected( $selected_status, $status_slug ); ?>><?php echo esc_html( $status_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Filter', 'doctor-ak-portal' ); ?></button>
		<?php if ( '' !== $selected_date || '' !== $selected_status ) : ?>
			<a class="dak-link" href="?tab=appointments"><?php esc_html_e( 'Clear filters', 'doctor-ak-portal' ); ?></a>
		<?php endif; ?>
	</form>
</section>

<section class="dak-dashboard-card">
	<?php if ( empty( $rows ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No appointments match these filters.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<ul class="dak-patient-payments-list">
			<?php foreach ( $rows as $row ) : ?>
				<li class="dak-patient-payment-row">
					<span class="dak-patient-appt-avatar">
						<?php if ( $row['patient_avatar_url'] ) : ?>
							<img src="<?php echo esc_url( $row['patient_avatar_url'] ); ?>" alt="">
						<?php else : ?>
							<?php echo esc_html( $row['patient_initials'] ); ?>
						<?php endif; ?>
					</span>

					<div class="dak-patient-payment-info">
						<strong><?php echo esc_html( $row['patient_name'] ); ?></strong>
						<span class="dak-patient-payment-meta">
							<?php
							$row_date_timestamp = strtotime( $row['date'] );
							$row_time_timestamp = strtotime( $row['date'] . ' ' . $row['time'] );
							echo esc_html( $row_date_timestamp ? date_i18n( 'D, M j, Y', $row_date_timestamp ) : $row['date'] );
							echo ' &middot; ';
							echo esc_html( $row_time_timestamp ? date_i18n( 'g:i A', $row_time_timestamp ) : $row['time'] );
							echo ' &middot; ';
							echo esc_html( $row['type_label'] );
							?>
						</span>
					</div>

					<div class="dak-patient-payment-amount-wrap">
						<span class="dak-status-pill dak-status-pill-outline dak-status-pill-<?php echo esc_attr( $row['status_badge_class'] ); ?>"><?php echo esc_html( $row['status_label'] ); ?></span>
						<span class="dak-status-pill dak-status-pill-outline <?php echo $row['is_paid'] ? 'dak-status-pill-is-active' : 'dak-status-pill-is-pending'; ?>">
							<?php echo $row['is_paid'] ? esc_html__( 'Paid', 'doctor-ak-portal' ) : esc_html__( 'Payment Pending', 'doctor-ak-portal' ); ?>
						</span>
						<?php if ( ! empty( $row['video_call']['can_join'] ) ) : ?>
							<a class="dak-status-pill dak-status-pill-action" href="<?php echo esc_url( $row['video_call']['room_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Start Call', 'doctor-ak-portal' ); ?></a>
						<?php endif; ?>
						<?php if ( in_array( $row['status'], array( 'confirmed', 'paid', 'rescheduled' ), true ) ) : ?>
							<button type="button" class="dak-status-pill dak-status-pill-action" data-mark-completed data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Mark Completed', 'doctor-ak-portal' ); ?></button>
						<?php endif; ?>
						<?php if ( ! empty( $row['reschedulable'] ) ) : ?>
							<button
								type="button"
								class="dak-status-pill dak-status-pill-action"
								data-reschedule-appointment
								data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"
								data-date="<?php echo esc_attr( $row['date'] ); ?>"
								data-time="<?php echo esc_attr( $row['time'] ); ?>"
							><?php esc_html_e( 'Reschedule', 'doctor-ak-portal' ); ?></button>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
