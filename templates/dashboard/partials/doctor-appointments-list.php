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

// Encounter links/check-ins from this tab come back here with the same
// filters (see Encounter_Return).
$dak_return_args  = \DoctorAKPortal\Includes\Encounter_Return::link_args( 'appointments', $filters );
$dak_return_query = http_build_query( $dak_return_args, '', '&' );
?>
<?php
$dak_live_attrs     = ' data-live-filter="doctor_ak_doctor_appointments_filter" data-live-filter-target="#dak-doctor-appointments-tab-content" data-live-filter-nonce="dakDoctorAppointments"';
$dak_status_classes = array(
	'confirmed'       => 'dak-status-pill-is-confirmed',
	'pending_payment' => 'dak-status-pill-is-pending',
	'paid'            => 'dak-status-pill-is-active',
	'checked_in'      => 'dak-status-pill-is-checked-in',
	'completed'       => 'dak-status-pill-is-neutral',
	'cancelled'       => 'dak-status-pill-is-neutral',
	'rescheduled'     => 'dak-status-pill-is-rescheduled',
);
$dak_dashboard_url  = \DoctorAKPortal\Includes\Page_Finder::url_for_shortcode( \DoctorAKPortal\Frontend\Doctor_Dashboard::SHORTCODE_TAG );
?>
<div class="dak-list-page">
<div class="dak-page-head">
	<div>
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
</div>

<div class="dak-list-toolbar">
	<form
		method="get"
		action="<?php echo esc_url( $appointments_url ); ?>"
		class="dak-list-filters"
		<?php echo $dak_live_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string. ?>
	>
		<input type="hidden" name="tab" value="appointments">

		<div class="dak-field is-search">
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
			<label for="dak-doctor-appt-filter-payment-status"><?php esc_html_e( 'Payment status', 'doctor-ak-portal' ); ?></label>
			<select id="dak-doctor-appt-filter-payment-status" name="payment_status">
				<option value=""><?php esc_html_e( 'All', 'doctor-ak-portal' ); ?></option>
				<option value="paid" <?php selected( $filters['payment_status'], 'paid' ); ?>><?php esc_html_e( 'Paid', 'doctor-ak-portal' ); ?></option>
				<option value="pending" <?php selected( $filters['payment_status'], 'pending' ); ?>><?php esc_html_e( 'Pending', 'doctor-ak-portal' ); ?></option>
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

		<div class="dak-list-filter-actions">
			<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Apply', 'doctor-ak-portal' ); ?></button>
			<?php if ( $dak_has_filters ) : ?>
				<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $appointments_url ); ?>" data-live-filter-clear<?php echo $dak_live_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string. ?>><?php esc_html_e( 'Clear', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	</form>
</div>

<section class="dak-results" aria-labelledby="dak-doctor-appts-title">
	<div class="dak-results-tools">
		<div>
			<h2 class="dak-results-title" id="dak-doctor-appts-title"><?php esc_html_e( 'Appointments', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $rows ) ) ); ?></span></h2>
			<p class="dak-results-subtitle"><?php esc_html_e( 'Rescheduling closes 30 minutes before the start.', 'doctor-ak-portal' ); ?></p>
		</div>
	</div>

	<?php if ( empty( $rows ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No appointments match these filters.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-data-table-wrap">
			<table class="dak-data-table dak-ui-table dak-doctor-appointments-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Patient', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date & time', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Visit', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Payment', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php $dak_report_counts = \DoctorAKPortal\Frontend\Appointment_Reports_Handler::counts( array_column( $rows, 'id' ) ); ?>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$dak_ts         = strtotime( $row['date'] . ' ' . $row['time'] );
						$dak_is_video   = 'video' === $row['type'];
						$dak_unpaid     = ! $row['is_paid'] && (float) $row['charge'] > 0;
						$dak_can_finish = in_array( $row['status'], array( 'confirmed', 'paid', 'rescheduled' ), true ) && ( $row['is_paid'] || (float) $row['charge'] <= 0 );
						$dak_can_check  = $dak_can_finish && ! $row['is_overdue'];
						$dak_encounter_url = '';

						if ( 'checked_in' === $row['status'] ) {
							$dak_open_encounter = \DoctorAKPortal\Includes\Encounters::find_by_appointment( $row['id'], \DoctorAKPortal\Includes\Encounters::STATUS_OPEN );
							$dak_encounter_url  = $dak_open_encounter ? add_query_arg( array_merge( array( 'tab' => 'encounter', 'encounter_id' => $dak_open_encounter['id'] ), $dak_return_args ), $dak_dashboard_url ) : '';
						}

						// One visible workflow action, same conditions as before.
						$dak_workflow = '';

						if ( ! empty( $row['video_call']['can_join'] ) ) {
							$dak_workflow = 'join';
						} elseif ( $dak_can_check ) {
							$dak_workflow = 'check_in';
						} elseif ( '' !== $dak_encounter_url ) {
							$dak_workflow = 'encounter';
						}

						$dak_reschedule_reason = '';

						if ( empty( $row['reschedulable'] ) ) {
							$dak_reschedule_reason = in_array( $row['status'], array( 'cancelled', 'completed' ), true )
								? __( 'This appointment can no longer be rescheduled.', 'doctor-ak-portal' )
								: __( 'Rescheduling closes 30 minutes before the appointment.', 'doctor-ak-portal' );
						}

						$dak_pay = $row['is_paid']
							? array( __( 'Paid', 'doctor-ak-portal' ), 'dak-status-pill-is-active' )
							: ( \DoctorAKPortal\Includes\Appointments::PAYMENT_STATUS_PENDING === $row['payment_status'] ? array( __( 'Pending', 'doctor-ak-portal' ), 'dak-status-pill-is-pending' ) : array( __( 'Not recorded', 'doctor-ak-portal' ), 'dak-status-pill-is-neutral' ) );
						$dak_visit_line = '' !== $row['clinic_name'] ? $row['clinic_name'] : ( $dak_is_video ? __( 'Video visit', 'doctor-ak-portal' ) : $row['type_label'] );
						$dak_contact    = array_filter( array( $row['patient_phone'], '' !== (string) $row['patient_age'] ? sprintf( /* translators: %d: patient's age in years. */ __( '%d yrs', 'doctor-ak-portal' ), $row['patient_age'] ) : '' ) );
						?>
						<tr id="dak-appointment-<?php echo esc_attr( $row['id'] ); ?>" data-row>
							<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Patient', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-primary"><?php echo esc_html( $row['patient_name'] ); ?></span>
									<span class="dak-cell-sub dak-cell-id"><?php echo esc_html( sprintf( 'APT-%04d', $row['id'] ) ); ?></span>
									<?php if ( ! empty( $dak_contact ) ) : ?>
										<span class="dak-cell-sub is-tabular"><?php echo esc_html( implode( ' · ', $dak_contact ) ); ?></span>
									<?php endif; ?>
								</span>
							</td>
							<td class="dak-col-nowrap" data-label="<?php esc_attr_e( 'Date & time', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-strong is-tabular"><?php echo esc_html( false !== $dak_ts ? date_i18n( 'd M Y', $dak_ts ) : $row['date'] ); ?></span>
									<span class="dak-cell-sub is-tabular"><?php echo esc_html( false !== $dak_ts ? date_i18n( 'h:i A', $dak_ts ) : $row['time'] ); ?></span>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'Visit', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<?php if ( '' !== $row['service_name'] && ! ( $dak_is_video && 0 === strcasecmp( trim( $row['service_name'] ), __( 'Video Consultation', 'doctor-ak-portal' ) ) ) ) : ?>
										<span><?php echo esc_html( $row['service_name'] ); ?></span>
									<?php endif; ?>
									<span class="dak-cell-sub"><?php echo esc_html( $dak_visit_line ); ?></span>
									<?php echo $dak_is_video ? '' : \DoctorAKPortal\Includes\Dashboard_Format::map_link_html( $row['clinic_map_url'], $row['clinic_name'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside map_link_html(). ?>
								</span>
							</td>
							<td class="dak-col-status" data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-status-pill <?php echo esc_attr( isset( $dak_status_classes[ $row['status'] ] ) ? $dak_status_classes[ $row['status'] ] : 'dak-status-pill-is-neutral' ); ?>"><?php echo esc_html( $row['status_label'] ); ?></span>
									<?php if ( $row['is_overdue'] ) : ?>
										<span class="dak-cell-note"><?php esc_html_e( 'Time passed', 'doctor-ak-portal' ); ?></span>
									<?php endif; ?>
								</span>
							</td>
							<td data-label="<?php esc_attr_e( 'Payment', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-strong is-tabular"><?php echo esc_html( \DoctorAKPortal\Includes\Dashboard_Format::money( $row['charge'], __( 'Free', 'doctor-ak-portal' ) ) ); ?></span>
									<span class="dak-pay-status <?php echo esc_attr( $dak_pay[1] ); ?>"><?php echo esc_html( $dak_pay[0] ); ?></span>
								</span>
							</td>
							<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
								<div class="dak-row-actions">
									<?php if ( 'join' === $dak_workflow ) : ?>
										<button type="button" class="dak-button dak-button-primary dak-button-sm" data-join-video-call data-room-url="<?php echo esc_url( $row['video_call']['room_url'] ); ?>"><?php esc_html_e( 'Join call', 'doctor-ak-portal' ); ?></button>
									<?php elseif ( 'check_in' === $dak_workflow ) : ?>
										<button type="button" class="dak-button dak-button-primary dak-button-sm" data-check-in data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>" data-return-query="<?php echo esc_attr( $dak_return_query ); ?>" title="<?php esc_attr_e( 'Check the patient in and open their encounter', 'doctor-ak-portal' ); ?>"><?php esc_html_e( 'Check in', 'doctor-ak-portal' ); ?></button>
									<?php elseif ( 'encounter' === $dak_workflow ) : ?>
										<a class="dak-button dak-button-primary dak-button-sm" href="<?php echo esc_url( $dak_encounter_url ); ?>"><?php esc_html_e( 'Open encounter', 'doctor-ak-portal' ); ?></a>
									<?php endif; ?>

									<?php
									$dak_report_count = isset( $dak_report_counts[ $row['id'] ] ) ? $dak_report_counts[ $row['id'] ] : 0;

									if ( 'cancelled' !== $row['status'] || $dak_report_count > 0 ) :
										?>
										<button type="button" class="dak-button dak-button-secondary dak-button-sm dak-reports-button" data-appointment-reports data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>" data-appointment-label="<?php echo esc_attr( $row['patient_name'] . ( false !== $dak_ts ? ' · ' . date_i18n( 'd M Y, h:i A', $dak_ts ) : '' ) ); ?>">
											<?php esc_html_e( 'Reports', 'doctor-ak-portal' ); ?>
											<span class="dak-reports-count" data-appointment-reports-count="<?php echo esc_attr( $row['id'] ); ?>"<?php echo $dak_report_count > 0 ? '' : ' hidden'; ?>><?php echo $dak_report_count > 0 ? esc_html( $dak_report_count ) : ''; ?></span>
										</button>
									<?php endif; ?>

									<details class="dak-row-menu">
										<summary class="dak-row-menu-toggle" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: patient name. */ __( 'More actions for %s', 'doctor-ak-portal' ), $row['patient_name'] ) ); ?>"><?php esc_html_e( 'More', 'doctor-ak-portal' ); ?></summary>
										<div class="dak-row-menu-panel" role="menu">
											<?php if ( 'check_in' !== $dak_workflow && $dak_can_check ) : ?>
												<button type="button" class="dak-row-menu-item" role="menuitem" data-check-in data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>" data-return-query="<?php echo esc_attr( $dak_return_query ); ?>"><?php esc_html_e( 'Check in', 'doctor-ak-portal' ); ?></button>
											<?php endif; ?>
											<?php if ( 'encounter' !== $dak_workflow && '' !== $dak_encounter_url ) : ?>
												<a class="dak-row-menu-item" role="menuitem" href="<?php echo esc_url( $dak_encounter_url ); ?>"><?php esc_html_e( 'Open encounter', 'doctor-ak-portal' ); ?></a>
											<?php endif; ?>
											<?php if ( $dak_unpaid && 'online' === $row['payment_mode'] ) : ?>
												<button type="button" class="dak-row-menu-item" role="menuitem" data-doctor-pay-now data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"><?php echo esc_html( sprintf( /* translators: %s: amount, e.g. "PKR 2,500". */ __( 'Collect online — %s', 'doctor-ak-portal' ), \DoctorAKPortal\Includes\Dashboard_Format::money( $row['charge'] ) ) ); ?></button>
											<?php elseif ( $dak_unpaid ) : ?>
												<button type="button" class="dak-row-menu-item" role="menuitem" data-doctor-mark-paid data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"><?php echo esc_html( sprintf( /* translators: %s: amount, e.g. "PKR 2,500". */ __( 'Record payment — %s', 'doctor-ak-portal' ), \DoctorAKPortal\Includes\Dashboard_Format::money( $row['charge'] ) ) ); ?></button>
											<?php endif; ?>
											<?php if ( $dak_can_finish ) : ?>
												<button type="button" class="dak-row-menu-item" role="menuitem" data-mark-completed data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Mark completed', 'doctor-ak-portal' ); ?></button>
											<?php endif; ?>
											<button
												type="button"
												class="dak-row-menu-item"
												role="menuitem"
												data-reschedule-appointment
												data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"
												data-date="<?php echo esc_attr( $row['date'] ); ?>"
												data-time="<?php echo esc_attr( $row['time'] ); ?>"
												<?php if ( '' !== $dak_reschedule_reason ) : ?>
													title="<?php echo esc_attr( $dak_reschedule_reason ); ?>"
													aria-describedby="dak-reschedule-reason-<?php echo esc_attr( $row['id'] ); ?>"
												<?php endif; ?>
												<?php disabled( empty( $row['reschedulable'] ) ); ?>
											><?php esc_html_e( 'Reschedule', 'doctor-ak-portal' ); ?></button>
											<?php if ( '' !== $dak_reschedule_reason ) : ?>
												<span class="dak-row-menu-note" id="dak-reschedule-reason-<?php echo esc_attr( $row['id'] ); ?>"><?php echo esc_html( $dak_reschedule_reason ); ?></span>
											<?php endif; ?>
											<?php if ( ! in_array( $row['status'], array( 'cancelled', 'completed' ), true ) ) : ?>
												<hr class="dak-row-menu-sep">
												<button type="button" class="dak-row-menu-item is-danger" role="menuitem" data-doctor-cancel-appointment data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Cancel appointment', 'doctor-ak-portal' ); ?></button>
											<?php endif; ?>
										</div>
									</details>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>
</div>