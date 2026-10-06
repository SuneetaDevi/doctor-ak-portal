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
<?php
$dak_live_attrs  = ' data-live-filter="doctor_ak_patient_appointments_filter" data-live-filter-target="#dak-patient-appointments-tab-content" data-live-filter-nonce="dakPatientDashboard"';
$dak_has_filters = '' !== $selected_date || '' !== $selected_status || 'upcoming' !== $selected_range || '' !== $selected_search;
?>
<div class="dak-list-page">
<div class="dak-list-toolbar">
	<form method="get" class="dak-list-filters"<?php echo $dak_live_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string. ?>>
		<input type="hidden" name="tab" value="appointments">
		<div class="dak-field is-search">
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
			<label for="dak-patient-appt-filter-status"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></label>
			<select id="dak-patient-appt-filter-status" name="status">
				<option value=""><?php esc_html_e( 'All statuses', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $status_options as $status_slug => $status_label ) : ?>
					<option value="<?php echo esc_attr( $status_slug ); ?>" <?php selected( $selected_status, $status_slug ); ?>><?php echo esc_html( $status_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="dak-field">
			<label for="dak-patient-appt-filter-date"><?php esc_html_e( 'Date', 'doctor-ak-portal' ); ?></label>
			<input type="date" id="dak-patient-appt-filter-date" name="date" value="<?php echo esc_attr( $selected_date ); ?>">
		</div>
		<div class="dak-list-filter-actions">
			<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Apply', 'doctor-ak-portal' ); ?></button>
			<?php if ( $dak_has_filters ) : ?>
				<a class="dak-button dak-button-secondary" href="?tab=appointments" data-live-filter-clear<?php echo $dak_live_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string. ?>><?php esc_html_e( 'Clear', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	</form>
</div>

<section class="dak-results" aria-labelledby="dak-patient-appts-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-patient-appts-title"><?php esc_html_e( 'Your appointments', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( number_format_i18n( count( $rows ) ) ); ?></span></h2>
		<button type="button" class="dak-button dak-button-secondary dak-button-sm" data-dak-book-appointment><?php esc_html_e( 'Book appointment', 'doctor-ak-portal' ); ?></button>
	</div>

	<?php if ( empty( $rows ) && $dak_has_filters ) : ?>
		<div class="dak-empty-state">
			<p><?php esc_html_e( 'No appointments match these filters.', 'doctor-ak-portal' ); ?></p>
			<a class="dak-button dak-button-secondary dak-button-sm" href="?tab=appointments" data-live-filter-clear<?php echo $dak_live_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string. ?>><?php esc_html_e( 'Clear filters', 'doctor-ak-portal' ); ?></a>
		</div>
	<?php elseif ( empty( $rows ) ) : ?>
		<div class="dak-empty-state">
			<p><?php esc_html_e( 'You have no upcoming appointments.', 'doctor-ak-portal' ); ?></p>
			<button type="button" class="dak-button dak-button-primary dak-button-sm" data-dak-book-appointment><?php esc_html_e( 'Book appointment', 'doctor-ak-portal' ); ?></button>
		</div>
	<?php else : ?>
		<div class="dak-data-table-wrap">
			<table class="dak-data-table dak-ui-table dak-patient-appointments-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date & time', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Payment', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$dak_ts        = strtotime( $row['date'] . ' ' . $row['time'] );
						$dak_can_pay   = ! $row['is_paid'] && (float) $row['charge'] > 0 && ! in_array( $row['status'], array( 'cancelled', 'completed' ), true );
						$dak_can_ref   = 'cancelled' === $row['status'] && $row['is_paid'] && 'online' === $row['payment_mode'] && '' === $row['refund_status'];
						$dak_can_join  = ! empty( $row['video_call']['can_join'] );
						$dak_has_more  = ( $dak_can_join && $dak_can_pay ) || $dak_can_ref || ! empty( $row['reschedulable'] );
						$dak_pay       = $row['is_paid'] ? array( __( 'Paid', 'doctor-ak-portal' ), 'dak-status-pill-is-active' ) : ( (float) $row['charge'] > 0 ? array( __( 'Pending', 'doctor-ak-portal' ), 'dak-status-pill-is-pending' ) : array( __( 'Nothing to pay', 'doctor-ak-portal' ), 'dak-status-pill-is-neutral' ) );
						$dak_visit     = 'video' === $row['type'] ? __( 'Video visit', 'doctor-ak-portal' ) : ( ! empty( $row['clinic_name'] ) ? $row['clinic_name'] : $row['type_label'] );
						?>
						<tr id="dak-appointment-<?php echo esc_attr( $row['id'] ); ?>" data-row data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>">
							<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Doctor', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-primary"><?php echo esc_html( sprintf( 'Dr. %s', $row['doctor_name'] ) ); ?></span>
									<span class="dak-cell-sub"><?php echo esc_html( '' !== $row['service_name'] ? $row['service_name'] : $row['type_label'] ); ?></span>
									<span class="dak-cell-sub"><?php echo esc_html( $dak_visit ); ?></span>
								</span>
							</td>
							<td class="dak-col-nowrap" data-label="<?php esc_attr_e( 'Date & time', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-cell-strong is-tabular"><?php echo esc_html( false !== $dak_ts ? date_i18n( 'd M Y', $dak_ts ) : $row['date'] ); ?></span>
									<span class="dak-cell-sub is-tabular"><?php echo esc_html( false !== $dak_ts ? date_i18n( 'h:i A', $dak_ts ) : $row['time'] ); ?></span>
								</span>
							</td>
							<td class="dak-col-status" data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>">
								<span class="dak-cell-stack">
									<span class="dak-status-pill <?php echo esc_attr( \DoctorAKPortal\Includes\Dashboard_Format::status_class( $row['status'] ) ); ?>"><?php echo esc_html( $row['status_label'] ); ?></span>
									<?php if ( 'requested' === $row['refund_status'] ) : ?>
										<span class="dak-cell-note"><?php esc_html_e( 'Refund requested', 'doctor-ak-portal' ); ?></span>
									<?php elseif ( 'processed' === $row['refund_status'] ) : ?>
										<span class="dak-cell-note"><?php esc_html_e( 'Refund processed', 'doctor-ak-portal' ); ?></span>
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
									<?php if ( $dak_can_join ) : ?>
										<button type="button" class="dak-button dak-button-primary dak-button-sm" data-join-video-call data-room-url="<?php echo esc_url( $row['video_call']['room_url'] ); ?>"><?php esc_html_e( 'Join call', 'doctor-ak-portal' ); ?></button>
									<?php elseif ( $dak_can_pay ) : ?>
										<button type="button" class="dak-button dak-button-primary dak-button-sm" data-pay-now data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"><?php echo esc_html( sprintf( /* translators: %s: amount, e.g. "PKR 2,500". */ __( 'Pay %s', 'doctor-ak-portal' ), \DoctorAKPortal\Includes\Dashboard_Format::money( $row['charge'] ) ) ); ?></button>
									<?php endif; ?>
									<?php if ( $dak_has_more ) : ?>
										<details class="dak-row-menu">
											<summary class="dak-row-menu-toggle" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: doctor name. */ __( 'More actions for your appointment with Dr. %s', 'doctor-ak-portal' ), $row['doctor_name'] ) ); ?>"><?php esc_html_e( 'More', 'doctor-ak-portal' ); ?></summary>
											<div class="dak-row-menu-panel" role="menu">
												<?php if ( $dak_can_join && $dak_can_pay ) : ?>
													<button type="button" class="dak-row-menu-item" role="menuitem" data-pay-now data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"><?php echo esc_html( sprintf( /* translators: %s: amount. */ __( 'Pay %s', 'doctor-ak-portal' ), \DoctorAKPortal\Includes\Dashboard_Format::money( $row['charge'] ) ) ); ?></button>
												<?php endif; ?>
												<?php if ( ! empty( $row['reschedulable'] ) ) : ?>
													<button type="button" class="dak-row-menu-item" role="menuitem" data-reschedule-appointment data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>" data-date="<?php echo esc_attr( $row['date'] ); ?>" data-time="<?php echo esc_attr( $row['time'] ); ?>"><?php esc_html_e( 'Reschedule', 'doctor-ak-portal' ); ?></button>
												<?php endif; ?>
												<?php if ( $dak_can_ref ) : ?>
													<button type="button" class="dak-row-menu-item" role="menuitem" data-request-refund data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"><?php esc_html_e( 'Request refund', 'doctor-ak-portal' ); ?></button>
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
	<?php endif; ?>
</section>
</div>