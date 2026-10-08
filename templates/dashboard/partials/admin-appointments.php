<?php
/**
 * Template: "Appointments" admin section — every booking across every
 * doctor and patient: a compact header with summary tiles, one filter
 * toolbar (with removable active-filter chips), and a single results table
 * grouped Today / Tomorrow / Upcoming / Earlier.
 *
 * Everything sits inside .dak-appts-page, which scopes this page's styles
 * (see section 18 of doctor-ak-dashboard-ui.css) so no other dashboard
 * screen is affected. All action data-* contracts are unchanged, so the
 * existing handlers in doctor-ak-admin-appointments.js / check-in /
 * video-call keep working.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $appointments            Rows from Appointments::all_for_admin().
 * @var string $filtered_patient        Name of the patient being filtered to, or '' if unfiltered.
 * @var string $appointments_url        Unfiltered URL of this section, for the filter form and "Clear filter" link.
 * @var array  $doctors                 Doctor users { ID, display_name }, for the filter's Doctor select.
 * @var array  $payment_status_options  Payment status slug => label.
 * @var array  $range_options           Range slug => label ('', 'upcoming', 'past'), see Appointments::range_options().
 * @var array  $filters                 Active filter values: patient_id, date_from, date_to, doctor_id, payment_status, range, search, sort ('desc'|'asc').
 * @var bool   $is_receptionist         Whether the viewer is a Receptionist — full Add/Edit/Delete/Mark Paid access, but no Refund (Billing/Revenue stays administrator-only).
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

// One formatter for every amount on this screen (tiles, list, details).
$dak_money = function ( $amount ) {
	return 'PKR ' . number_format_i18n( (float) $amount );
};

// Unambiguous date ("06 Oct 2026") — same stored date/time and the same
// date_i18n() call the rest of the plugin uses, so the scheduling timezone
// is unchanged; only the display pattern differs.
$dak_format_date = function ( $ymd ) {
	$ts = strtotime( (string) $ymd );

	return false !== $ts ? date_i18n( 'd M Y', $ts ) : (string) $ymd;
};

// ---- Filter state: live-filter attributes, chips, Clear all. ------------
$dak_live_attrs = ' data-live-filter="doctor_ak_admin_appointments_filter" data-live-filter-target="#dak-admin-section-content" data-live-filter-nonce="dakAdminUsers"';

// The complete current query (every filter, range always explicit — an
// absent `range` would otherwise be read as "All"), used to build each
// chip's "remove just this filter" link.
$dak_current_query = array(
	'range'          => $filters['range'],
	'search'         => $filters['search'],
	'doctor_id'      => $filters['doctor_id'] > 0 ? $filters['doctor_id'] : '',
	'date_from'      => $filters['date_from'],
	'date_to'        => $filters['date_to'],
	'payment_status' => $filters['payment_status'],
	'sort'           => $filters['sort'],
	'patient_id'     => $filters['patient_id'] > 0 ? $filters['patient_id'] : '',
);

$dak_without = function ( $key, $replacement = null ) use ( $dak_current_query, $appointments_url ) {
	$query = $dak_current_query;

	if ( null === $replacement ) {
		unset( $query[ $key ] );
	} else {
		$query[ $key ] = $replacement;
	}

	$query = array_filter(
		$query,
		function ( $value, $name ) {
			return 'range' === $name || '' !== (string) $value;
		},
		ARRAY_FILTER_USE_BOTH
	);

	return add_query_arg( $query, $appointments_url );
};

$dak_doctor_names = array();

foreach ( $doctors as $dak_doctor_option ) {
	$dak_doctor_names[ (int) $dak_doctor_option->ID ] = $dak_doctor_option->display_name;
}

$dak_chips = array();

if ( '' !== $filters['search'] ) {
	/* translators: %s: search text. */
	$dak_chips[] = array( sprintf( __( 'Search: “%s”', 'doctor-ak-portal' ), $filters['search'] ), $dak_without( 'search' ) );
}

if ( 'upcoming' !== $filters['range'] ) {
	$dak_range_label = isset( $range_options[ $filters['range'] ] ) ? $range_options[ $filters['range'] ] : $filters['range'];
	/* translators: %s: "All", "Past", … */
	$dak_chips[] = array( sprintf( __( 'Showing: %s', 'doctor-ak-portal' ), $dak_range_label ), $dak_without( 'range', 'upcoming' ) );
}

if ( $filters['doctor_id'] > 0 ) {
	$dak_chips[] = array(
		/* translators: %s: doctor name. */
		sprintf( __( 'Doctor: %s', 'doctor-ak-portal' ), isset( $dak_doctor_names[ (int) $filters['doctor_id'] ] ) ? $dak_doctor_names[ (int) $filters['doctor_id'] ] : '#' . (int) $filters['doctor_id'] ),
		$dak_without( 'doctor_id' ),
	);
}

if ( '' !== $filters['date_from'] ) {
	/* translators: %s: date. */
	$dak_chips[] = array( sprintf( __( 'From: %s', 'doctor-ak-portal' ), $dak_format_date( $filters['date_from'] ) ), $dak_without( 'date_from' ) );
}

if ( '' !== $filters['date_to'] ) {
	/* translators: %s: date. */
	$dak_chips[] = array( sprintf( __( 'To: %s', 'doctor-ak-portal' ), $dak_format_date( $filters['date_to'] ) ), $dak_without( 'date_to' ) );
}

if ( '' !== $filters['payment_status'] ) {
	$dak_chips[] = array(
		/* translators: %s: payment status. */
		sprintf( __( 'Payment: %s', 'doctor-ak-portal' ), isset( $payment_status_options[ $filters['payment_status'] ] ) ? $payment_status_options[ $filters['payment_status'] ] : $filters['payment_status'] ),
		$dak_without( 'payment_status' ),
	);
}

// "Clear all" returns to the default view (Upcoming, newest first), keeping
// a patient filter — that one has its own "Clear filter" in its banner.
$dak_clear_all_url = add_query_arg( array_filter( array( 'range' => 'upcoming', 'patient_id' => $filters['patient_id'] > 0 ? $filters['patient_id'] : '' ) ), $appointments_url );

$dak_more_active = (int) ( '' !== $filters['date_from'] ) + (int) ( '' !== $filters['date_to'] ) + (int) ( '' !== $filters['payment_status'] );
/* translators: %d: number of active filters inside "More filters". */
$dak_more_label = $dak_more_active > 0 ? sprintf( __( 'More filters (%d)', 'doctor-ak-portal' ), $dak_more_active ) : __( 'More filters', 'doctor-ak-portal' );
?>
<div class="dak-appts-page">

<div class="dak-page-head">
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
		<strong class="dak-appt-stat-value"><?php echo esc_html( $dak_money( $dak_stat_awaiting_total ) ); ?></strong>
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

<section class="dak-appts-toolbar" aria-label="<?php esc_attr_e( 'Filter appointments', 'doctor-ak-portal' ); ?>">
	<form
		method="get"
		action="<?php echo esc_url( $appointments_url ); ?>"
		id="dak-appt-filter-form"
		class="dak-appt-filters-form dak-appts-filters"
		<?php echo $dak_live_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string. ?>
	>
		<input type="hidden" name="section" value="appointments">
		<?php if ( $filters['patient_id'] > 0 ) : ?>
			<input type="hidden" name="patient_id" value="<?php echo esc_attr( $filters['patient_id'] ); ?>">
		<?php endif; ?>

		<div class="dak-field dak-appts-filter-search">
			<label for="dak-admin-appointments-filter-search"><?php esc_html_e( 'Search', 'doctor-ak-portal' ); ?></label>
			<input type="search" id="dak-admin-appointments-filter-search" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Name, phone or APT number…', 'doctor-ak-portal' ); ?>">
		</div>

		<div class="dak-field dak-appts-filter-range">
			<label for="dak-admin-appointments-filter-range"><?php esc_html_e( 'Show', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-appointments-filter-range" name="range">
				<?php foreach ( $range_options as $dak_range_slug => $dak_range_label ) : ?>
					<option value="<?php echo esc_attr( $dak_range_slug ); ?>" <?php selected( $filters['range'], $dak_range_slug ); ?>><?php echo esc_html( $dak_range_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="dak-field dak-appts-filter-doctor">
			<label for="dak-admin-appointments-filter-doctor"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-appointments-filter-doctor" name="doctor_id" class="dak-select-searchable" data-placeholder="<?php esc_attr_e( 'Search doctors…', 'doctor-ak-portal' ); ?>">
				<option value="0"><?php esc_html_e( 'All doctors', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $doctors as $dak_doctor_option ) : ?>
					<option value="<?php echo esc_attr( $dak_doctor_option->ID ); ?>" <?php selected( $filters['doctor_id'], $dak_doctor_option->ID ); ?>><?php echo esc_html( $dak_doctor_option->display_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="dak-appts-filter-buttons">
			<button
				type="button"
				class="dak-button dak-button-secondary dak-appts-more-toggle"
				data-dak-disclosure
				aria-expanded="<?php echo $dak_more_active > 0 ? 'true' : 'false'; ?>"
				aria-controls="dak-appts-more-filters"
				data-more-label="<?php echo esc_attr( $dak_more_label ); ?>"
				data-less-label="<?php esc_attr_e( 'Fewer filters', 'doctor-ak-portal' ); ?>"
			><?php echo $dak_more_active > 0 ? esc_html__( 'Fewer filters', 'doctor-ak-portal' ) : esc_html( $dak_more_label ); ?></button>
			<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Apply', 'doctor-ak-portal' ); ?></button>
		</div>

		<div class="dak-appts-more-filters" id="dak-appts-more-filters"<?php echo $dak_more_active > 0 ? '' : ' hidden'; ?>>
			<div class="dak-field">
				<label for="dak-admin-appointments-filter-date-from"><?php esc_html_e( 'From', 'doctor-ak-portal' ); ?></label>
				<input type="date" id="dak-admin-appointments-filter-date-from" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
			</div>

			<div class="dak-field">
				<label for="dak-admin-appointments-filter-date-to"><?php esc_html_e( 'To', 'doctor-ak-portal' ); ?></label>
				<input type="date" id="dak-admin-appointments-filter-date-to" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
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
		</div>
	</form>

	<?php if ( ! empty( $dak_chips ) ) : ?>
		<ul class="dak-appts-chips" aria-label="<?php esc_attr_e( 'Active filters', 'doctor-ak-portal' ); ?>">
			<?php foreach ( $dak_chips as $dak_chip ) : ?>
				<li>
					<a class="dak-appts-chip" href="<?php echo esc_url( $dak_chip[1] ); ?>" data-live-filter-clear<?php echo $dak_live_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string. ?>>
						<span><?php echo esc_html( $dak_chip[0] ); ?></span>
						<span class="dak-appts-chip-x" aria-hidden="true">&times;</span>
						<span class="dak-visually-hidden"><?php esc_html_e( '(remove filter)', 'doctor-ak-portal' ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
			<li>
				<a class="dak-appts-clear-all" href="<?php echo esc_url( $dak_clear_all_url ); ?>" data-live-filter-clear<?php echo $dak_live_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute string. ?>><?php esc_html_e( 'Clear all', 'doctor-ak-portal' ); ?></a>
			</li>
		</ul>
	<?php endif; ?>
</section>

<section class="dak-appts-results" aria-label="<?php esc_attr_e( 'Appointment results', 'doctor-ak-portal' ); ?>">
	<?php if ( empty( $appointments ) ) : ?>
		<p class="dak-empty-state">
			<?php
			echo esc_html(
				$dak_appt_has_filters || $filters['patient_id'] > 0
					? __( 'No appointments match these filters.', 'doctor-ak-portal' )
					: __( 'No appointments have been booked yet.', 'doctor-ak-portal' )
			);
			?>
		</p>
	<?php else : ?>
		<?php
		// Booking lifecycle status → badge colour (text always shown, so colour
		// is never the only cue). Completed/Cancelled are normal end states,
		// so neutral rather than red. Labels come from status_label unchanged.
		$dak_status_classes = array(
			'confirmed'       => 'dak-status-pill-is-confirmed',
			'pending_payment' => 'dak-status-pill-is-pending',
			'paid'            => 'dak-status-pill-is-active',
			'checked_in'      => 'dak-status-pill-is-checked-in',
			'completed'       => 'dak-status-pill-is-neutral',
			'cancelled'       => 'dak-status-pill-is-neutral',
			'rescheduled'     => 'dak-status-pill-is-rescheduled',
		);

		// Payment status comes only from payment_status — never from the
		// booking status.
		$dak_payment_labels = array(
			\DoctorAKPortal\Includes\Appointments::PAYMENT_STATUS_PAID    => array( __( 'Paid', 'doctor-ak-portal' ), 'dak-status-pill-is-active' ),
			\DoctorAKPortal\Includes\Appointments::PAYMENT_STATUS_PENDING => array( __( 'Pending', 'doctor-ak-portal' ), 'dak-status-pill-is-pending' ),
		);

		// Encounter links/check-ins from here come back to this list, with
		// these filters, at the same row (see Encounter_Return).
		$dak_return_args  = \DoctorAKPortal\Includes\Encounter_Return::link_args( 'appointments', $filters );
		$dak_return_query = http_build_query( $dak_return_args, '', '&' );
		$dak_dashboard    = \DoctorAKPortal\Includes\Page_Finder::url_for_shortcode( \DoctorAKPortal\Frontend\Admin_Dashboard::SHORTCODE_TAG );

		// The Edit modal's data contract (unchanged) for "Edit appointment".
		$dak_edit_attrs = function ( $row ) {
			$attrs = array(
				'data-appointment-id' => $row['id'],
				'data-doctor-id'      => $row['doctor_id'],
				'data-patient-id'     => $row['patient_id'],
				'data-guest-name'     => $row['guest_name'],
				'data-guest-email'    => $row['guest_email'],
				'data-guest-phone'    => $row['guest_phone'],
				'data-type'           => $row['type'],
				'data-service-id'     => $row['service_id'],
				'data-service-ids'    => wp_json_encode( ! empty( $row['service_ids'] ) ? array_values( $row['service_ids'] ) : array() ),
				'data-date'           => $row['date'],
				'data-time'           => $row['time'],
				'data-status'         => $row['status'],
				'data-payment-status' => $row['payment_status'],
				'data-payment-mode'   => $row['payment_mode'],
				'data-notes'          => $row['notes'],
			);
			$out   = ' data-admin-appointment-edit';

			foreach ( $attrs as $name => $value ) {
				$out .= ' ' . $name . '="' . esc_attr( $value ) . '"';
			}

			return $out;
		};

		// "Reschedule": the same dialog in its focused reschedule mode — only
		// what it needs to look up availability plus the read-only summary.
		$dak_reschedule_attrs = function ( $row, $date_label, $time_label, $appt_label ) {
			$attrs = array(
				'data-appointment-id'    => $row['id'],
				'data-appointment-label' => $appt_label,
				'data-doctor-id'         => $row['doctor_id'],
				'data-type'              => $row['type'],
				'data-clinic-id'         => 'video' === $row['type'] ? 0 : $row['clinic_id'],
				'data-date'              => $row['date'],
				'data-time'              => $row['time'],
				'data-date-label'        => $date_label,
				'data-time-label'        => $time_label,
				'data-patient-name'      => $row['patient_name'],
				'data-doctor-name'       => $row['doctor_name'],
				'data-service-name'      => $row['service_name'],
				'data-type-label'        => $row['type_label'],
				'data-clinic-name'       => $row['clinic_name'],
			);
			$out   = ' data-admin-appointment-reschedule';

			foreach ( $attrs as $name => $value ) {
				$out .= ' ' . $name . '="' . esc_attr( $value ) . '"';
			}

			return $out;
		};
		?>
		<div class="dak-appts-results-tools">
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

			<div class="dak-appts-sort">
				<label for="dak-admin-appointments-filter-sort"><?php esc_html_e( 'Sort', 'doctor-ak-portal' ); ?></label>
				<?php // Belongs to the filter form above via form="…", so it auto-applies like every other filter. ?>
				<select id="dak-admin-appointments-filter-sort" name="sort" form="dak-appt-filter-form">
					<option value="desc" <?php selected( $filters['sort'], 'desc' ); ?>><?php esc_html_e( 'Newest date first', 'doctor-ak-portal' ); ?></option>
					<option value="asc" <?php selected( $filters['sort'], 'asc' ); ?>><?php esc_html_e( 'Oldest date first', 'doctor-ak-portal' ); ?></option>
				</select>
			</div>
		</div>

		<div class="dak-data-table-wrap dak-appts-table-wrap">
			<table class="dak-data-table dak-appointments-table">
				<colgroup>
					<col class="dak-appts-col-select">
					<col class="dak-appts-col-patient">
					<col class="dak-appts-col-schedule">
					<col class="dak-appts-col-doctor">
					<col class="dak-appts-col-status">
					<col class="dak-appts-col-payment">
					<col class="dak-appts-col-actions">
				</colgroup>
				<thead>
					<tr>
						<th scope="col" class="dak-col-select"><span class="dak-visually-hidden"><?php esc_html_e( 'Select', 'doctor-ak-portal' ); ?></span></th>
						<th scope="col"><?php esc_html_e( 'Patient', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date & time', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Doctor & visit', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'doctor-ak-portal' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Payment', 'doctor-ak-portal' ); ?></th>
						<th scope="col" class="dak-col-actions"><?php esc_html_e( 'Actions', 'doctor-ak-portal' ); ?></th>
					</tr>
				</thead>

				<?php foreach ( $dak_buckets as $dak_bucket ) : ?>
					<?php if ( empty( $dak_bucket['rows'] ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>

					<tbody>
						<tr class="dak-data-table-group">
							<th scope="colgroup" colspan="7">
								<span><?php echo esc_html( $dak_bucket['label'] ); ?></span>
								<span class="dak-appt-section-count"><?php echo esc_html( count( $dak_bucket['rows'] ) ); ?></span>
							</th>
						</tr>

						<?php foreach ( $dak_bucket['rows'] as $row ) : ?>
							<?php
							$dak_ts          = strtotime( $row['date'] . ' ' . $row['time'] );
							$dak_date_label  = false !== $dak_ts ? date_i18n( 'd M Y', $dak_ts ) : $row['date'];
							$dak_time_label  = false !== $dak_ts ? date_i18n( 'h:i A', $dak_ts ) : $row['time'];
							$dak_appt_id     = sprintf( 'APT-%04d', $row['id'] );
							$dak_charge      = (float) $row['charge'] > 0 ? $dak_money( $row['charge'] ) : __( 'Free', 'doctor-ak-portal' );
							$dak_payment     = isset( $dak_payment_labels[ $row['payment_status'] ] ) ? $dak_payment_labels[ $row['payment_status'] ] : array( __( 'Not recorded', 'doctor-ak-portal' ), 'dak-status-pill-is-neutral' );
							$dak_status_cls  = isset( $dak_status_classes[ $row['status'] ] ) ? $dak_status_classes[ $row['status'] ] : 'dak-status-pill-is-neutral';
							$dak_is_video    = 'video' === $row['type'];
							$dak_can_collect = ! $row['is_paid'] && (float) $row['charge'] > 0;
							$dak_refund      = '';

							// Doctor & visit: service on line 2 unless it just
							// repeats the visit type ("Video Consultation" for a
							// video visit, which line 3 already says).
							$dak_show_service = '' !== $row['service_name'] && ! ( $dak_is_video && 0 === strcasecmp( trim( $row['service_name'] ), __( 'Video Consultation', 'doctor-ak-portal' ) ) );
							$dak_visit        = '' !== $row['clinic_name'] ? $row['clinic_name'] : ( $dak_is_video ? __( 'Video visit', 'doctor-ak-portal' ) : $row['type_label'] );

							if ( 'requested' === $row['refund_status'] ) {
								$dak_refund = __( 'Refund requested', 'doctor-ak-portal' );
							} elseif ( 'processed' === $row['refund_status'] ) {
								$dak_refund = (float) $row['refund_amount'] > 0
									/* translators: %s: refunded amount. */
									? sprintf( __( 'Refund processed (%s)', 'doctor-ak-portal' ), $dak_money( $row['refund_amount'] ) )
									: __( 'Refund processed', 'doctor-ak-portal' );
							}

							// One contextual workflow action — same conditions as
							// before. Overdue rows surface Reschedule (the existing
							// rule); everything else stays reachable under More.
							$dak_workflow      = '';
							$dak_encounter_url = '';

							if ( $row['is_overdue'] ) {
								$dak_workflow = 'reschedule';
							} elseif ( in_array( $row['status'], array( 'confirmed', 'paid', 'rescheduled' ), true ) && ( $row['is_paid'] || (float) $row['charge'] <= 0 ) ) {
								$dak_workflow = 'check_in';
							} elseif ( 'checked_in' === $row['status'] ) {
								$dak_open_encounter = \DoctorAKPortal\Includes\Encounters::find_by_appointment( $row['id'], \DoctorAKPortal\Includes\Encounters::STATUS_OPEN );

								if ( $dak_open_encounter && $dak_dashboard ) {
									$dak_workflow      = 'open_encounter';
									$dak_encounter_url = add_query_arg( array_merge( array( 'section' => 'encounter', 'encounter_id' => $dak_open_encounter['id'] ), $dak_return_args ), $dak_dashboard );
								}
							}

							if ( '' === $dak_workflow && ! empty( $row['video_call']['can_join'] ) ) {
								$dak_workflow = 'join_call';
							}

							$dak_view_payment_mode = 'online' === $row['payment_mode'] ? __( 'Online', 'doctor-ak-portal' ) : ( 'manual' === $row['payment_mode'] ? __( 'Manual', 'doctor-ak-portal' ) : '' );
							?>
							<tr id="dak-appointment-<?php echo esc_attr( $row['id'] ); ?>" data-row data-appointment-row="<?php echo esc_attr( $row['id'] ); ?>">
								<td class="dak-col-select" data-label="<?php esc_attr_e( 'Select', 'doctor-ak-portal' ); ?>">
									<input type="checkbox" class="dak-appt-select" data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: appointment id, 2: patient name. */ __( 'Select %1$s for %2$s', 'doctor-ak-portal' ), $dak_appt_id, $row['patient_name'] ) ); ?>">
								</td>

								<td class="dak-col-primary dak-appts-cell-patient" data-label="<?php esc_attr_e( 'Patient', 'doctor-ak-portal' ); ?>">
									<span class="dak-cell-stack">
										<span class="dak-cell-primary"><?php echo esc_html( $row['patient_name'] ); ?></span>
										<span class="dak-cell-sub is-tabular"><?php echo esc_html( $dak_appt_id ); ?></span>
										<?php if ( '' !== trim( (string) $row['patient_phone'] ) ) : ?>
											<a class="dak-cell-sub dak-cell-phone is-tabular" href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $row['patient_phone'] ) ); ?>"><?php echo esc_html( $row['patient_phone'] ); ?></a>
										<?php else : ?>
											<span class="dak-cell-sub dak-cell-note"><?php esc_html_e( 'No phone recorded', 'doctor-ak-portal' ); ?></span>
										<?php endif; ?>
									</span>
								</td>

								<td class="dak-appts-cell-schedule" data-label="<?php esc_attr_e( 'Date & time', 'doctor-ak-portal' ); ?>">
									<span class="dak-cell-stack">
										<span class="is-tabular"><?php echo esc_html( $dak_date_label ); ?></span>
										<span class="dak-cell-sub is-tabular"><?php echo esc_html( $dak_time_label ); ?></span>
									</span>
								</td>

								<td class="dak-appts-cell-doctor" data-label="<?php esc_attr_e( 'Doctor & visit', 'doctor-ak-portal' ); ?>">
									<span class="dak-cell-stack">
										<span><?php echo esc_html( sprintf( /* translators: %s: doctor name. */ __( 'Dr. %s', 'doctor-ak-portal' ), $row['doctor_name'] ) ); ?></span>
										<?php if ( $dak_show_service ) : ?>
											<span class="dak-cell-sub"><?php echo esc_html( $row['service_name'] ); ?></span>
										<?php endif; ?>
										<span class="dak-cell-sub"><?php echo esc_html( $dak_visit ); ?></span>
										<?php echo $dak_is_video ? '' : \DoctorAKPortal\Includes\Dashboard_Format::map_link_html( $row['clinic_map_url'], $row['clinic_name'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside map_link_html(). ?>
									</span>
								</td>

								<td class="dak-appts-cell-status" data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>">
									<span class="dak-cell-stack">
										<span class="dak-status-pill <?php echo esc_attr( $dak_status_cls ); ?>"><?php echo esc_html( $row['status_label'] ); ?></span>
										<?php if ( $row['is_overdue'] ) : ?>
											<span class="dak-cell-note"><?php esc_html_e( 'Time passed', 'doctor-ak-portal' ); ?></span>
										<?php endif; ?>
									</span>
								</td>

								<td class="dak-appts-cell-payment" data-label="<?php esc_attr_e( 'Payment', 'doctor-ak-portal' ); ?>">
									<span class="dak-cell-stack">
										<span class="dak-appts-amount is-tabular"><?php echo esc_html( $dak_charge ); ?></span>
										<span class="dak-appts-pay-status <?php echo esc_attr( $dak_payment[1] ); ?>"><?php echo esc_html( $dak_payment[0] ); ?></span>
										<?php if ( '' !== $dak_refund ) : ?>
											<span class="dak-cell-note"><?php echo esc_html( $dak_refund ); ?></span>
										<?php endif; ?>
									</span>
								</td>

								<td class="dak-col-actions dak-appts-cell-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
									<div class="dak-row-actions">
										<?php if ( 'check_in' === $dak_workflow ) : ?>
											<button type="button" class="dak-button dak-button-primary dak-button-sm" data-check-in data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>" data-return-query="<?php echo esc_attr( $dak_return_query ); ?>"><?php esc_html_e( 'Check in', 'doctor-ak-portal' ); ?></button>
										<?php elseif ( 'open_encounter' === $dak_workflow ) : ?>
											<a class="dak-button dak-button-primary dak-button-sm" href="<?php echo esc_url( $dak_encounter_url ); ?>"><?php esc_html_e( 'Open encounter', 'doctor-ak-portal' ); ?></a>
										<?php elseif ( 'join_call' === $dak_workflow ) : ?>
											<button type="button" class="dak-button dak-button-primary dak-button-sm" data-join-video-call data-room-url="<?php echo esc_url( $row['video_call']['room_url'] ); ?>"><?php esc_html_e( 'Join call', 'doctor-ak-portal' ); ?></button>
										<?php elseif ( 'reschedule' === $dak_workflow ) : ?>
											<button
												type="button"
												class="dak-button dak-button-primary dak-button-sm"
												<?php echo $dak_reschedule_attrs( $row, $dak_date_label, $dak_time_label, $dak_appt_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value escaped with esc_attr() inside $dak_reschedule_attrs. ?>
												title="<?php esc_attr_e( 'This appointment\'s time has passed — reschedule it to a new date/time.', 'doctor-ak-portal' ); ?>"
											><?php esc_html_e( 'Reschedule', 'doctor-ak-portal' ); ?></button>
										<?php endif; ?>

										<button
											type="button"
											class="dak-appts-action-link"
											data-admin-appointment-view
											data-appointment-label="<?php echo esc_attr( $dak_appt_id ); ?>"
											data-patient-name="<?php echo esc_attr( $row['patient_name'] ); ?>"
											data-patient-phone="<?php echo esc_attr( $row['patient_phone'] ); ?>"
											data-patient-age="<?php echo esc_attr( '' !== $row['patient_age'] ? sprintf( /* translators: %d: patient's age in years. */ __( '%d yrs', 'doctor-ak-portal' ), $row['patient_age'] ) : '' ); ?>"
											data-doctor-name="<?php echo esc_attr( $row['doctor_name'] ); ?>"
											data-type-label="<?php echo esc_attr( $row['type_label'] ); ?>"
											data-service-name="<?php echo esc_attr( $row['service_name'] ); ?>"
											data-date-label="<?php echo esc_attr( $dak_date_label ); ?>"
											data-time-label="<?php echo esc_attr( $dak_time_label ); ?>"
											data-clinic-name="<?php echo esc_attr( $row['clinic_name'] ); ?>"
											data-clinic-address="<?php echo esc_attr( $row['clinic_address'] ); ?>"
											data-status-label="<?php echo esc_attr( $row['status_label'] ); ?>"
											data-charge="<?php echo esc_attr( $dak_charge ); ?>"
											data-payment-status-label="<?php echo esc_attr( $dak_payment[0] ); ?>"
											data-payment-mode="<?php echo esc_attr( $dak_view_payment_mode ); ?>"
											data-refund-label="<?php echo esc_attr( $dak_refund ); ?>"
											data-online-order-id="<?php echo esc_attr( $row['online_order_id'] ); ?>"
											data-notes="<?php echo esc_attr( $row['notes'] ); ?>"
											data-print-url="<?php echo esc_url( \DoctorAKPortal\Frontend\Appointment_Handler::print_url( $row['id'] ) ); ?>"
											aria-label="<?php echo esc_attr( sprintf( /* translators: 1: appointment id, 2: patient name. */ __( 'View %1$s for %2$s', 'doctor-ak-portal' ), $dak_appt_id, $row['patient_name'] ) ); ?>"
										><?php esc_html_e( 'View', 'doctor-ak-portal' ); ?></button>

										<details class="dak-row-menu">
											<summary class="dak-row-menu-toggle dak-appts-more" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: patient name. */ __( 'More actions for %s', 'doctor-ak-portal' ), $row['patient_name'] ) ); ?>"><?php esc_html_e( 'More', 'doctor-ak-portal' ); ?></summary>
											<div class="dak-row-menu-panel" role="menu">
												<?php // Always here — Reschedule only changes date/time; anything broader is a full edit. ?>
												<button type="button" class="dak-row-menu-item" role="menuitem"<?php echo $dak_edit_attrs( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value escaped with esc_attr() inside $dak_edit_attrs. ?>><?php esc_html_e( 'Edit appointment', 'doctor-ak-portal' ); ?></button>
												<?php if ( 'join_call' !== $dak_workflow && ! empty( $row['video_call']['can_join'] ) ) : ?>
													<button type="button" class="dak-row-menu-item" role="menuitem" data-join-video-call data-room-url="<?php echo esc_url( $row['video_call']['room_url'] ); ?>"><?php esc_html_e( 'Join video call', 'doctor-ak-portal' ); ?></button>
												<?php endif; ?>
												<?php if ( $dak_can_collect ) : ?>
													<button
														type="button"
														class="dak-row-menu-item"
														role="menuitem"
														data-admin-appointment-pay-now
														data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"
														title="<?php esc_attr_e( 'Opens the online card/wallet checkout for this appointment.', 'doctor-ak-portal' ); ?>"
													><?php echo esc_html( sprintf( /* translators: %s: amount, e.g. "PKR 2,500". */ __( 'Collect online — %s', 'doctor-ak-portal' ), $dak_money( $row['charge'] ) ) ); ?></button>
													<button
														type="button"
														class="dak-row-menu-item"
														role="menuitem"
														data-admin-appointment-mark-paid
														data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"
														title="<?php esc_attr_e( 'Already collected in cash or another way? Record it as paid.', 'doctor-ak-portal' ); ?>"
													><?php esc_html_e( 'Record cash / manual payment', 'doctor-ak-portal' ); ?></button>
												<?php endif; ?>
												<?php if ( ! $is_receptionist && 'requested' === $row['refund_status'] ) : ?>
													<button
														type="button"
														class="dak-row-menu-item"
														role="menuitem"
														data-admin-process-refund
														data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"
														data-patient-name="<?php echo esc_attr( $row['patient_name'] ); ?>"
														data-reason="<?php echo esc_attr( $row['refund_reason'] ); ?>"
														data-charge="<?php echo esc_attr( $row['charge'] ); ?>"
														data-refund-amount="<?php echo esc_attr( $row['refund_amount'] ); ?>"
													><?php esc_html_e( 'Process refund', 'doctor-ak-portal' ); ?></button>
												<?php endif; ?>
												<a class="dak-row-menu-item" role="menuitem" href="<?php echo esc_url( \DoctorAKPortal\Frontend\Appointment_Handler::print_url( $row['id'] ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Print slip', 'doctor-ak-portal' ); ?></a>
												<hr class="dak-row-menu-sep">
												<button
													type="button"
													class="dak-row-menu-item is-danger"
													role="menuitem"
													data-admin-appointment-delete
													data-appointment-id="<?php echo esc_attr( $row['id'] ); ?>"
												><?php esc_html_e( 'Delete appointment', 'doctor-ak-portal' ); ?></button>
											</div>
										</details>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				<?php endforeach; ?>
			</table>
		</div>
	<?php endif; ?>
</section>

</div>
