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
 * @var string $appointments_chart_html Pre-rendered admin-appointments-chart.php output — the "Appointments" clustered bar chart (counts by status, by day/week/month).
 * @var string $clinic_name          Clinic name (Settings → Footer Settings).
 * @var string $clinic_address       Clinic address (Settings → Footer Settings).
 * @var string $appointments_url     URL of the Appointments section ("View all" link).
 * @var string $doctor_requests_url  URL of the Doctor Requests section ("Review" links).
 * @var bool   $is_receptionist      Whether the logged-in viewer is a Receptionist — hospital revenue figures are admin-only and hidden entirely for this role.
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
<div class="dak-page-head">
	<div>
		<h1><?php esc_html_e( 'Overview', 'doctor-ak-portal' ); ?></h1>
		<p>
			<?php
			echo esc_html( date_i18n( 'l, j F Y', current_time( 'timestamp' ) ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- display-only, no math done with it.
			if ( '' !== $clinic_name ) {
				echo ' &middot; ' . esc_html( $clinic_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_html() already applied above.
			}
			?>
		</p>
	</div>
	<?php if ( $appointments_url ) : ?>
		<a class="dak-button dak-button-secondary dak-button-sm" href="<?php echo esc_url( $appointments_url ); ?>"><?php esc_html_e( 'View appointments', 'doctor-ak-portal' ); ?></a>
	<?php endif; ?>
</div>

<section class="dak-dashboard-statistics">
	<div class="dak-stat-card">
		<div class="dak-kpi-top">
			<span class="dak-stat-label"><?php esc_html_e( 'Appointments today', 'doctor-ak-portal' ); ?></span>
			<span class="dak-stat-icon" aria-hidden="true"><?php echo $dak_overview_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<span class="dak-stat-value"><?php echo esc_html( number_format_i18n( $appointments_today ) ); ?></span>
		<?php if ( $appointments_url ) : ?>
			<a class="dak-kpi-foot" href="<?php echo esc_url( $appointments_url ); ?>"><span><?php esc_html_e( 'View appointments', 'doctor-ak-portal' ); ?></span><span aria-hidden="true">&rarr;</span></a>
		<?php endif; ?>
	</div>
	<div class="dak-stat-card">
		<div class="dak-kpi-top">
			<span class="dak-stat-label"><?php esc_html_e( 'Total patients', 'doctor-ak-portal' ); ?></span>
			<span class="dak-stat-icon" aria-hidden="true"><?php echo $dak_overview_icons['users']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<span class="dak-stat-value"><?php echo esc_html( number_format_i18n( $total_patients ) ); ?></span>
	</div>
	<div class="dak-stat-card">
		<div class="dak-kpi-top">
			<span class="dak-stat-label"><?php esc_html_e( 'Doctors', 'doctor-ak-portal' ); ?></span>
			<span class="dak-stat-icon" aria-hidden="true"><?php echo $dak_overview_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</div>
		<span class="dak-stat-value"><?php echo esc_html( number_format_i18n( $total_doctors ) ); ?></span>
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
		<?php if ( $doctor_requests_url ) : ?>
			<a class="dak-kpi-foot" href="<?php echo esc_url( $doctor_requests_url ); ?>"><span><?php esc_html_e( 'Review requests', 'doctor-ak-portal' ); ?></span><span aria-hidden="true">&rarr;</span></a>
		<?php endif; ?>
	</div>
	<?php if ( ! $is_receptionist ) : ?>
		<div class="dak-stat-card">
			<div class="dak-kpi-top">
				<span class="dak-stat-label"><?php esc_html_e( 'Hospital revenue this month', 'doctor-ak-portal' ); ?></span>
				<span class="dak-stat-icon" aria-hidden="true"><?php echo $dak_overview_icons['money']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</div>
			<span class="dak-stat-value">PKR <?php echo esc_html( number_format_i18n( $revenue_this_month ) ); ?></span>
		</div>
	<?php endif; ?>
</section>

<div class="dak-dashboard-grid dak-dashboard-grid-charts">
	<?php if ( ! $is_receptionist ) : ?>
	<section class="dak-dashboard-card dak-cashflow">
		<?php
		$dak_rev_totals = wp_list_pluck( $revenue_chart, 'total' );
		$dak_rev_sum    = array_sum( $dak_rev_totals );
		$dak_rev_max    = max( 1.0, (float) max( $dak_rev_totals ) );
		$dak_rev_scale  = pow( 10, floor( log10( $dak_rev_max ) ) );
		$dak_rev_top    = ceil( $dak_rev_max / $dak_rev_scale ) * $dak_rev_scale;
		?>
		<div class="dak-cashflow-head">
			<div>
				<span class="dak-cashflow-label"><?php esc_html_e( 'Hospital revenue', 'doctor-ak-portal' ); ?></span>
				<strong class="dak-cashflow-total">PKR <?php echo esc_html( number_format_i18n( $dak_rev_sum ) ); ?></strong>
			</div>
			<span class="dak-cashflow-range"><?php esc_html_e( 'Last 14 days', 'doctor-ak-portal' ); ?></span>
		</div>
		<div class="dak-cashflow-chart" role="img" aria-label="<?php esc_attr_e( 'Bar chart of daily paid revenue over the last 14 days', 'doctor-ak-portal' ); ?>">
			<div class="dak-cashflow-axis">
				<?php foreach ( array( 1, 0.75, 0.5, 0.25, 0 ) as $dak_fraction ) : ?>
					<span><?php echo esc_html( number_format_i18n( $dak_rev_top * $dak_fraction ) ); ?></span>
				<?php endforeach; ?>
			</div>
			<div class="dak-cashflow-bars">
				<?php foreach ( array_values( $revenue_chart ) as $dak_i => $dak_point ) : ?>
					<div class="dak-cashflow-col" tabindex="0">
						<span class="dak-cashflow-tip"><small><?php echo esc_html( $dak_point['label'] ); ?></small>PKR <?php echo esc_html( number_format_i18n( $dak_point['total'] ) ); ?></span>
						<span class="dak-cashflow-bar" style="height:<?php echo esc_attr( max( 2, round( $dak_point['total'] / $dak_rev_top * 100 ) ) ); ?>%"></span>
						<span class="dak-cashflow-xlabel"><?php echo esc_html( $dak_point['label'] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<?php echo $appointments_chart_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own admin-appointments-chart.php template, which escapes its own output. ?>
</div>

<div class="dak-dashboard-grid dak-dashboard-grid-lists">
	<section class="dak-dashboard-card dak-activity-card">
		<div class="dak-activity-head">
			<h2><?php esc_html_e( 'Recent activities', 'doctor-ak-portal' ); ?></h2>
			<div class="dak-activity-tools">
				<label class="dak-activity-search">
					<span class="dak-visually-hidden"><?php esc_html_e( 'Search activities', 'doctor-ak-portal' ); ?></span>
					<input type="search" placeholder="<?php esc_attr_e( 'Search', 'doctor-ak-portal' ); ?>" data-activity-search>
				</label>
				<select data-activity-filter aria-label="<?php esc_attr_e( 'Filter by status', 'doctor-ak-portal' ); ?>">
					<option value=""><?php esc_html_e( 'All statuses', 'doctor-ak-portal' ); ?></option>
					<?php foreach ( array_unique( wp_list_pluck( $latest_appointments, 'status_label' ) ) as $dak_status_option ) : ?>
						<option value="<?php echo esc_attr( strtolower( $dak_status_option ) ); ?>"><?php echo esc_html( $dak_status_option ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( $appointments_url ) : ?>
					<a class="dak-button dak-button-secondary dak-button-sm" href="<?php echo esc_url( $appointments_url ); ?>"><?php esc_html_e( 'View all', 'doctor-ak-portal' ); ?></a>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( empty( $latest_appointments ) ) : ?>
			<p class="dak-empty-state"><?php esc_html_e( 'No upcoming appointments.', 'doctor-ak-portal' ); ?></p>
		<?php else : ?>
			<div class="dak-activity-scroll">
				<table class="dak-activity-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Patient', 'doctor-ak-portal' ); ?></th>
							<th><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></th>
							<th><?php esc_html_e( 'Date & time', 'doctor-ak-portal' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'doctor-ak-portal' ); ?></th>
							<th><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
							<th><span class="dak-visually-hidden"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $latest_appointments as $dak_row ) : ?>
							<tr data-activity-row data-status="<?php echo esc_attr( strtolower( $dak_row['status_label'] ) ); ?>" data-text="<?php echo esc_attr( strtolower( $dak_row['patient_name'] . ' ' . $dak_row['doctor_name'] . ' ' . $dak_row['patient_phone'] ) ); ?>">
								<td>
									<span class="dak-activity-person">
										<span class="dak-patient-appt-avatar">
											<?php if ( $dak_row['patient_avatar_url'] ) : ?>
												<img src="<?php echo esc_url( $dak_row['patient_avatar_url'] ); ?>" alt="">
											<?php else : ?>
												<?php echo esc_html( dak_admin_overview_initials( $dak_row['patient_name'] ) ); ?>
											<?php endif; ?>
										</span>
										<span><strong><?php echo esc_html( $dak_row['patient_name'] ); ?></strong>
										<?php if ( '' !== $dak_row['patient_phone'] ) : ?><small><?php echo esc_html( $dak_row['patient_phone'] ); ?></small><?php endif; ?></span>
									</span>
								</td>
								<td><?php echo esc_html( sprintf( 'Dr. %s', $dak_row['doctor_name'] ) ); ?></td>
								<td><?php echo esc_html( $dak_row['datetime_label'] ); ?></td>
								<td><strong>PKR <?php echo esc_html( number_format_i18n( $dak_row['charge'] ) ); ?></strong></td>
								<td>
									<span class="dak-status-pill dak-status-pill-outline dak-status-pill-<?php echo esc_attr( $dak_row['status_badge_class'] ); ?>"><?php echo esc_html( $dak_row['status_label'] ); ?></span>
									<?php if ( ! in_array( $dak_row['status'], array( 'pending_payment', 'paid' ), true ) ) : ?>
										<span class="dak-status-pill dak-status-pill-outline <?php echo $dak_row['is_paid'] ? 'dak-status-pill-is-active' : 'dak-status-pill-is-pending'; ?>"><?php echo $dak_row['is_paid'] ? esc_html__( 'Paid', 'doctor-ak-portal' ) : esc_html__( 'Payment Pending', 'doctor-ak-portal' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="dak-activity-actions">
						<?php if ( $dak_row['is_overdue'] ) : ?>
							<div class="dak-patient-appt-row-actions">
								<button
									type="button"
									class="dak-status-pill dak-status-pill-action"
									data-admin-appointment-edit
									data-appointment-id="<?php echo esc_attr( $dak_row['id'] ); ?>"
									data-doctor-id="<?php echo esc_attr( $dak_row['doctor_id'] ); ?>"
									data-patient-id="<?php echo esc_attr( $dak_row['patient_id'] ); ?>"
									data-guest-name="<?php echo esc_attr( $dak_row['guest_name'] ); ?>"
									data-guest-email="<?php echo esc_attr( $dak_row['guest_email'] ); ?>"
									data-guest-phone="<?php echo esc_attr( $dak_row['guest_phone'] ); ?>"
									data-type="<?php echo esc_attr( $dak_row['type'] ); ?>"
									data-service-id="<?php echo esc_attr( $dak_row['service_id'] ); ?>"
									data-date="<?php echo esc_attr( $dak_row['date'] ); ?>"
									data-time="<?php echo esc_attr( $dak_row['time'] ); ?>"
									data-status="<?php echo esc_attr( $dak_row['status'] ); ?>"
									data-payment-status="<?php echo esc_attr( $dak_row['payment_status'] ); ?>"
									data-payment-mode="<?php echo esc_attr( $dak_row['payment_mode'] ); ?>"
									data-notes="<?php echo esc_attr( $dak_row['notes'] ); ?>"
									title="<?php esc_attr_e( 'This appointment\'s time has passed — reschedule it to a new date/time.', 'doctor-ak-portal' ); ?>"
								><?php esc_html_e( 'Reschedule', 'doctor-ak-portal' ); ?></button>
							</div>
						<?php elseif ( ! $dak_row['is_paid'] || ! empty( $dak_row['video_call']['can_join'] ) || in_array( $dak_row['status'], array( 'confirmed', 'paid', 'rescheduled', 'checked_in' ), true ) ) : ?>
							<div class="dak-patient-appt-row-actions">
								<?php if ( ! empty( $dak_row['video_call']['can_join'] ) ) : ?>
									<button type="button" class="dak-status-pill dak-status-pill-action" data-join-video-call data-room-url="<?php echo esc_url( $dak_row['video_call']['room_url'] ); ?>"><?php esc_html_e( 'Join Call', 'doctor-ak-portal' ); ?></button>
								<?php endif; ?>
								<?php if ( in_array( $dak_row['status'], array( 'confirmed', 'paid', 'rescheduled' ), true ) && ( $dak_row['is_paid'] || (float) $dak_row['charge'] <= 0 ) ) : ?>
									<button type="button" class="dak-status-pill dak-status-pill-action" data-check-in data-appointment-id="<?php echo esc_attr( $dak_row['id'] ); ?>" title="<?php esc_attr_e( 'Check the patient in and open their encounter', 'doctor-ak-portal' ); ?>"><?php esc_html_e( 'Check In', 'doctor-ak-portal' ); ?></button>
								<?php elseif ( 'checked_in' === $dak_row['status'] ) : ?>
									<?php
									$dak_open_encounter = \DoctorAKPortal\Includes\Encounters::find_by_appointment( $dak_row['id'], \DoctorAKPortal\Includes\Encounters::STATUS_OPEN );
									$dak_encounter_url  = $dak_open_encounter ? add_query_arg( array( 'section' => 'encounter', 'encounter_id' => $dak_open_encounter['id'] ), \DoctorAKPortal\Includes\Page_Finder::url_for_shortcode( \DoctorAKPortal\Frontend\Admin_Dashboard::SHORTCODE_TAG ) ) : '';
									?>
									<?php if ( '' !== $dak_encounter_url ) : ?>
										<a class="dak-status-pill dak-status-pill-action" href="<?php echo esc_url( $dak_encounter_url ); ?>"><?php esc_html_e( 'Open Encounter', 'doctor-ak-portal' ); ?></a>
									<?php endif; ?>
								<?php endif; ?>
								<?php if ( ! $dak_row['is_paid'] && (float) $dak_row['charge'] > 0 && 'online' === $dak_row['payment_mode'] ) : ?>
									<button type="button" class="dak-status-pill dak-status-pill-action" data-admin-appointment-pay-now data-appointment-id="<?php echo esc_attr( $dak_row['id'] ); ?>" title="<?php esc_attr_e( 'Pay for this appointment', 'doctor-ak-portal' ); ?>"><?php echo esc_html( sprintf( /* translators: %s: amount. */ __( 'Pay PKR%s', 'doctor-ak-portal' ), number_format( (float) $dak_row['charge'], 0 ) ) ); ?></button>
									<button type="button" class="dak-icon-button" data-admin-appointment-mark-paid data-appointment-id="<?php echo esc_attr( $dak_row['id'] ); ?>" title="<?php esc_attr_e( 'Already collected? Mark this appointment as paid manually.', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Mark Paid', 'doctor-ak-portal' ); ?>"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M6.8 10.2l2.1 2.1 4.3-4.6"/></svg></button>
								<?php elseif ( ! $dak_row['is_paid'] && (float) $dak_row['charge'] > 0 ) : ?>
									<button type="button" class="dak-status-pill dak-status-pill-action" data-admin-appointment-mark-paid data-appointment-id="<?php echo esc_attr( $dak_row['id'] ); ?>" title="<?php esc_attr_e( 'Mark this appointment as paid', 'doctor-ak-portal' ); ?>"><?php esc_html_e( 'Mark Paid', 'doctor-ak-portal' ); ?></button>
								<?php endif; ?>
							</div>
						<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="dak-empty-state dak-hidden" data-activity-empty><?php esc_html_e( 'No activities match your search.', 'doctor-ak-portal' ); ?></p>
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
