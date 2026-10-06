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
 * @var string $doctor_requests_url  URL of the Doctor Requests section ("Review" links), or '' when this viewer can't open that section.
 * @var bool   $can_review_doctor_requests Whether this viewer may open Doctor Requests — gates the pending-approval count and panel (same check the section itself applies).
 * @var bool   $is_receptionist      Whether the logged-in viewer is a Receptionist — hospital revenue figures are admin-only and hidden entirely for this role.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_money = array( '\DoctorAKPortal\Includes\Dashboard_Format', 'money' );
?>
<div class="dak-list-page dak-overview-page">
<div class="dak-page-head">
	<div>
		<h1><?php echo esc_html( $is_receptionist ? __( 'Front desk', 'doctor-ak-portal' ) : __( 'Overview', 'doctor-ak-portal' ) ); ?></h1>
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
		<a class="dak-button dak-button-primary" href="<?php echo esc_url( $appointments_url ); ?>"><?php esc_html_e( 'Manage appointments', 'doctor-ak-portal' ); ?></a>
	<?php endif; ?>
</div>

<div class="dak-summary-grid">
	<div class="dak-summary-card">
		<span class="dak-summary-card-label"><?php esc_html_e( 'Appointments today', 'doctor-ak-portal' ); ?></span>
		<strong class="dak-summary-card-value"><?php echo esc_html( number_format_i18n( $appointments_today ) ); ?></strong>
		<?php if ( $appointments_url ) : ?>
			<a class="dak-summary-card-link" href="<?php echo esc_url( $appointments_url ); ?>"><?php esc_html_e( 'View appointments', 'doctor-ak-portal' ); ?></a>
		<?php endif; ?>
	</div>
	<div class="dak-summary-card">
		<span class="dak-summary-card-label"><?php esc_html_e( 'Total patients', 'doctor-ak-portal' ); ?></span>
		<strong class="dak-summary-card-value"><?php echo esc_html( number_format_i18n( $total_patients ) ); ?></strong>
	</div>
	<div class="dak-summary-card">
		<span class="dak-summary-card-label"><?php esc_html_e( 'Doctors', 'doctor-ak-portal' ); ?></span>
		<strong class="dak-summary-card-value"><?php echo esc_html( number_format_i18n( $total_doctors ) ); ?></strong>
		<?php if ( $can_review_doctor_requests && $pending_doctors_count > 0 ) : ?>
			<span class="dak-summary-card-sub is-attention">
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
			<a class="dak-summary-card-link" href="<?php echo esc_url( $doctor_requests_url ); ?>"><?php esc_html_e( 'Review requests', 'doctor-ak-portal' ); ?></a>
		<?php endif; ?>
	</div>
	<?php if ( ! $is_receptionist ) : ?>
		<div class="dak-summary-card">
			<span class="dak-summary-card-label"><?php esc_html_e( 'Hospital revenue this month', 'doctor-ak-portal' ); ?></span>
			<strong class="dak-summary-card-value"><?php echo esc_html( call_user_func( $dak_money, $revenue_this_month ) ); ?></strong>
		</div>
	<?php endif; ?>
</div>

<?php // Today's working list first — the charts below support decisions but shouldn't push the list a front desk works from off-screen. ?>
<section class="dak-results dak-overview-upcoming" aria-labelledby="dak-overview-upcoming-title">
	<div class="dak-results-tools">
		<div>
			<h2 class="dak-results-title" id="dak-overview-upcoming-title"><?php esc_html_e( 'Upcoming appointments', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $latest_appointments ) ) ); ?></span></h2>
			<p class="dak-results-subtitle"><?php esc_html_e( 'From today, soonest first', 'doctor-ak-portal' ); ?></p>
		</div>
		<?php if ( ! empty( $latest_appointments ) ) : ?>
			<div class="dak-results-tools-actions">
				<label class="dak-dashboard-search dak-list-search-box">
					<span class="dak-dashboard-search-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg></span>
					<span class="dak-visually-hidden"><?php esc_html_e( 'Search upcoming appointments', 'doctor-ak-portal' ); ?></span>
					<input type="search" placeholder="<?php esc_attr_e( 'Patient or doctor', 'doctor-ak-portal' ); ?>" data-activity-search>
				</label>
				<select class="dak-compact-select" data-activity-filter aria-label="<?php esc_attr_e( 'Filter by status', 'doctor-ak-portal' ); ?>">
					<option value=""><?php esc_html_e( 'All statuses', 'doctor-ak-portal' ); ?></option>
					<?php foreach ( array_unique( wp_list_pluck( $latest_appointments, 'status_label' ) ) as $dak_status_option ) : ?>
						<option value="<?php echo esc_attr( strtolower( $dak_status_option ) ); ?>"><?php echo esc_html( $dak_status_option ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( $appointments_url ) : ?>
					<a class="dak-text-action" href="<?php echo esc_url( $appointments_url ); ?>"><?php esc_html_e( 'View all', 'doctor-ak-portal' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( empty( $latest_appointments ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No upcoming appointments.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php
		// Same badge mapping as the approved Appointments list: booking
		// lifecycle status and payment status are separate fields, shown
		// separately (never inferred from one another).
		$dak_status_classes = array(
			'confirmed'       => 'dak-status-pill-is-confirmed',
			'pending_payment' => 'dak-status-pill-is-pending',
			'paid'            => 'dak-status-pill-is-active',
			'checked_in'      => 'dak-status-pill-is-checked-in',
			'completed'       => 'dak-status-pill-is-neutral',
			'cancelled'       => 'dak-status-pill-is-neutral',
			'rescheduled'     => 'dak-status-pill-is-rescheduled',
		);
		$dak_dashboard_base = \DoctorAKPortal\Includes\Page_Finder::url_for_shortcode( \DoctorAKPortal\Frontend\Admin_Dashboard::SHORTCODE_TAG );
		?>
		<div class="dak-data-table-wrap">
			<table class="dak-data-table dak-ui-table dak-overview-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Patient', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date & time', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Payment', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $latest_appointments as $dak_row ) : ?>
						<?php
						$dak_ts        = strtotime( $dak_row['date'] . ' ' . $dak_row['time'] );
						$dak_can_check = in_array( $dak_row['status'], array( 'confirmed', 'paid', 'rescheduled' ), true ) && ( $dak_row['is_paid'] || (float) $dak_row['charge'] <= 0 );
						$dak_unpaid    = ! $dak_row['is_paid'] && (float) $dak_row['charge'] > 0;
						$dak_encounter_url = '';

						if ( ! $dak_row['is_overdue'] && 'checked_in' === $dak_row['status'] ) {
							$dak_open_encounter = \DoctorAKPortal\Includes\Encounters::find_by_appointment( $dak_row['id'], \DoctorAKPortal\Includes\Encounters::STATUS_OPEN );
							$dak_encounter_url  = $dak_open_encounter ? add_query_arg( array( 'section' => 'encounter', 'encounter_id' => $dak_open_encounter['id'], 'from' => 'dashboard' ), $dak_dashboard_base ) : '';
						}
						?>
						<tr data-row data-activity-row data-status="<?php echo esc_attr( strtolower( $dak_row['status_label'] ) ); ?>" data-text="<?php echo esc_attr( strtolower( $dak_row['patient_name'] . ' ' . $dak_row['doctor_name'] . ' ' . $dak_row['patient_phone'] ) ); ?>">
							<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Patient', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-primary"><?php echo esc_html( $dak_row['patient_name'] ); ?></span>
									<span class="dak-cell-sub is-tabular"><?php echo esc_html( '' !== (string) $dak_row['patient_phone'] ? $dak_row['patient_phone'] : sprintf( 'APT-%04d', $dak_row['id'] ) ); ?></span>
								</span>
							</td>
							<td class="dak-col-nowrap" data-label="<?php esc_attr_e( 'Date & time', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-strong is-tabular"><?php echo esc_html( false !== $dak_ts ? date_i18n( 'd M Y', $dak_ts ) : $dak_row['date'] ); ?></span>
									<span class="dak-cell-sub is-tabular"><?php echo esc_html( false !== $dak_ts ? date_i18n( 'h:i A', $dak_ts ) : $dak_row['time'] ); ?></span>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'Doctor', 'doctor-ak-portal' ); ?>"><?php echo esc_html( sprintf( 'Dr. %s', $dak_row['doctor_name'] ) ); ?></td>
							<td class="dak-col-status" data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-status-pill <?php echo esc_attr( isset( $dak_status_classes[ $dak_row['status'] ] ) ? $dak_status_classes[ $dak_row['status'] ] : 'dak-status-pill-is-neutral' ); ?>"><?php echo esc_html( $dak_row['status_label'] ); ?></span>
									<?php if ( $dak_row['is_overdue'] ) : ?>
										<span class="dak-cell-note"><?php esc_html_e( 'Time passed', 'doctor-ak-portal' ); ?></span>
									<?php endif; ?>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'Payment', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-strong is-tabular"><?php echo esc_html( call_user_func( $dak_money, $dak_row['charge'], __( 'Free', 'doctor-ak-portal' ) ) ); ?></span>
									<?php $dak_pay = $dak_row['is_paid'] ? array( __( 'Paid', 'doctor-ak-portal' ), 'dak-status-pill-is-active' ) : ( \DoctorAKPortal\Includes\Appointments::PAYMENT_STATUS_PENDING === $dak_row['payment_status'] ? array( __( 'Pending', 'doctor-ak-portal' ), 'dak-status-pill-is-pending' ) : array( __( 'Not recorded', 'doctor-ak-portal' ), 'dak-status-pill-is-neutral' ) ); ?>
									<span class="dak-pay-status <?php echo esc_attr( $dak_pay[1] ); ?>"><?php echo esc_html( $dak_pay[0] ); ?></span>
								</span>
							</td>
							<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
								<div class="dak-row-actions">
									<?php if ( $dak_row['is_overdue'] ) : ?>
										<?php
										// The appointment dialog only lives on the Appointments
										// section (this overview doesn't load it), so Reschedule
										// opens that list narrowed to this appointment's day, and
										// doctor-ak-admin-appointments.js opens its reschedule
										// dialog from the `reschedule` parameter.
										$dak_reschedule_url = add_query_arg(
											array(
												'section'    => 'appointments',
												'range'      => '',
												'date_from'  => $dak_row['date'],
												'date_to'    => $dak_row['date'],
												'reschedule' => $dak_row['id'],
											),
											$dak_dashboard_base
										) . '#dak-appointment-' . $dak_row['id'];
										?>
										<a class="dak-button dak-button-primary dak-button-sm" href="<?php echo esc_url( $dak_reschedule_url ); ?>" title="<?php esc_attr_e( 'This appointment\'s time has passed — reschedule it to a new date/time.', 'doctor-ak-portal' ); ?>"><?php esc_html_e( 'Reschedule', 'doctor-ak-portal' ); ?></a>
									<?php elseif ( $dak_can_check ) : ?>
										<button type="button" class="dak-button dak-button-primary dak-button-sm" data-check-in data-appointment-id="<?php echo esc_attr( $dak_row['id'] ); ?>" data-return-query="from=dashboard" title="<?php esc_attr_e( 'Check the patient in and open their encounter', 'doctor-ak-portal' ); ?>"><?php esc_html_e( 'Check in', 'doctor-ak-portal' ); ?></button>
									<?php elseif ( '' !== $dak_encounter_url ) : ?>
										<a class="dak-button dak-button-primary dak-button-sm" href="<?php echo esc_url( $dak_encounter_url ); ?>"><?php esc_html_e( 'Open encounter', 'doctor-ak-portal' ); ?></a>
									<?php elseif ( ! empty( $dak_row['video_call']['can_join'] ) ) : ?>
										<button type="button" class="dak-button dak-button-primary dak-button-sm" data-join-video-call data-room-url="<?php echo esc_url( $dak_row['video_call']['room_url'] ); ?>"><?php esc_html_e( 'Join call', 'doctor-ak-portal' ); ?></button>
									<?php endif; ?>

									<?php
									$dak_has_more = ! $dak_row['is_overdue'] && ( $dak_unpaid || ( ! empty( $dak_row['video_call']['can_join'] ) && ( $dak_can_check || '' !== $dak_encounter_url ) ) );
									?>
									<?php if ( $dak_has_more ) : ?>
										<details class="dak-row-menu">
											<summary class="dak-row-menu-toggle" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: patient name. */ __( 'More actions for %s', 'doctor-ak-portal' ), $dak_row['patient_name'] ) ); ?>"><?php esc_html_e( 'More', 'doctor-ak-portal' ); ?></summary>
											<div class="dak-row-menu-panel" role="menu">
												<?php if ( ! empty( $dak_row['video_call']['can_join'] ) && ( $dak_can_check || '' !== $dak_encounter_url ) ) : ?>
													<button type="button" class="dak-row-menu-item" role="menuitem" data-join-video-call data-room-url="<?php echo esc_url( $dak_row['video_call']['room_url'] ); ?>"><?php esc_html_e( 'Join video call', 'doctor-ak-portal' ); ?></button>
												<?php endif; ?>
												<?php if ( $dak_unpaid && 'online' === $dak_row['payment_mode'] ) : ?>
													<button type="button" class="dak-row-menu-item" role="menuitem" data-admin-appointment-pay-now data-appointment-id="<?php echo esc_attr( $dak_row['id'] ); ?>"><?php echo esc_html( sprintf( /* translators: %s: amount, e.g. "PKR 2,500". */ __( 'Collect online — %s', 'doctor-ak-portal' ), call_user_func( $dak_money, $dak_row['charge'] ) ) ); ?></button>
												<?php endif; ?>
												<?php if ( $dak_unpaid ) : ?>
													<button type="button" class="dak-row-menu-item" role="menuitem" data-admin-appointment-mark-paid data-appointment-id="<?php echo esc_attr( $dak_row['id'] ); ?>"><?php esc_html_e( 'Record cash / manual payment', 'doctor-ak-portal' ); ?></button>
												<?php endif; ?>
											</div>
										</details>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="dak-empty-state dak-results-empty dak-hidden" data-activity-empty><?php esc_html_e( 'No appointments match your search.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</section>
<div class="dak-dashboard-grid dak-dashboard-grid-charts">
	<?php if ( ! $is_receptionist ) : ?>
	<section class="dak-dashboard-card dak-cashflow">
		<?php
		$dak_rev_totals = wp_list_pluck( $revenue_chart, 'total' );
		$dak_rev_sum    = array_sum( $dak_rev_totals );
		$dak_rev_max    = max( 1.0, empty( $dak_rev_totals ) ? 0.0 : (float) max( $dak_rev_totals ) );
		$dak_rev_scale  = pow( 10, floor( log10( $dak_rev_max ) ) );
		$dak_rev_top    = ceil( $dak_rev_max / $dak_rev_scale ) * $dak_rev_scale;
		?>
		<div class="dak-cashflow-head">
			<div>
				<span class="dak-cashflow-label"><?php esc_html_e( 'Hospital revenue', 'doctor-ak-portal' ); ?></span>
				<strong class="dak-cashflow-total">PKR <?php echo esc_html( number_format_i18n( $dak_rev_sum ) ); ?></strong>
				<span class="dak-cashflow-note"><?php esc_html_e( "Clinic's own share of paid appointments", 'doctor-ak-portal' ); ?></span>
			</div>
			<span class="dak-cashflow-range"><?php esc_html_e( 'Last 14 days', 'doctor-ak-portal' ); ?></span>
		</div>
		<?php if ( 0.0 === (float) $dak_rev_sum ) : ?>
			<?php // A real zero, not a loading/failed state — the figure above is already computed server-side. An empty 14-bar chart with a 0–1 axis would only add noise. ?>
			<p class="dak-chart-empty"><?php esc_html_e( 'No paid revenue in the last 14 days. This chart fills in as appointments are marked paid.', 'doctor-ak-portal' ); ?></p>
		<?php else : ?>
			<div class="dak-cashflow-chart" role="img" aria-label="<?php esc_attr_e( 'Bar chart of daily paid revenue over the last 14 days', 'doctor-ak-portal' ); ?>">
				<div class="dak-cashflow-axis">
					<?php foreach ( array( 1, 0.5, 0 ) as $dak_fraction ) : ?>
						<span><?php echo esc_html( number_format_i18n( $dak_rev_top * $dak_fraction ) ); ?></span>
					<?php endforeach; ?>
				</div>
				<div class="dak-cashflow-bars">
					<?php foreach ( array_values( $revenue_chart ) as $dak_i => $dak_point ) : ?>
						<div class="dak-cashflow-col" tabindex="0" aria-label="<?php echo esc_attr( $dak_point['label'] . ': PKR ' . number_format_i18n( $dak_point['total'] ) ); ?>">
							<span class="dak-cashflow-tip"><small><?php echo esc_html( $dak_point['label'] ); ?></small>PKR <?php echo esc_html( number_format_i18n( $dak_point['total'] ) ); ?></span>
							<span class="dak-cashflow-bar" style="height:<?php echo esc_attr( max( 2, round( $dak_point['total'] / $dak_rev_top * 100 ) ) ); ?>%"></span>
							<?php // Every other day labelled — 14 labels in this width collide; each bar still exposes its own date via the tooltip/aria-label. ?>
							<span class="dak-cashflow-xlabel"><?php echo 0 === $dak_i % 2 ? esc_html( $dak_point['label'] ) : ''; ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<?php echo $appointments_chart_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own admin-appointments-chart.php template, which escapes its own output. ?>
</div>

<?php if ( $can_review_doctor_requests ) : ?>
<section class="dak-results dak-overview-pending" aria-labelledby="dak-overview-pending-title">
	<div class="dak-results-tools">
		<div>
			<h2 class="dak-results-title" id="dak-overview-pending-title"><?php esc_html_e( 'Pending doctor approvals', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( $pending_doctors_count ) ); ?></span></h2>
			<p class="dak-results-subtitle"><?php esc_html_e( 'Approvals notify the doctor by email.', 'doctor-ak-portal' ); ?></p>
		</div>
		<?php if ( $doctor_requests_url && ! empty( $pending_doctors ) ) : ?>
			<a class="dak-text-action" href="<?php echo esc_url( $doctor_requests_url ); ?>"><?php esc_html_e( 'View all requests', 'doctor-ak-portal' ); ?></a>
		<?php endif; ?>
	</div>

	<?php if ( empty( $pending_doctors ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No doctors awaiting approval.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-data-table-wrap">
			<table class="dak-data-table dak-ui-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Applicant', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Specialization', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Location', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $pending_doctors as $dak_doctor ) : ?>
						<?php $dak_doc_specs = array_values( array_filter( (array) $dak_doctor['specialization_labels'] ) ); ?>
						<tr data-row>
							<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Applicant', 'doctor-ak-portal' ); ?>"><span class="dak-cell-primary"><?php echo esc_html( sprintf( 'Dr. %s', $dak_doctor['name'] ) ); ?></span></td>
							<td data-label="<?php esc_attr_e( 'Specialization', 'doctor-ak-portal' ); ?>">
								<?php if ( empty( $dak_doc_specs ) ) : ?>
									<span class="dak-cell-sub"><?php esc_html_e( 'Not set', 'doctor-ak-portal' ); ?></span>
								<?php else : ?>
									<?php echo esc_html( $dak_doc_specs[0] ); ?>
									<?php if ( count( $dak_doc_specs ) > 1 ) : ?>
										<span class="dak-cell-sub"><?php echo esc_html( sprintf( ' +%d', count( $dak_doc_specs ) - 1 ) ); ?></span>
									<?php endif; ?>
								<?php endif; ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Location', 'doctor-ak-portal' ); ?>"><?php $dak_doc_place = implode( ', ', array_filter( array( $dak_doctor['city'], $dak_doctor['country'] ) ) ); ?><?php if ( '' !== $dak_doc_place ) : ?><?php echo esc_html( $dak_doc_place ); ?><?php else : ?><span class="dak-cell-sub"><?php esc_html_e( 'Not set', 'doctor-ak-portal' ); ?></span><?php endif; ?></td>
							<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
								<div class="dak-row-actions">
									<?php if ( $doctor_requests_url ) : ?>
										<a class="dak-text-action" href="<?php echo esc_url( $doctor_requests_url ); ?>"><?php esc_html_e( 'Review', 'doctor-ak-portal' ); ?></a>
									<?php endif; ?>
									<button type="button" class="dak-button dak-button-primary dak-button-sm" data-doctor-request-approve data-user-id="<?php echo esc_attr( $dak_doctor['id'] ); ?>"><?php esc_html_e( 'Approve', 'doctor-ak-portal' ); ?></button>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>
<?php endif; ?>
</div>