<?php
/**
 * Template: A single upcoming-appointment row on the doctor dashboard —
 * patient avatar/name, a countdown badge, status/payment pills, a Join Call
 * action for video appointments, and a Mark Paid action for the doctor's
 * own pending-payment appointments (e.g. cash collected at the clinic).
 * Mirrors patient-appointment-row.php but shows the patient (not the
 * doctor) and has no Cancel action — that belongs to the patient (a doctor
 * cancels from the full Appointments tab instead, see
 * doctor-appointments-list.php).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $appointment Row from Appointments::doctor_dashboard_data()['groups'][...].
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'dak_doctor_appt_initials' ) ) :
	/**
	 * One or two uppercase initials from a name, for the avatar fallback.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	function dak_doctor_appt_initials( $name ) {
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

$dak_row_ts      = strtotime( $appointment['date'] . ' ' . $appointment['time'] );
$dak_row_unpaid  = ! $appointment['is_paid'] && (float) $appointment['charge'] > 0;
$dak_row_can_go  = in_array( $appointment['status'], array( 'confirmed', 'paid', 'rescheduled' ), true ) && ( $appointment['is_paid'] || (float) $appointment['charge'] <= 0 );
$dak_row_enc_url = '';

if ( 'checked_in' === $appointment['status'] ) {
	$dak_open_encounter = \DoctorAKPortal\Includes\Encounters::find_by_appointment( $appointment['id'], \DoctorAKPortal\Includes\Encounters::STATUS_OPEN );
	$dak_row_enc_url    = $dak_open_encounter ? add_query_arg( array( 'tab' => 'encounter', 'encounter_id' => $dak_open_encounter['id'], 'from' => 'dashboard' ), \DoctorAKPortal\Includes\Page_Finder::url_for_shortcode( \DoctorAKPortal\Frontend\Doctor_Dashboard::SHORTCODE_TAG ) ) : '';
}

$dak_row_workflow = ! empty( $appointment['video_call']['can_join'] ) ? 'join' : ( $dak_row_can_go ? 'check_in' : ( '' !== $dak_row_enc_url ? 'encounter' : '' ) );
$dak_row_pay      = $appointment['is_paid']
	? array( __( 'Paid', 'doctor-ak-portal' ), 'dak-status-pill-is-active' )
	: ( \DoctorAKPortal\Includes\Appointments::PAYMENT_STATUS_PENDING === $appointment['payment_status'] ? array( __( 'Pending', 'doctor-ak-portal' ), 'dak-status-pill-is-pending' ) : array( __( 'Not recorded', 'doctor-ak-portal' ), 'dak-status-pill-is-neutral' ) );
$dak_row_has_more = $dak_row_unpaid || $dak_row_can_go || ! empty( $appointment['reschedulable'] ) || ( 'join' === $dak_row_workflow && ( $dak_row_can_go || '' !== $dak_row_enc_url ) );
$dak_row_contact  = array_filter( array( $appointment['is_guest'] ? __( 'Guest booking', 'doctor-ak-portal' ) : '', $appointment['patient_phone'], '' !== (string) $appointment['patient_age'] ? sprintf( /* translators: %d: patient's age in years. */ __( '%d yrs', 'doctor-ak-portal' ), $appointment['patient_age'] ) : '' ) );
?>
<tr data-row data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>">
	<td class="dak-col-primary" data-label="<?php esc_attr_e( 'Patient', 'doctor-ak-portal' ); ?>">
		<span class="dak-cell-stack">
			<span class="dak-cell-primary"><?php echo esc_html( $appointment['patient_name'] ); ?></span>
			<?php if ( ! empty( $dak_row_contact ) ) : ?>
				<span class="dak-cell-sub is-tabular"><?php echo esc_html( implode( ' · ', $dak_row_contact ) ); ?></span>
			<?php endif; ?>
		</span>
	</td>
	<td class="dak-col-nowrap" data-label="<?php esc_attr_e( 'Time', 'doctor-ak-portal' ); ?>">
		<span class="dak-cell-stack">
			<span class="dak-cell-strong is-tabular"><?php echo esc_html( false !== $dak_row_ts ? date_i18n( 'h:i A', $dak_row_ts ) : $appointment['time'] ); ?></span>
			<span class="dak-cell-sub"><?php echo esc_html( false !== $dak_row_ts ? date_i18n( 'd M Y', $dak_row_ts ) : $appointment['date'] ); ?> · <?php echo esc_html( $appointment['countdown_label'] ); ?></span>
		</span>
	</td>
	<td data-label="<?php esc_attr_e( 'Visit', 'doctor-ak-portal' ); ?>">
		<span class="dak-cell-stack">
			<span><?php echo esc_html( '' !== $appointment['service_name'] ? $appointment['service_name'] : $appointment['type_label'] ); ?></span>
			<?php if ( '' !== $appointment['service_name'] ) : ?>
				<span class="dak-cell-sub"><?php echo esc_html( $appointment['type_label'] ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $appointment['video_call']['applicable'] ) && ! $appointment['video_call']['can_join'] && '' !== $appointment['video_call']['hint'] ) : ?>
				<span class="dak-cell-note"><?php echo esc_html( $appointment['video_call']['hint'] ); ?></span>
			<?php endif; ?>
		</span>
	</td>
	<td data-label="<?php esc_attr_e( 'Status', 'doctor-ak-portal' ); ?>">
		<span class="dak-cell-stack">
			<span class="dak-status-pill <?php echo esc_attr( \DoctorAKPortal\Includes\Dashboard_Format::status_class( $appointment['status'] ) ); ?>"><?php echo esc_html( $appointment['status_label'] ); ?></span>
			<span class="dak-pay-status <?php echo esc_attr( $dak_row_pay[1] ); ?>"><?php echo esc_html( $dak_row_pay[0] . ' · ' . \DoctorAKPortal\Includes\Dashboard_Format::money( $appointment['charge'], __( 'Free', 'doctor-ak-portal' ) ) ); ?></span>
		</span>
	</td>
	<td class="dak-col-actions" data-label="<?php esc_attr_e( 'Actions', 'doctor-ak-portal' ); ?>">
		<div class="dak-row-actions">
			<?php if ( 'join' === $dak_row_workflow ) : ?>
				<button type="button" class="dak-button dak-button-primary dak-button-sm" data-join-video-call data-room-url="<?php echo esc_url( $appointment['video_call']['room_url'] ); ?>" title="<?php esc_attr_e( "You'll be asked to log in with a free account (Google, GitHub, etc.) to start the call — your patient won't need to.", 'doctor-ak-portal' ); ?>"><?php esc_html_e( 'Start call', 'doctor-ak-portal' ); ?></button>
			<?php elseif ( 'check_in' === $dak_row_workflow ) : ?>
				<button type="button" class="dak-button dak-button-primary dak-button-sm" data-check-in data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>" data-return-query="from=dashboard" title="<?php esc_attr_e( 'Check the patient in and open their encounter', 'doctor-ak-portal' ); ?>"><?php esc_html_e( 'Check in', 'doctor-ak-portal' ); ?></button>
			<?php elseif ( 'encounter' === $dak_row_workflow ) : ?>
				<a class="dak-button dak-button-primary dak-button-sm" href="<?php echo esc_url( $dak_row_enc_url ); ?>"><?php esc_html_e( 'Open encounter', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
			<?php
			$dak_report_count = isset( $appointment['report_count'] ) ? (int) $appointment['report_count'] : 0;

			// Reports shared before the consultation — open while the
			// appointment is active, view-only afterwards if any were shared.
			if ( 'cancelled' !== $appointment['status'] || $dak_report_count > 0 ) :
				?>
				<button type="button" class="dak-button dak-button-secondary dak-button-sm dak-reports-button" data-appointment-reports data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>" data-appointment-label="<?php echo esc_attr( $appointment['patient_name'] . ( $dak_row_ts ? ' · ' . date_i18n( 'd M Y, h:i A', $dak_row_ts ) : '' ) ); ?>">
					<?php esc_html_e( 'Reports', 'doctor-ak-portal' ); ?>
					<span class="dak-reports-count" data-appointment-reports-count="<?php echo esc_attr( $appointment['id'] ); ?>"<?php echo $dak_report_count > 0 ? '' : ' hidden'; ?>><?php echo $dak_report_count > 0 ? esc_html( $dak_report_count ) : ''; ?></span>
				</button>
			<?php endif; ?>
			<?php if ( $dak_row_has_more ) : ?>
				<details class="dak-row-menu">
					<summary class="dak-row-menu-toggle" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: patient name. */ __( 'More actions for %s', 'doctor-ak-portal' ), $appointment['patient_name'] ) ); ?>"><?php esc_html_e( 'More', 'doctor-ak-portal' ); ?></summary>
					<div class="dak-row-menu-panel" role="menu">
						<?php if ( 'join' === $dak_row_workflow && $dak_row_can_go ) : ?>
							<button type="button" class="dak-row-menu-item" role="menuitem" data-check-in data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>" data-return-query="from=dashboard"><?php esc_html_e( 'Check in', 'doctor-ak-portal' ); ?></button>
						<?php elseif ( 'join' === $dak_row_workflow && '' !== $dak_row_enc_url ) : ?>
							<a class="dak-row-menu-item" role="menuitem" href="<?php echo esc_url( $dak_row_enc_url ); ?>"><?php esc_html_e( 'Open encounter', 'doctor-ak-portal' ); ?></a>
						<?php endif; ?>
						<?php if ( $dak_row_unpaid && 'online' === $appointment['payment_mode'] ) : ?>
							<button type="button" class="dak-row-menu-item" role="menuitem" data-doctor-pay-now data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>"><?php echo esc_html( sprintf( /* translators: %s: amount. */ __( 'Collect online — %s', 'doctor-ak-portal' ), \DoctorAKPortal\Includes\Dashboard_Format::money( $appointment['charge'] ) ) ); ?></button>
						<?php elseif ( $dak_row_unpaid ) : ?>
							<button type="button" class="dak-row-menu-item" role="menuitem" data-doctor-mark-paid data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>"><?php echo esc_html( sprintf( /* translators: %s: amount. */ __( 'Record payment — %s', 'doctor-ak-portal' ), \DoctorAKPortal\Includes\Dashboard_Format::money( $appointment['charge'] ) ) ); ?></button>
						<?php endif; ?>
						<?php if ( $dak_row_can_go ) : ?>
							<button type="button" class="dak-row-menu-item" role="menuitem" data-mark-completed data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>"><?php esc_html_e( 'Mark completed', 'doctor-ak-portal' ); ?></button>
						<?php endif; ?>
						<?php if ( ! empty( $appointment['reschedulable'] ) ) : ?>
							<button type="button" class="dak-row-menu-item" role="menuitem" data-reschedule-appointment data-appointment-id="<?php echo esc_attr( $appointment['id'] ); ?>" data-date="<?php echo esc_attr( $appointment['date'] ); ?>" data-time="<?php echo esc_attr( $appointment['time'] ); ?>"><?php esc_html_e( 'Reschedule', 'doctor-ak-portal' ); ?></button>
						<?php endif; ?>
					</div>
				</details>
			<?php endif; ?>
		</div>
	</td>
</tr>