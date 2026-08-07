<?php
/**
 * Template: "Doctor Sessions" admin table — every doctor's every clinic
 * (physical location or video-consultation entry), its weekly enabled days
 * and appointment slot duration, flattened into one table.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $clinics         Rows from Clinics::all_flat_for_admin(), each with an added 'doctor' sub-array.
 * @var string $section_url     This section's own URL (?section=doctor-sessions or ?section=clinic), for building the Add/Edit links.
 * @var string $filtered_doctor Name of the doctor being filtered to (via the Doctors directory's "View Sessions" action), or '' if unfiltered.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_session_icons = array(
	'pin'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'video'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="11" height="10" rx="1.5"/><path d="M13 8.3l5-2.8v9l-5-2.8"/></svg>',
	'edit'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 3.5a1.7 1.7 0 0 1 2.4 2.4L6.5 15.3l-3 .7.7-3 9.3-9.3z"/></svg>',
	'delete' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h12M8 6V4.5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1V6M6 6l.6 9a1.5 1.5 0 0 0 1.5 1.4h3.8a1.5 1.5 0 0 0 1.5-1.4L14 6"/></svg>',
);
?>
<div class="dak-dashboard-greeting dak-admin-users-header">
	<div>
		<h1><?php esc_html_e( 'Doctor Sessions', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( "Every doctor's clinics and their weekly session hours, in one place.", 'doctor-ak-portal' ); ?></p>
	</div>
	<a class="dak-button dak-button-primary" href="<?php echo esc_url( add_query_arg( 'view', 'form', $section_url ) ); ?>"><?php esc_html_e( '+ Add Session', 'doctor-ak-portal' ); ?></a>
</div>

<?php if ( '' !== $filtered_doctor ) : ?>
	<div class="dak-alert dak-alert-success">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: doctor's name. */
				__( 'Showing sessions for Dr. %s.', 'doctor-ak-portal' ),
				$filtered_doctor
			)
		);
		?>
		<a class="dak-link" href="<?php echo esc_url( $section_url ); ?>"><?php esc_html_e( 'Clear filter', 'doctor-ak-portal' ); ?></a>
	</div>
<?php endif; ?>

<section class="dak-dashboard-card">
	<div class="dak-dashboard-card-header">
		<h2><?php esc_html_e( 'Session directory', 'doctor-ak-portal' ); ?></h2>
	</div>

	<?php if ( empty( $clinics ) ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'No doctors have added any clinics or sessions yet.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php foreach ( $clinics as $clinic ) : ?>
			<?php
			$slot_durations = array();

			foreach ( $clinic['sessions'] as $dak_session_day ) {
				foreach ( $dak_session_day as $dak_session_period ) {
					if ( ! empty( $dak_session_period['enabled'] ) ) {
						$slot_durations[] = $dak_session_period['slot_duration_minutes'];
					}
				}
			}

			$slot_label = ! empty( $slot_durations ) ? sprintf( /* translators: %d: minutes. */ __( '%d min', 'doctor-ak-portal' ), (int) reset( $slot_durations ) ) : '—';
			?>
			<div class="dak-admin-record-row" data-clinic-row="<?php echo esc_attr( $clinic['id'] ); ?>">
				<div class="dak-admin-record-row-main">
					<span class="dak-avatar dak-avatar-sm" aria-hidden="true"><?php echo $dak_session_icons[ 'video' === $clinic['type'] ? 'video' : 'pin' ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="dak-admin-record-row-info">
						<strong><?php echo esc_html( sprintf( 'Dr. %s', $clinic['doctor']['name'] ) ); ?></strong>
						<span class="dak-admin-record-row-id">
							<?php echo esc_html( 'video' === $clinic['type'] ? __( 'Online Consultation', 'doctor-ak-portal' ) : $clinic['name'] ); ?>
						</span>
					</span>

					<span class="dak-admin-record-row-meta"><?php echo esc_html( $slot_label ); ?></span>

					<span class="dak-status-pill dak-status-pill-outline <?php echo empty( $clinic['enabled_days'] ) ? 'dak-status-pill-is-disabled' : 'dak-status-pill-is-active'; ?>">
						<?php echo empty( $clinic['enabled_days'] ) ? esc_html__( 'Not available', 'doctor-ak-portal' ) : esc_html__( 'Available', 'doctor-ak-portal' ); ?>
					</span>

					<?php if ( ! empty( $clinic['enabled_days'] ) ) : ?>
						<span class="dak-admin-record-row-tags">
							<?php foreach ( $clinic['enabled_days'] as $label ) : ?>
								<span class="dak-status-pill dak-status-pill-outline"><?php echo esc_html( mb_substr( $label, 0, 3 ) ); ?></span>
							<?php endforeach; ?>
						</span>
					<?php endif; ?>

					<span class="dak-admin-record-row-actions">
						<a
							class="dak-icon-button"
							href="<?php echo esc_url( add_query_arg( array( 'view' => 'form', 'clinic_id' => $clinic['id'] ), $section_url ) ); ?>"
							title="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
						><?php echo $dak_session_icons['edit']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
						<button
							type="button"
							class="dak-icon-button dak-icon-button-danger"
							data-admin-session-delete
							data-clinic-id="<?php echo esc_attr( $clinic['id'] ); ?>"
							title="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
							aria-label="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
						><?php echo $dak_session_icons['delete']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
					</span>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
