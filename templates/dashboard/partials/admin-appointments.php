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
 * @var array  $range_options           Range slug => label ('', 'upcoming', 'past'), see Appointments::range_options().
 * @var array  $filters                 Active filter values: patient_id, date_from, date_to, doctor_id, payment_status, range, search.
 * @var bool   $is_receptionist         Whether the viewer is a Receptionist — full Add/Edit/Delete/Mark Paid access, but no Refund (Billing/Revenue stays administrator-only).
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
	'check'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M6.8 10.2l2.1 2.1 4.3-4.6"/></svg>',
);

$dak_appt_has_filters = '' !== $filters['date_from'] || '' !== $filters['date_to'] || $filters['doctor_id'] > 0 || '' !== $filters['payment_status'] || 'upcoming' !== $filters['range'] || '' !== $filters['search'];

// Summary stat cards + Today/Tomorrow/Upcoming/Earlier sectioning are both
// derived from the same already-filtered $appointments list — no extra
// queries, so the cards and the section counts always agree with what's
// actually shown below.
$dak_today    = current_time( 'Y-m-d' );
$dak_tomorrow = gmdate( 'Y-m-d', strtotime( $dak_today . ' +1 day' ) );

$dak_week_start = gmdate( 'Y-m-d', strtotime( 'monday this week', strtotime( $dak_today ) ) );
$dak_week_end   = gmdate( 'Y-m-d', strtotime( 'sunday this week', strtotime( $dak_today ) ) );

$dak_stat_today_video    = 0;
$dak_stat_today_clinic   = 0;
$dak_stat_awaiting_count = 0;
$dak_stat_awaiting_total = 0.0;
$dak_stat_time_passed    = 0;
$dak_stat_completed_week = 0;

$dak_buckets = array(
	'today'    => array(
		'label' => __( 'Today', 'doctor-ak-portal' ),
		'rows'  => array(),
	),
	'tomorrow' => array(
		'label' => __( 'Tomorrow', 'doctor-ak-portal' ),
		'rows'  => array(),
	),
	'upcoming' => array(
		'label' => __( 'Upcoming', 'doctor-ak-portal' ),
		'rows'  => array(),
	),
	'earlier'  => array(
		'label' => __( 'Earlier', 'doctor-ak-portal' ),
		'rows'  => array(),
	),
);

foreach ( $appointments as $dak_stat_row ) {
	if ( $dak_stat_row['date'] === $dak_today ) {
		$dak_buckets['today']['rows'][] = $dak_stat_row;

		if ( 'video' === $dak_stat_row['type'] ) {
			++$dak_stat_today_video;
		} else {
			++$dak_stat_today_clinic;
		}
	} elseif ( $dak_stat_row['date'] === $dak_tomorrow ) {
		$dak_buckets['tomorrow']['rows'][] = $dak_stat_row;
	} elseif ( $dak_stat_row['date'] > $dak_tomorrow ) {
		$dak_buckets['upcoming']['rows'][] = $dak_stat_row;
	} else {
		$dak_buckets['earlier']['rows'][] = $dak_stat_row;
	}

	if ( ! $dak_stat_row['is_paid'] && (float) $dak_stat_row['charge'] > 0 ) {
		++$dak_stat_awaiting_count;
		$dak_stat_awaiting_total += (float) $dak_stat_row['charge'];
	}

	if ( ! empty( $dak_stat_row['is_overdue'] ) ) {
		++$dak_stat_time_passed;
	}

	if ( 'completed' === $dak_stat_row['status'] && $dak_stat_row['date'] >= $dak_week_start && $dak_stat_row['date'] <= $dak_week_end ) {
		++$dak_stat_completed_week;
	}
}
?>

<div class="dak-appt-stats-grid">
	<div class="dak-appt-stat-card">
		<span class="dak-appt-stat-label"><?php esc_html_e( 'Today', 'doctor-ak-portal' ); ?></span>
		<strong class="dak-appt-stat-value"><?php echo esc_html( count( $dak_buckets['today']['rows'] ) ); ?></strong>
		<span class="dak-appt-stat-sub">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: video appointment count, 2: clinic appointment count. */
					__( '%1$d video · %2$d clinic', 'doctor-ak-portal' ),
					$dak_stat_today_video,
					$dak_stat_today_clinic
				)
			);
			?>
		</span>
	</div>
	<div class="dak-appt-stat-card">
		<span class="dak-appt-stat-label"><?php esc_html_e( 'Awaiting payment', 'doctor-ak-portal' ); ?></span>
		<strong class="dak-appt-stat-value"><?php echo esc_html( 'PKR ' . number_format( $dak_stat_awaiting_total, 0 ) ); ?></strong>
		<span class="dak-appt-stat-sub">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of appointments. */
					_n( 'across %d appointment', 'across %d appointments', $dak_stat_awaiting_count, 'doctor-ak-portal' ),
					$dak_stat_awaiting_count
				)
			);
			?>
		</span>
	</div>
	<div class="dak-appt-stat-card">
		<span class="dak-appt-stat-label"><?php esc_html_e( 'Time passed', 'doctor-ak-portal' ); ?></span>
		<strong class="dak-appt-stat-value"><?php echo esc_html( $dak_stat_time_passed ); ?></strong>
		<span class="dak-appt-stat-sub"><?php esc_html_e( 'need reschedule', 'doctor-ak-portal' ); ?></span>
	</div>
	<div class="dak-appt-stat-card">
		<span class="dak-appt-stat-label"><?php esc_html_e( 'Completed this week', 'doctor-ak-portal' ); ?></span>
		<strong class="dak-appt-stat-value"><?php echo esc_html( $dak_stat_completed_week ); ?></strong>
		<span class="dak-appt-stat-sub"><?php esc_html_e( 'Mon – Sun', 'doctor-ak-portal' ); ?></span>
	</div>
</div>

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
	<button type="button" class="dak-button dak-button-primary" id="dak-admin-appointment-add"><?php esc_html_e( '+ Add Appointment', 'doctor-ak-portal' ); ?></button>
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

<section class="dak-dashboard-card dak-appt-filters-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Filters', 'doctor-ak-portal' ); ?></h2>
	</div>

	<form
		method="get"
		action="<?php echo esc_url( $appointments_url ); ?>"
		class="dak-appt-filters-form"
		data-live-filter="doctor_ak_admin_appointments_filter"
		data-live-filter-target="#dak-admin-section-content"
		data-live-filter-nonce="dakAdminUsers"
	>
		<input type="hidden" name="section" value="appointments">
		<?php if ( $filters['patient_id'] > 0 ) : ?>
			<input type="hidden" name="patient_id" value="<?php echo esc_attr( $filters['patient_id'] ); ?>">
		<?php endif; ?>

		<div class="dak-field">
			<label for="dak-admin-appointments-filter-search"><?php esc_html_e( 'Search', 'doctor-ak-portal' ); ?></label>
			<input type="search" id="dak-admin-appointments-filter-search" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Patient, doctor, or guest name…', 'doctor-ak-portal' ); ?>">
		</div>

		<div class="dak-field">
			<label for="dak-admin-appointments-filter-range"><?php esc_html_e( 'Show', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-appointments-filter-range" name="range">
				<?php foreach ( $range_options as $dak_range_slug => $dak_range_label ) : ?>
					<option value="<?php echo esc_attr( $dak_range_slug ); ?>" <?php selected( $filters['range'], $dak_range_slug ); ?>><?php echo esc_html( $dak_range_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

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
			<select id="dak-admin-appointments-filter-doctor" name="doctor_id" class="dak-select-searchable" data-placeholder="<?php esc_attr_e( 'Search doctors…', 'doctor-ak-portal' ); ?>">
				<option value="0"><?php esc_html_e( 'All doctors', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $doctors as $dak_doctor_option ) : ?>
					<option value="<?php echo esc_attr( $dak_doctor_option->ID ); ?>" <?php selected( $filters['doctor_id'], $dak_doctor_option->ID ); ?>><?php echo esc_html( $dak_doctor_option->display_name ); ?></option>
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
				<a
					class="dak-button dak-button-secondary"
					href="<?php echo esc_url( $filters['patient_id'] > 0 ? add_query_arg( 'patient_id', $filters['patient_id'], $appointments_url ) : $appointments_url ); ?>"
					data-live-filter-clear
					data-live-filter="doctor_ak_admin_appointments_filter"
					data-live-filter-target="#dak-admin-section-content"
					data-live-filter-nonce="dakAdminUsers"
				><?php esc_html_e( 'Clear', 'doctor-ak-portal' ); ?></a>
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
		<div class="dak-appt-bulk-toolbar">
			<label class="dak-appt-select-all">
				<input type="checkbox" id="dak-appt-select-all">
				<span><?php esc_html_e( 'Select all', 'doctor-ak-portal' ); ?></span>
			</label>
			<div class="dak-appt-bulk-actions dak-hidden" id="dak-appt-bulk-actions">
				<span id="dak-appt-bulk-count">0 <?php esc_html_e( 'selected', 'doctor-ak-portal' ); ?></span>
				<button type="button" class="dak-button dak-button-secondary dak-button-sm" data-appt-bulk-mark-paid><?php esc_html_e( 'Mark paid', 'doctor-ak-portal' ); ?></button>
				<button type="button" class="dak-button dak-button-secondary dak-button-sm" data-appt-bulk-send-reminder><?php esc_html_e( 'Send reminder', 'doctor-ak-portal' ); ?></button>
				<button type="button" class="dak-button dak-button-secondary dak-button-sm" data-appt-bulk-clear><?php esc_html_e( 'Clear', 'doctor-ak-portal' ); ?></button>
			</div>
		</div>

		<?php foreach ( $dak_buckets as $dak_bucket ) : ?>
			<?php if ( empty( $dak_bucket['rows'] ) ) : ?>
				<?php continue; ?>
			<?php endif; ?>

			<div class="dak-appt-section">
				<div class="dak-appt-section-header">
					<span><?php echo esc_html( $dak_bucket['label'] ); ?></span>
					<span class="dak-appt-section-count"><?php echo esc_html( count( $dak_bucket['rows'] ) ); ?></span>
				</div>

				<?php foreach ( $dak_bucket['rows'] as $row ) : ?>
			<div id="dak-appointment-<?php echo esc_attr( $row['id'] ); ?>" class="dak-admin-record-row" data-appointment-row="<?php echo esc_attr( $row['id'] ); ?>">
				<div class="dak-admin-record-row-main">
					<label class="dak-appt-row-checkbox">
						<input type="checkbox" class="dak-appt-select" data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>">
					</label>
					<span class="dak-avatar dak-avatar-sm" aria-hidden="true"><?php echo esc_html( $row['patient_initials'] ); ?></span>
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( $row['patient_name'] ); ?></strong>
						<span class="dak-admin-record-row-id"><?php echo esc_html( sprintf( 'APT-%04d', $row['id'] ) ); ?></span>
					</span>
					<span class="dak-admin-record-row-meta">
						<?php echo esc_html( sprintf( /* translators: %s: doctor name. */ __( 'Dr. %s', 'doctor-ak-portal' ), $row['doctor_name'] ) ); ?><br>
						<span class="dak-clinic-card-meta"><?php echo esc_html( $row['datetime_label'] ); ?></span>
						<?php if ( '' !== $row['clinic_label'] ) : ?>
							<br><span class="dak-clinic-card-meta"><?php echo esc_html( $row['clinic_label'] ); ?></span>
						<?php endif; ?>
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
						<?php if ( $row['is_overdue'] ) : ?>
							<span class="dak-status-pill dak-status-pill-outline dak-status-pill-is-disabled"><?php esc_html_e( 'Time passed', 'doctor-ak-portal' ); ?></span>
						<?php endif; ?>
					</span>

					<span class="dak-admin-record-row-amount">
						<?php echo $row['charge'] > 0 ? esc_html( 'PKR ' . number_format( $row['charge'], 0 ) ) : esc_html__( 'Free', 'doctor-ak-portal' ); ?>
					</span>

					<span class="dak-admin-record-row-actions">
						<?php if ( $row['is_overdue'] ) : ?>
							<button
								type="button"
								class="dak-status-pill dak-status-pill-action"
								data-admin-appointment-edit
									data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"
									data-doctor-id="<?php echo esc_attr( $row['doctor_id'] ); ?>"
									data-patient-id="<?php echo esc_attr( $row['patient_id'] ); ?>"
									data-guest-name="<?php echo esc_attr( $row['guest_name'] ); ?>"
									data-guest-email="<?php echo esc_attr( $row['guest_email'] ); ?>"
									data-guest-phone="<?php echo esc_attr( $row['guest_phone'] ); ?>"
									data-type="<?php echo esc_attr( $row['type'] ); ?>"
									data-service-id="<?php echo esc_attr( $row['service_id'] ); ?>"
									data-service-ids="<?php echo esc_attr( wp_json_encode( ! empty( $row['service_ids'] ) ? $row['service_ids'] : array() ) ); ?>"
									data-date="<?php echo esc_attr( $row['date'] ); ?>"
									data-time="<?php echo esc_attr( $row['time'] ); ?>"
									data-status="<?php echo esc_attr( $row['status'] ); ?>"
									data-payment-status="<?php echo esc_attr( $row['payment_status'] ); ?>"
									data-payment-mode="<?php echo esc_attr( $row['payment_mode'] ); ?>"
									data-notes="<?php echo esc_attr( $row['notes'] ); ?>"
									title="<?php esc_attr_e( 'This appointment\'s time has passed — reschedule it to a new date/time.', 'doctor-ak-portal' ); ?>"
									aria-label="<?php esc_attr_e( 'Reschedule', 'doctor-ak-portal' ); ?>"
							><?php esc_html_e( 'Reschedule', 'doctor-ak-portal' ); ?></button>
						<?php else : ?>
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
									data-service-ids="<?php echo esc_attr( wp_json_encode( ! empty( $row['service_ids'] ) ? $row['service_ids'] : array() ) ); ?>"
									data-date="<?php echo esc_attr( $row['date'] ); ?>"
									data-time="<?php echo esc_attr( $row['time'] ); ?>"
									data-status="<?php echo esc_attr( $row['status'] ); ?>"
									data-payment-status="<?php echo esc_attr( $row['payment_status'] ); ?>"
									data-payment-mode="<?php echo esc_attr( $row['payment_mode'] ); ?>"
									data-notes="<?php echo esc_attr( $row['notes'] ); ?>"
									title="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
									aria-label="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
								><?php echo $dak_appt_icons['edit']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
							<?php if ( ! empty( $row['video_call']['can_join'] ) ) : ?>
								<a class="dak-status-pill dak-status-pill-action" href="<?php echo esc_url( $row['video_call']['room_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Join Call', 'doctor-ak-portal' ); ?></a>
							<?php endif; ?>
							<?php if ( in_array( $row['status'], array( 'confirmed', 'paid', 'rescheduled' ), true ) && ( $row['is_paid'] || (float) $row['charge'] <= 0 ) && ! $row['is_overdue'] ) : ?>
								<button type="button" class="dak-status-pill dak-status-pill-action" data-check-in data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>" title="<?php esc_attr_e( 'Check the patient in and open their encounter', 'doctor-ak-portal' ); ?>"><?php esc_html_e( 'Check In', 'doctor-ak-portal' ); ?></button>
							<?php elseif ( 'checked_in' === $row['status'] ) : ?>
								<?php
								$dak_open_encounter = \DoctorAKPortal\Includes\Encounters::find_by_appointment( $row['id'], \DoctorAKPortal\Includes\Encounters::STATUS_OPEN );
								$dak_encounter_url  = $dak_open_encounter ? add_query_arg( array( 'section' => 'encounter', 'encounter_id' => $dak_open_encounter['id'] ), \DoctorAKPortal\Includes\Page_Finder::url_for_shortcode( \DoctorAKPortal\Frontend\Admin_Dashboard::SHORTCODE_TAG ) ) : '';
								?>
								<?php if ( '' !== $dak_encounter_url ) : ?>
									<a class="dak-status-pill dak-status-pill-action" href="<?php echo esc_url( $dak_encounter_url ); ?>"><?php esc_html_e( 'Open Encounter', 'doctor-ak-portal' ); ?></a>
								<?php endif; ?>
							<?php endif; ?>
							<?php if ( ! $row['is_paid'] && (float) $row['charge'] > 0 ) : ?>
								<button
									type="button"
									class="dak-status-pill dak-status-pill-action"
									data-admin-appointment-pay-now
									data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"
									title="<?php esc_attr_e( 'Pay for this appointment', 'doctor-ak-portal' ); ?>"
								><?php echo esc_html( sprintf( /* translators: %s: amount. */ __( 'Pay PKR%s', 'doctor-ak-portal' ), number_format( (float) $row['charge'], 0 ) ) ); ?></button>
								<button
									type="button"
									class="dak-icon-button"
									data-admin-appointment-mark-paid
									data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"
									title="<?php esc_attr_e( 'Already collected? Mark this appointment as paid manually.', 'doctor-ak-portal' ); ?>"
									aria-label="<?php esc_attr_e( 'Mark Paid', 'doctor-ak-portal' ); ?>"
								><?php echo $dak_appt_icons['check']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
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
								data-datetime-label="<?php echo esc_attr( $row['datetime_label'] ); ?>"
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
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
