<?php
/**
 * Template: Administrator dashboard's "Dashboard" overview — a hero banner,
 * four stat cards, a "Latest appointments" list, and a "Pending doctor
 * approvals" side card.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var int    $total_doctors        Count of users holding the Doctor role.
 * @var int    $total_patients       Count of users holding the Patient role.
 * @var int    $total_clinics        Count of clinic rows across every doctor.
 * @var int    $total_appointments   Count of published appointments ever booked.
 * @var int    $appointments_today   Count of appointments dated today.
 * @var float  $total_revenue        Sum of every paid appointment's charge, see Appointments::revenue_summary().
 * @var float  $revenue_this_month   Sum of this calendar month's paid appointments.
 * @var int    $pending_doctors_count Count of doctor accounts awaiting approval.
 * @var array  $pending_doctors      Up to 3 pending doctor rows, see Admin_Dashboard::row_data().
 * @var array  $latest_appointments  Up to 6 upcoming appointment rows, see Appointments::admin_row_data(), soonest first.
 * @var array  $revenue_chart        Last 14 days' paid revenue, see Appointments::revenue_by_day().
 * @var array  $status_chart         Appointment counts by status, see Appointments::status_counts().
 * @var string $clinic_name          Clinic name (Settings → Footer Settings).
 * @var string $clinic_address       Clinic address (Settings → Footer Settings).
 * @var string $appointments_url     URL of the Appointments section ("View all" link).
 * @var string $doctor_requests_url  URL of the Doctor Requests section ("Review" links).
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_overview_icons = array(
	'calendar' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4" width="15" height="13" rx="1.5"/><path d="M2.5 8h15"/><path d="M6 2.5v3M14 2.5v3"/></svg>',
	'users'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="2.8"/><path d="M1.8 16c0-2.9 2.3-4.8 5.2-4.8s5.2 1.9 5.2 4.8"/><path d="M13 7.2a2.6 2.6 0 1 1 3.6 2.4"/><path d="M14.5 11.3c2 .3 3.7 1.7 3.7 4"/></svg>',
	'person'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M4 17c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/></svg>',
	'money'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M10 6.2v7.6M12.2 8.1c0-1-1-1.6-2.2-1.6s-2.2.6-2.2 1.5c0 2.2 4.4 1 4.4 3.2 0 .9-1 1.5-2.2 1.5s-2.2-.6-2.2-1.6"/></svg>',
);

if ( ! function_exists( 'dak_admin_overview_initials' ) ) :
	/**
	 * One or two uppercase initials from a name, for an avatar fallback.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	function dak_admin_overview_initials( $name ) {
		$words    = preg_split( '/\s+/', trim( (string) $name ) );
		$initials = '';

		foreach ( array_slice( $words, 0, 2 ) as $word ) {
			if ( '' !== $word ) {
				$initials .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
			}
		}

		return '' !== $initials ? $initials : '?';
	}
endif;
?>
<div class="dak-dashboard-greeting dak-hero-banner">
	<h1><?php esc_html_e( 'Clinic overview', 'doctor-ak-portal' ); ?></h1>
	<p>
		<?php
		echo esc_html( date_i18n( 'l, j F Y', current_time( 'timestamp' ) ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- display-only, no math done with it.
		if ( '' !== $clinic_name ) {
			echo ' &middot; ' . esc_html( $clinic_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html() already applied above.
		}
		?>
	</p>
</div>

<section class="dak-dashboard-statistics">
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_overview_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value"><?php echo esc_html( number_format_i18n( $appointments_today ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( 'Appointments today', 'doctor-ak-portal' ); ?></span>
	</div>
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_overview_icons['users']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value"><?php echo esc_html( number_format_i18n( $total_patients ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( 'Total patients', 'doctor-ak-portal' ); ?></span>
	</div>
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_overview_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value"><?php echo esc_html( number_format_i18n( $total_doctors ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( 'Doctors', 'doctor-ak-portal' ); ?></span>
		<?php if ( $pending_doctors_count > 0 ) : ?>
			<span class="dak-stat-delta">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of doctors pending approval. */
						_n( '%d pending approval', '%d pending approval', $pending_doctors_count, 'doctor-ak-portal' ),
						$pending_doctors_count
					)
				);
				?>
			</span>
		<?php endif; ?>
	</div>
	<div class="dak-stat-card">
		<span class="dak-stat-icon dak-stat-icon-green" aria-hidden="true"><?php echo $dak_overview_icons['money']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<span class="dak-stat-value">PKR <?php echo esc_html( number_format_i18n( $revenue_this_month ) ); ?></span>
		<span class="dak-stat-label"><?php esc_html_e( 'Hospital revenue this month', 'doctor-ak-portal' ); ?></span>
	</div>
</section>

<div class="dak-dashboard-grid dak-dashboard-grid-charts">
	<section class="dak-dashboard-card">
		<div class="dak-dashboard-card-header">
			<div>
				<h2><?php esc_html_e( 'Hospital Revenue', 'doctor-ak-portal' ); ?></h2>
				<p class="dak-notifications-card-subtitle"><?php esc_html_e( "Last 14 days, clinic's own share of paid appointments", 'doctor-ak-portal' ); ?></p>
			</div>
		</div>
		<?php
		$dak_revenue_totals = wp_list_pluck( $revenue_chart, 'total' );
		$dak_revenue_max    = max( 1.0, max( $dak_revenue_totals ) );
		$dak_revenue_scale  = pow( 10, floor( log10( $dak_revenue_max ) ) );
		$dak_revenue_max    = ceil( $dak_revenue_max / $dak_revenue_scale ) * $dak_revenue_scale;

		$dak_chart_w   = 640;
		$dak_chart_h   = 200;
		$dak_pad_left  = 58;
		$dak_pad_right = 12;
		$dak_pad_top   = 16;
		$dak_pad_bot   = 30;
		$dak_plot_w    = $dak_chart_w - $dak_pad_left - $dak_pad_right;
		$dak_plot_h    = $dak_chart_h - $dak_pad_top - $dak_pad_bot;
		$dak_count     = count( $revenue_chart );

		$dak_points = array();

		foreach ( array_values( $revenue_chart ) as $dak_i => $dak_point ) {
			$dak_points[] = array(
				'x'     => round( $dak_pad_left + ( $dak_count > 1 ? ( $dak_i / ( $dak_count - 1 ) ) * $dak_plot_w : $dak_plot_w / 2 ), 1 ),
				'y'     => round( $dak_pad_top + $dak_plot_h - ( $dak_point['total'] / $dak_revenue_max ) * $dak_plot_h, 1 ),
				'total' => $dak_point['total'],
				'label' => $dak_point['label'],
			);
		}

		$dak_line_d = '';

		foreach ( $dak_points as $dak_i => $dak_p ) {
			$dak_line_d .= ( 0 === $dak_i ? 'M' : 'L' ) . $dak_p['x'] . ' ' . $dak_p['y'] . ' ';
		}

		$dak_baseline_y = $dak_pad_top + $dak_plot_h;
		$dak_area_d     = $dak_line_d . 'L' . end( $dak_points )['x'] . ' ' . $dak_baseline_y . ' L' . $dak_points[0]['x'] . ' ' . $dak_baseline_y . ' Z';
		?>
		<svg class="dak-chart-svg" viewBox="0 0 <?php echo esc_attr( $dak_chart_w ); ?> <?php echo esc_attr( $dak_chart_h ); ?>" role="img" aria-label="<?php esc_attr_e( 'Line chart of daily paid revenue over the last 14 days', 'doctor-ak-portal' ); ?>">
			<?php foreach ( array( 0, 0.5, 1 ) as $dak_fraction ) : ?>
				<?php $dak_gy = round( $dak_pad_top + $dak_plot_h * ( 1 - $dak_fraction ), 1 ); ?>
				<line class="dak-chart-gridline" x1="<?php echo esc_attr( $dak_pad_left ); ?>" y1="<?php echo esc_attr( $dak_gy ); ?>" x2="<?php echo esc_attr( $dak_chart_w - $dak_pad_right ); ?>" y2="<?php echo esc_attr( $dak_gy ); ?>"></line>
				<text class="dak-chart-axis-label" x="<?php echo esc_attr( $dak_pad_left - 8 ); ?>" y="<?php echo esc_attr( $dak_gy + 4 ); ?>" text-anchor="end">PKR <?php echo esc_html( number_format_i18n( $dak_revenue_max * $dak_fraction ) ); ?></text>
			<?php endforeach; ?>

			<path class="dak-chart-area" d="<?php echo esc_attr( $dak_area_d ); ?>"></path>
			<path class="dak-chart-line" d="<?php echo esc_attr( trim( $dak_line_d ) ); ?>"></path>

			<?php foreach ( $dak_points as $dak_i => $dak_p ) : ?>
				<?php $dak_show_label = 0 === $dak_i % 3 || $dak_i === $dak_count - 1; ?>
				<?php if ( $dak_show_label ) : ?>
					<text class="dak-chart-axis-label" x="<?php echo esc_attr( $dak_p['x'] ); ?>" y="<?php echo esc_attr( $dak_chart_h - 6 ); ?>" text-anchor="middle"><?php echo esc_html( $dak_p['label'] ); ?></text>
				<?php endif; ?>
				<circle class="dak-chart-hit" cx="<?php echo esc_attr( $dak_p['x'] ); ?>" cy="<?php echo esc_attr( $dak_p['y'] ); ?>" r="8"><title><?php echo esc_html( $dak_p['label'] . ': PKR ' . number_format_i18n( $dak_p['total'] ) ); ?></title></circle>
				<circle class="dak-chart-dot" cx="<?php echo esc_attr( $dak_p['x'] ); ?>" cy="<?php echo esc_attr( $dak_p['y'] ); ?>" r="3"></circle>
			<?php endforeach; ?>
		</svg>
	</section>

	<section class="dak-dashboard-card">
		<div class="dak-dashboard-card-header">
			<div>
				<h2><?php esc_html_e( 'Appointments by status', 'doctor-ak-portal' ); ?></h2>
				<p class="dak-notifications-card-subtitle"><?php esc_html_e( 'All appointments on file', 'doctor-ak-portal' ); ?></p>
			</div>
		</div>
		<?php
		$dak_status_color_vars = array(
			'is-active'      => 'var(--dak-tint-success-text)',
			'is-pending'     => 'var(--dak-tint-amber-text)',
			'is-disabled'    => 'var(--dak-tint-danger-text)',
			'is-rescheduled' => 'var(--dak-tint-info-text)',
		);
		$dak_status_max = max( 1, max( wp_list_pluck( $status_chart, 'count' ) ) );
		?>
		<div class="dak-chart-bars">
			<?php foreach ( $status_chart as $dak_status_row ) : ?>
				<div class="dak-chart-bar-row">
					<span class="dak-chart-bar-label"><?php echo esc_html( $dak_status_row['label'] ); ?></span>
					<span class="dak-chart-bar-track">
						<span
							class="dak-chart-bar-fill"
							style="width: <?php echo esc_attr( round( $dak_status_row['count'] / $dak_status_max * 100, 1 ) ); ?>%; background: <?php echo esc_attr( isset( $dak_status_color_vars[ $dak_status_row['badge_class'] ] ) ? $dak_status_color_vars[ $dak_status_row['badge_class'] ] : 'var(--dak-muted)' ); ?>;"
						></span>
					</span>
					<span class="dak-chart-bar-value"><?php echo esc_html( number_format_i18n( $dak_status_row['count'] ) ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
</div>

<div class="dak-dashboard-grid dak-dashboard-grid-lists">
	<section class="dak-dashboard-card">
		<div class="dak-dashboard-card-header">
			<h2><?php esc_html_e( 'Latest appointments', 'doctor-ak-portal' ); ?></h2>
			<?php if ( $appointments_url ) : ?>
				<a class="dak-button dak-button-secondary dak-button-sm" href="<?php echo esc_url( $appointments_url ); ?>"><?php esc_html_e( 'View all', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>

		<?php if ( empty( $latest_appointments ) ) : ?>
			<p class="dak-empty-state"><?php esc_html_e( 'No upcoming appointments.', 'doctor-ak-portal' ); ?></p>
		<?php else : ?>
			<?php foreach ( $latest_appointments as $dak_row ) : ?>
				<div class="dak-patient-appt-row">
					<div class="dak-patient-appt-row-top">
						<div class="dak-patient-appt-row-doctor">
							<span class="dak-patient-appt-avatar">
								<?php if ( $dak_row['patient_avatar_url'] ) : ?>
									<img src="<?php echo esc_url( $dak_row['patient_avatar_url'] ); ?>" alt="">
								<?php else : ?>
									<?php echo esc_html( dak_admin_overview_initials( $dak_row['patient_name'] ) ); ?>
								<?php endif; ?>
							</span>
							<span class="dak-patient-appt-doctor-info">
								<strong><?php echo esc_html( $dak_row['patient_name'] ); ?></strong>
								<span class="dak-patient-appt-specialty">
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: doctor's display name, 2: appointment date, 3: appointment time. */
											__( 'Dr. %1$s &middot; %2$s &middot; %3$s', 'doctor-ak-portal' ),
											$dak_row['doctor_name'],
											$dak_row['date'],
											$dak_row['time']
										)
									);
									?>
								</span>
							</span>
						</div>

						<div class="dak-patient-appt-row-meta">
							<strong>PKR <?php echo esc_html( number_format_i18n( $dak_row['charge'] ) ); ?></strong>
						</div>
					</div>

					<div class="dak-patient-appt-row-bottom">
						<div class="dak-patient-appt-row-tags">
							<span class="dak-status-pill dak-status-pill-outline dak-status-pill-<?php echo esc_attr( $dak_row['status_badge_class'] ); ?>"><?php echo esc_html( $dak_row['status_label'] ); ?></span>
							<?php if ( ! in_array( $dak_row['status'], array( 'pending_payment', 'paid' ), true ) ) : ?>
								<span class="dak-status-pill dak-status-pill-outline <?php echo $dak_row['is_paid'] ? 'dak-status-pill-is-active' : 'dak-status-pill-is-pending'; ?>">
									<?php echo $dak_row['is_paid'] ? esc_html__( 'Paid', 'doctor-ak-portal' ) : esc_html__( 'Payment Pending', 'doctor-ak-portal' ); ?>
								</span>
							<?php endif; ?>
						</div>
						<?php if ( ! $dak_row['is_paid'] || ! empty( $dak_row['video_call']['can_join'] ) ) : ?>
							<div class="dak-patient-appt-row-actions">
								<?php if ( ! empty( $dak_row['video_call']['can_join'] ) ) : ?>
									<a class="dak-status-pill dak-status-pill-action" href="<?php echo esc_url( $dak_row['video_call']['room_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Join Call', 'doctor-ak-portal' ); ?></a>
								<?php endif; ?>
								<?php if ( ! $dak_row['is_paid'] && 'online' === $dak_row['payment_mode'] ) : ?>
									<button type="button" class="dak-status-pill dak-status-pill-action" data-admin-appointment-pay-now data-appointment-id="<?php echo esc_attr( $dak_row['id'] ); ?>" title="<?php esc_attr_e( 'Pay for this appointment', 'doctor-ak-portal' ); ?>"><?php echo esc_html( sprintf( /* translators: %s: amount. */ __( 'Pay PKR%s', 'doctor-ak-portal' ), number_format( (float) $dak_row['charge'], 0 ) ) ); ?></button>
								<?php elseif ( ! $dak_row['is_paid'] ) : ?>
									<button type="button" class="dak-status-pill dak-status-pill-action" data-admin-appointment-mark-paid data-appointment-id="<?php echo esc_attr( $dak_row['id'] ); ?>" title="<?php esc_attr_e( 'Mark this appointment as paid', 'doctor-ak-portal' ); ?>"><?php esc_html_e( 'Mark Paid', 'doctor-ak-portal' ); ?></button>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</section>

	<section class="dak-dashboard-card">
		<div class="dak-dashboard-card-header">
			<h2><?php esc_html_e( 'Pending doctor approvals', 'doctor-ak-portal' ); ?></h2>
		</div>

		<?php if ( empty( $pending_doctors ) ) : ?>
			<p class="dak-empty-state"><?php esc_html_e( 'No doctors awaiting approval.', 'doctor-ak-portal' ); ?></p>
		<?php else : ?>
			<?php foreach ( $pending_doctors as $dak_doctor ) : ?>
				<div class="dak-pending-doctor-card">
					<div class="dak-pending-doctor-header">
						<span class="dak-avatar dak-avatar-sm">
							<?php if ( $dak_doctor['avatar_url'] ) : ?>
								<img src="<?php echo esc_url( $dak_doctor['avatar_url'] ); ?>" alt="">
							<?php else : ?>
								<?php echo esc_html( dak_admin_overview_initials( $dak_doctor['name'] ) ); ?>
							<?php endif; ?>
						</span>
						<span class="dak-pending-doctor-info">
							<strong><?php echo esc_html( sprintf( 'Dr. %s', $dak_doctor['name'] ) ); ?></strong>
							<?php if ( '' !== $dak_doctor['city'] || '' !== $dak_doctor['country'] ) : ?>
								<span><?php echo esc_html( implode( ', ', array_filter( array( $dak_doctor['city'], $dak_doctor['country'] ) ) ) ); ?></span>
							<?php endif; ?>
						</span>
					</div>

					<?php if ( ! empty( $dak_doctor['specialization_labels'] ) ) : ?>
						<div class="dak-specialty-tags">
							<?php foreach ( array_slice( $dak_doctor['specialization_labels'], 0, 2 ) as $dak_specialization ) : ?>
								<span class="dak-specialty-tag"><?php echo esc_html( $dak_specialization ); ?></span>
							<?php endforeach; ?>
							<?php if ( count( $dak_doctor['specialization_labels'] ) > 2 ) : ?>
								<span class="dak-specialty-tag"><?php echo esc_html( sprintf( '+%d', count( $dak_doctor['specialization_labels'] ) - 2 ) ); ?></span>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<div class="dak-pending-doctor-actions">
						<button type="button" class="dak-button dak-button-primary dak-button-sm dak-button-block" data-doctor-request-approve data-user-id="<?php echo esc_attr( $dak_doctor['id'] ); ?>"><?php esc_html_e( 'Approve', 'doctor-ak-portal' ); ?></button>
						<?php if ( $doctor_requests_url ) : ?>
							<a class="dak-button dak-button-secondary dak-button-sm dak-button-block" href="<?php echo esc_url( $doctor_requests_url ); ?>"><?php esc_html_e( 'Review', 'doctor-ak-portal' ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>

			<p class="dak-field-hint"><?php esc_html_e( 'Approvals notify the doctor by email.', 'doctor-ak-portal' ); ?></p>
		<?php endif; ?>
	</section>
</div>
