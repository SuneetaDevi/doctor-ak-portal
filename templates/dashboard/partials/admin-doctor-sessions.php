<?php
/**
 * Template: "Doctor Sessions" admin table — every doctor's every clinic
 * (physical location or video-consultation entry), its weekly enabled days
 * and appointment slot duration, flattened into one table.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $clinics Rows from Clinics::all_flat_for_admin(), each with an added 'doctor' sub-array.
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
	<button type="button" class="dak-button dak-button-primary" id="dak-admin-session-add"><?php esc_html_e( '+ Add Session', 'doctor-ak-portal' ); ?></button>
</div>

<section class="dak-dashboard-card dak-admin-users-card">
<?php if ( empty( $clinics ) ) : ?>
	<p class="dak-empty-state"><?php esc_html_e( 'No doctors have added any clinics or sessions yet.', 'doctor-ak-portal' ); ?></p>
<?php else : ?>
	<div class="dak-table-scroll">
		<table class="dak-admin-users-table dak-admin-sessions-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Clinic', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Days', 'doctor-ak-portal' ); ?></th>
					<th><?php esc_html_e( 'Time Slot', 'doctor-ak-portal' ); ?></th>
					<th class="dak-admin-users-actions-col"><?php esc_html_e( 'Action', 'doctor-ak-portal' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $clinics as $clinic ) : ?>
					<?php
					$slot_durations = wp_list_pluck( array_filter( $clinic['sessions'], function ( $day ) { return ! empty( $day['enabled'] ); } ), 'slot_duration_minutes' );
					$slot_label     = ! empty( $slot_durations ) ? sprintf( /* translators: %d: minutes. */ __( '%d min', 'doctor-ak-portal' ), (int) reset( $slot_durations ) ) : '—';
					?>
					<tr data-clinic-row="<?php echo esc_attr( $clinic['id'] ); ?>">
						<td data-label="ID">#<?php echo esc_html( $clinic['id'] ); ?></td>
						<td data-label="<?php esc_attr_e( 'Doctor', 'doctor-ak-portal' ); ?>">
							<strong><?php echo esc_html( sprintf( 'Dr. %s', $clinic['doctor']['name'] ) ); ?></strong><br>
							<span class="dak-clinic-card-meta"><?php echo esc_html( $clinic['doctor']['email'] ); ?></span>
						</td>
						<td data-label="<?php esc_attr_e( 'Clinic', 'doctor-ak-portal' ); ?>">
							<span class="dak-clinic-card-icon" aria-hidden="true"><?php echo $dak_session_icons[ 'video' === $clinic['type'] ? 'video' : 'pin' ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php echo esc_html( 'video' === $clinic['type'] ? __( 'Online Consultation', 'doctor-ak-portal' ) : $clinic['name'] ); ?><br>
							<span class="dak-clinic-card-meta"><?php echo esc_html( $clinic['contact_email'] ); ?></span>
						</td>
						<td data-label="<?php esc_attr_e( 'Days', 'doctor-ak-portal' ); ?>">
							<?php foreach ( $clinic['enabled_days'] as $label ) : ?>
								<span class="dak-specialty-tag"><?php echo esc_html( mb_substr( $label, 0, 3 ) ); ?></span>
							<?php endforeach; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Time Slot', 'doctor-ak-portal' ); ?>"><?php echo esc_html( $slot_label ); ?></td>
						<td class="dak-admin-users-actions-col">
							<div class="dak-admin-users-actions">
								<button
									type="button"
									class="dak-icon-button"
									data-admin-session-edit
									data-clinic-id="<?php echo esc_attr( $clinic['id'] ); ?>"
									data-doctor-id="<?php echo esc_attr( $clinic['doctor_id'] ); ?>"
									data-type="<?php echo esc_attr( $clinic['type'] ); ?>"
									data-name="<?php echo esc_attr( $clinic['name'] ); ?>"
									data-address="<?php echo esc_attr( $clinic['address'] ); ?>"
									data-city="<?php echo esc_attr( $clinic['city'] ); ?>"
									data-area="<?php echo esc_attr( $clinic['area'] ); ?>"
									data-phone="<?php echo esc_attr( $clinic['phone'] ); ?>"
									data-contact-email="<?php echo esc_attr( $clinic['contact_email'] ); ?>"
									data-sessions="<?php echo esc_attr( wp_json_encode( $clinic['sessions'] ) ); ?>"
									title="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
									aria-label="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"
								><?php echo $dak_session_icons['edit']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
								<button
									type="button"
									class="dak-icon-button dak-icon-button-danger"
									data-admin-session-delete
									data-clinic-id="<?php echo esc_attr( $clinic['id'] ); ?>"
									title="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
									aria-label="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"
								><?php echo $dak_session_icons['delete']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
</section>
