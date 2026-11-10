<?php
/**
 * Partial: floating "Join call" card for the patient portal — shown on
 * every tab while one of the patient's paid video consultations is inside
 * its join window (Appointments::VIDEO_JOIN_WINDOW_* around the start
 * time). Rendered hidden; doctor-ak-call-dock.js shows it when a window
 * opens (also on a page left open since before then), hides it when the
 * window ends, and lets the patient shrink it to a small "Join call"
 * button. The Join button uses the same [data-join-video-call] handler as
 * every other Join button (doctor-ak-video-call.js — opens a new tab).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $video_calls     Appointments::upcoming_video_calls_for_patient().
 * @var bool  $has_bottom_nav  Whether the mobile bottom navigation is on this page.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $video_calls ) ) {
	return;
}

$dak_dock_calls = array_map(
	function ( $call ) {
		return array(
			'id'        => (int) $call['id'],
			/* translators: %s: doctor's name. */
			'doctor'    => sprintf( __( 'Dr. %s', 'doctor-ak-portal' ), $call['doctor_name'] ),
			'time'      => $call['time_label'],
			'roomUrl'   => $call['room_url'],
			'opensIn'   => (int) $call['opens_in'],
			'closesIn'  => (int) $call['closes_in'],
			'startsIn'  => (int) $call['starts_in'],
		);
	},
	$video_calls
);

$dak_video_icon = '<svg viewBox="0 0 20 20" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="2.5" y="5" width="10" height="10" rx="1.5"/><path d="M17.5 7.5 12.5 10l5 2.5z"/></svg>';
?>
<div
	class="dak-call-dock<?php echo ! empty( $has_bottom_nav ) ? ' has-bottom-nav' : ''; ?>"
	data-call-dock
	data-calls="<?php echo esc_attr( wp_json_encode( $dak_dock_calls ) ); ?>"
	hidden
>
	<section class="dak-call-dock-card" role="region" aria-label="<?php esc_attr_e( 'Your video consultation', 'doctor-ak-portal' ); ?>" data-call-dock-card>
		<span class="dak-call-dock-icon"><?php echo $dak_video_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
		<div class="dak-call-dock-text">
			<strong data-call-dock-status><?php esc_html_e( 'Your video consultation is ready', 'doctor-ak-portal' ); ?></strong>
			<span data-call-dock-detail></span>
		</div>
		<button type="button" class="dak-button dak-button-primary dak-button-sm dak-call-dock-join" data-join-video-call data-room-url="" data-call-dock-join>
			<?php esc_html_e( 'Join call', 'doctor-ak-portal' ); ?>
		</button>
		<button type="button" class="dak-call-dock-min" data-call-dock-minimize aria-label="<?php esc_attr_e( 'Make smaller', 'doctor-ak-portal' ); ?>" title="<?php esc_attr_e( 'Make smaller', 'doctor-ak-portal' ); ?>">
			<svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M5 10h10"/></svg>
		</button>
	</section>

	<button type="button" class="dak-call-dock-pill" data-call-dock-expand hidden aria-label="<?php esc_attr_e( 'Show your video consultation', 'doctor-ak-portal' ); ?>">
		<span class="dak-call-dock-pulse" aria-hidden="true"></span>
		<?php echo $dak_video_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
		<span><?php esc_html_e( 'Join call', 'doctor-ak-portal' ); ?></span>
	</button>
</div>
