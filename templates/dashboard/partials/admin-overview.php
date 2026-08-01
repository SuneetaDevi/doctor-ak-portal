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
<div class="dak-dashboard-greeting">
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
		<span class="dak-stat-label"><?php esc_html_e( 'Revenue this month', 'doctor-ak-portal' ); ?></span>
	</div>
</section>

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
							<span class="dak-status-pill dak-status-pill-outline <?php echo $dak_row['is_paid'] ? 'dak-status-pill-is-active' : 'dak-status-pill-is-pending'; ?>">
								<?php echo $dak_row['is_paid'] ? esc_html__( 'Paid', 'doctor-ak-portal' ) : esc_html__( 'Payment Pending', 'doctor-ak-portal' ); ?>
							</span>
						</div>
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
