<?php
/**
 * Template: Doctor dashboard "Clinics" tab — manage clinics (physical
 * locations or a video-consultation entry) and each one's weekly sessions
 * (which days it's open, hours, and appointment slot duration).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $clinics      Doctor's clinics, see Clinics::get_for_doctor().
 * @var array $session_days Day slug => label, see Clinics::session_days().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_clinic_icons = array(
	'pin'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'video'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="11" height="10" rx="1.5"/><path d="M13 8.3l5-2.8v9l-5-2.8"/></svg>',
	'edit'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 3.5a1.7 1.7 0 0 1 2.4 2.4L6.5 15.3l-3 .7.7-3 9.3-9.3z"/></svg>',
	'delete' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h12M8 6V4.5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1V6M6 6l.6 9a1.5 1.5 0 0 0 1.5 1.4h3.8a1.5 1.5 0 0 0 1.5-1.4L14 6"/></svg>',
);

/**
 * Renders one clinic card's markup (summary + edit form). $clinic may be
 * null to render the blank <template> used by JS when adding a new clinic.
 *
 * @param array|null $clinic       Decoded clinic row, or null for the blank template.
 * @param array      $session_days Day slug => label.
 * @param array      $icons        Icon slug => inline SVG markup.
 * @return void
 */
if ( ! function_exists( 'dak_render_clinic_card' ) ) :
function dak_render_clinic_card( $clinic, array $session_days, array $icons ) {
	$is_blank = null === $clinic;
	$type     = $is_blank ? 'physical' : $clinic['type'];
	$sessions = $is_blank ? \DoctorAKPortal\Includes\Clinics::empty_sessions() : $clinic['sessions'];
	?>
	<div class="dak-clinic-card" data-clinic-id="<?php echo esc_attr( $is_blank ? '0' : $clinic['id'] ); ?>">
		<div class="dak-clinic-card-summary<?php echo $is_blank ? ' dak-hidden' : ''; ?>">
			<span class="dak-clinic-card-icon" aria-hidden="true"><?php echo $icons[ 'video' === $type ? 'video' : 'pin' ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<div class="dak-clinic-card-info">
				<strong class="dak-clinic-card-name" data-clinic-name-display><?php echo esc_html( $is_blank ? '' : $clinic['name'] ); ?></strong>
				<span class="dak-clinic-card-meta" data-clinic-meta-display>
					<?php
					if ( ! $is_blank ) {
						echo esc_html( 'video' === $clinic['type'] ? __( 'Video Consultation', 'doctor-ak-portal' ) : $clinic['address'] );
					}
					?>
				</span>
				<div class="dak-specialty-tags dak-clinic-card-days" data-clinic-days-display>
					<?php if ( ! $is_blank ) : ?>
						<?php foreach ( $clinic['enabled_days'] as $label ) : ?>
							<span class="dak-specialty-tag"><?php echo esc_html( $label ); ?></span>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
			<div class="dak-clinic-card-actions">
				<button type="button" class="dak-icon-button" data-clinic-edit-toggle title="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Edit', 'doctor-ak-portal' ); ?>"><?php echo $icons['edit']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
				<button type="button" class="dak-icon-button dak-icon-button-danger" data-clinic-delete title="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Delete', 'doctor-ak-portal' ); ?>"><?php echo $icons['delete']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></button>
			</div>
		</div>

		<div class="dak-clinic-card-form<?php echo $is_blank ? '' : ' dak-hidden'; ?>">
			<div class="dak-alert dak-alert-error dak-hidden" data-clinic-form-error role="alert"></div>

			<div class="dak-field-row">
				<div class="dak-field">
					<label><?php esc_html_e( 'Clinic Type', 'doctor-ak-portal' ); ?></label>
					<select class="dak-clinic-type-select">
						<option value="physical" <?php selected( 'video' !== $type ); ?>><?php esc_html_e( 'Physical Clinic', 'doctor-ak-portal' ); ?></option>
						<option value="video" <?php selected( 'video' === $type ); ?>><?php esc_html_e( 'Video Consultation', 'doctor-ak-portal' ); ?></option>
					</select>
				</div>
				<div class="dak-field">
					<label><?php esc_html_e( 'Clinic Name', 'doctor-ak-portal' ); ?></label>
					<input type="text" class="dak-clinic-name-input" value="<?php echo esc_attr( $is_blank ? '' : $clinic['name'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. AK-Lohana Clinic', 'doctor-ak-portal' ); ?>">
					<span class="dak-field-error" data-field="name"></span>
				</div>
			</div>

			<div class="dak-field dak-clinic-address-field<?php echo ! $is_blank && 'video' === $clinic['type'] ? ' dak-hidden' : ''; ?>">
				<label><?php esc_html_e( 'Address', 'doctor-ak-portal' ); ?></label>
				<input type="text" class="dak-clinic-address-input" value="<?php echo esc_attr( $is_blank ? '' : $clinic['address'] ); ?>">
				<span class="dak-field-error" data-field="address"></span>
			</div>

			<div class="dak-field-row">
				<div class="dak-field">
					<label><?php esc_html_e( 'Phone (optional)', 'doctor-ak-portal' ); ?></label>
					<input type="tel" class="dak-clinic-phone-input" value="<?php echo esc_attr( $is_blank ? '' : $clinic['phone'] ); ?>">
					<span class="dak-field-error" data-field="phone"></span>
				</div>
				<div class="dak-field">
					<label><?php esc_html_e( 'Contact Email (optional)', 'doctor-ak-portal' ); ?></label>
					<input type="email" class="dak-clinic-email-input" value="<?php echo esc_attr( $is_blank ? '' : $clinic['contact_email'] ); ?>">
					<span class="dak-field-error" data-field="contact_email"></span>
				</div>
			</div>

			<div class="dak-field">
				<span class="dak-field-label"><?php esc_html_e( 'Weekly Sessions', 'doctor-ak-portal' ); ?></span>
				<div class="dak-availability-grid dak-clinic-sessions-grid">
					<div class="dak-availability-row dak-availability-row-header">
						<span></span>
						<span class="dak-availability-col-label"><?php esc_html_e( 'Start', 'doctor-ak-portal' ); ?></span>
						<span></span>
						<span class="dak-availability-col-label"><?php esc_html_e( 'End', 'doctor-ak-portal' ); ?></span>
						<span class="dak-availability-col-label"><?php esc_html_e( 'Slot (min)', 'doctor-ak-portal' ); ?></span>
					</div>
					<?php foreach ( $session_days as $slug => $label ) : ?>
						<?php $day = isset( $sessions[ $slug ] ) ? $sessions[ $slug ] : array( 'enabled' => false, 'start' => '', 'end' => '', 'slot_duration_minutes' => '' ); ?>
						<div class="dak-availability-row" data-day="<?php echo esc_attr( $slug ); ?>">
							<label class="dak-checkbox">
								<input type="checkbox" class="dak-availability-toggle" <?php checked( ! empty( $day['enabled'] ) ); ?>>
								<span><?php echo esc_html( $label ); ?></span>
							</label>
							<input type="time" class="dak-availability-start" aria-label="<?php esc_attr_e( 'Start time', 'doctor-ak-portal' ); ?>" value="<?php echo esc_attr( $day['start'] ); ?>" <?php disabled( empty( $day['enabled'] ) ); ?>>
							<span class="dak-availability-sep">&ndash;</span>
							<input type="time" class="dak-availability-end" aria-label="<?php esc_attr_e( 'End time', 'doctor-ak-portal' ); ?>" value="<?php echo esc_attr( $day['end'] ); ?>" <?php disabled( empty( $day['enabled'] ) ); ?>>
							<input type="number" min="5" max="240" class="dak-clinic-slot-duration" aria-label="<?php esc_attr_e( 'Slot duration in minutes', 'doctor-ak-portal' ); ?>" placeholder="<?php esc_attr_e( 'min', 'doctor-ak-portal' ); ?>" value="<?php echo esc_attr( '' !== $day['slot_duration_minutes'] ? $day['slot_duration_minutes'] : '' ); ?>" <?php disabled( empty( $day['enabled'] ) ); ?>>
						</div>
					<?php endforeach; ?>
				</div>
				<span class="dak-field-error" data-field="sessions"></span>
			</div>

			<div class="dak-clinic-card-form-actions">
				<button type="button" class="dak-button dak-button-primary" data-clinic-save>
					<span class="dak-button-label"><?php esc_html_e( 'Save Clinic', 'doctor-ak-portal' ); ?></span>
				</button>
				<button type="button" class="dak-button dak-button-secondary" data-clinic-cancel><?php esc_html_e( 'Cancel', 'doctor-ak-portal' ); ?></button>
			</div>
		</div>
	</div>
	<?php
}
endif;
?>
<div class="dak-alert dak-alert-success dak-hidden" id="dak-clinics-success" role="status"></div>
<div class="dak-alert dak-alert-error dak-hidden" id="dak-clinics-general-error" role="alert"></div>

<div class="dak-clinics-list" id="dak-clinics-list">
	<?php if ( empty( $clinics ) ) : ?>
		<p class="dak-empty-state" id="dak-clinics-empty-state"><?php esc_html_e( "You haven't added any clinics yet. Add one below.", 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>

	<?php foreach ( $clinics as $clinic ) : ?>
		<?php dak_render_clinic_card( $clinic, $session_days, $dak_clinic_icons ); ?>
	<?php endforeach; ?>
</div>

<button type="button" class="dak-button dak-button-secondary" id="dak-clinic-add">
	<?php esc_html_e( '+ Add Clinic', 'doctor-ak-portal' ); ?>
</button>

<template id="dak-clinic-card-template">
	<?php dak_render_clinic_card( null, $session_days, $dak_clinic_icons ); ?>
</template>
