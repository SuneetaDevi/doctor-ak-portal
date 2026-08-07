<?php
/**
 * Template: Full-screen Add/Edit Session form — replaces the Doctor
 * Sessions table's content area (see Admin_Dashboard::session_form_screen_html())
 * when the URL has `?view=form`. Same fields the old modal had (clinic
 * type/name/address/location/contact, plus the weekly Morning/Afternoon/
 * Evening sessions grid), submitted to the same
 * Clinic_Handler::handle_admin_save_clinic() endpoint, just rendered as an
 * in-page screen instead of a popup so there's more room to work and the
 * whole thing is a normal, linkable/back-button-friendly page state.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array      $session_days     Day slug => label, see Clinics::session_days().
 * @var array      $session_periods  Period slug => label, see Clinics::session_periods().
 * @var array      $doctor_options   Doctor user ID => display name.
 * @var array      $clinic_locations Master clinic list, see Clinic_Locations::get_all().
 * @var string     $list_url         Back-to-list URL (the Doctor Sessions table).
 * @var array|null $editing_clinic   Decoded clinic row (see Clinics::find()) when editing, null when adding.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_is_editing = null !== $editing_clinic;
$dak_type       = $dak_is_editing ? $editing_clinic['type'] : 'physical';
$dak_sessions   = $dak_is_editing ? $editing_clinic['sessions'] : \DoctorAKPortal\Includes\Clinics::empty_sessions();
?>
<div class="dak-dashboard-greeting dak-admin-users-header">
	<div>
		<a class="dak-back-link" href="<?php echo esc_url( $list_url ); ?>">
			&larr; <?php esc_html_e( 'Back to Doctor Sessions', 'doctor-ak-portal' ); ?>
		</a>
		<h1><?php echo esc_html( $dak_is_editing ? __( 'Edit Session', 'doctor-ak-portal' ) : __( 'Add Session', 'doctor-ak-portal' ) ); ?></h1>
	</div>
</div>

<div class="dak-alert dak-alert-error dak-hidden" id="dak-admin-session-general-error" role="alert"></div>

<section class="dak-dashboard-card dak-admin-user-form-card">
	<form id="dak-admin-session-form" novalidate data-list-url="<?php echo esc_url( $list_url ); ?>">
		<input type="hidden" name="clinic_id" id="dak-admin-session-clinic-id" value="<?php echo esc_attr( $dak_is_editing ? $editing_clinic['id'] : 0 ); ?>">

		<div class="dak-field">
			<label for="dak-admin-session-doctor"><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-session-doctor" name="doctor_id" required>
				<option value=""><?php esc_html_e( 'Select a doctor…', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $doctor_options as $doctor_id => $doctor_option ) : ?>
					<option
						value="<?php echo esc_attr( $doctor_id ); ?>"
						<?php selected( $dak_is_editing && (int) $editing_clinic['doctor_id'] === (int) $doctor_id ); ?>
						<?php disabled( $doctor_option['is_disabled'] ); ?>
					><?php echo esc_html( $doctor_option['is_disabled'] ? sprintf( __( '%s (deactivated)', 'doctor-ak-portal' ), $doctor_option['name'] ) : $doctor_option['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="dak-field-error" data-field="doctor_id"></span>
		</div>

		<div class="dak-field-row">
			<div class="dak-field">
				<label for="dak-admin-session-type"><?php esc_html_e( 'Clinic Type', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-session-type" name="type">
					<option value="physical" <?php selected( 'video' !== $dak_type ); ?>><?php esc_html_e( 'Physical Clinic', 'doctor-ak-portal' ); ?></option>
					<option value="video" <?php selected( 'video' === $dak_type ); ?>><?php esc_html_e( 'Video Consultation', 'doctor-ak-portal' ); ?></option>
				</select>
			</div>
			<div class="dak-field dak-admin-session-video-name-field<?php echo 'video' !== $dak_type ? ' dak-hidden' : ''; ?>">
				<label for="dak-admin-session-name"><?php esc_html_e( 'Clinic Name', 'doctor-ak-portal' ); ?></label>
				<input type="text" id="dak-admin-session-name" name="name" value="<?php echo esc_attr( $dak_is_editing ? $editing_clinic['name'] : '' ); ?>">
				<span class="dak-field-error" data-field="name"></span>
			</div>
		</div>

		<div class="dak-field-row dak-admin-session-address-field<?php echo $dak_is_editing && 'video' === $dak_type ? ' dak-hidden' : ''; ?>">
			<div class="dak-field">
				<label for="dak-admin-session-country"><?php esc_html_e( 'Country', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-session-country" data-current="<?php echo esc_attr( $dak_is_editing ? $editing_clinic['country'] : '' ); ?>"></select>
				<span class="dak-field-error" data-field="country"></span>
			</div>
			<div class="dak-field">
				<label for="dak-admin-session-city"><?php esc_html_e( 'City', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-session-city" data-current="<?php echo esc_attr( $dak_is_editing ? $editing_clinic['city'] : '' ); ?>"></select>
				<span class="dak-field-error" data-field="city"></span>
			</div>
			<div class="dak-field">
				<label for="dak-admin-session-area"><?php esc_html_e( 'Area', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-session-area" data-current="<?php echo esc_attr( $dak_is_editing ? $editing_clinic['area'] : '' ); ?>"></select>
				<span class="dak-field-error" data-field="area"></span>
			</div>
		</div>

		<div class="dak-field dak-admin-session-address-field<?php echo $dak_is_editing && 'video' === $dak_type ? ' dak-hidden' : ''; ?>">
			<label for="dak-admin-session-clinic-location"><?php esc_html_e( 'Clinic', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-session-clinic-location" name="clinic_location_id" data-current="<?php echo esc_attr( $dak_is_editing ? $editing_clinic['clinic_location_id'] : '' ); ?>"></select>
			<span class="dak-field-error" data-field="clinic_location_id"></span>
			<p class="dak-field-hint"><?php esc_html_e( 'No clinic for this area yet? Add it first from the admin "Clinic" section, then come back here to align this doctor to it.', 'doctor-ak-portal' ); ?></p>
		</div>

		<div class="dak-field-row">
			<div class="dak-field">
				<label for="dak-admin-session-phone"><?php esc_html_e( 'Phone (optional)', 'doctor-ak-portal' ); ?></label>
				<input type="tel" id="dak-admin-session-phone" name="phone" value="<?php echo esc_attr( $dak_is_editing ? $editing_clinic['phone'] : '' ); ?>">
			</div>
			<div class="dak-field">
				<label for="dak-admin-session-email"><?php esc_html_e( 'Contact Email (optional)', 'doctor-ak-portal' ); ?></label>
				<input type="email" id="dak-admin-session-email" name="contact_email" value="<?php echo esc_attr( $dak_is_editing ? $editing_clinic['contact_email'] : '' ); ?>">
			</div>
		</div>

		<div class="dak-field">
			<span class="dak-field-label"><?php esc_html_e( 'Weekly Sessions', 'doctor-ak-portal' ); ?></span>
			<p class="dak-field-hint"><?php esc_html_e( 'Split a day into Morning/Afternoon/Evening sessions to give the doctor a break in between — leave a period unchecked to keep it closed.', 'doctor-ak-portal' ); ?></p>
			<div class="dak-clinic-sessions-days" id="dak-admin-session-grid">
				<?php foreach ( $session_days as $day_slug => $day_label ) : ?>
					<?php $day_periods = isset( $dak_sessions[ $day_slug ] ) ? $dak_sessions[ $day_slug ] : array(); ?>
					<div class="dak-clinic-sessions-day" data-day="<?php echo esc_attr( $day_slug ); ?>">
						<span class="dak-clinic-sessions-day-label"><?php echo esc_html( $day_label ); ?></span>
						<div class="dak-clinic-sessions-periods">
							<?php foreach ( $session_periods as $period_slug => $period_label ) : ?>
								<?php $period = isset( $day_periods[ $period_slug ] ) ? $day_periods[ $period_slug ] : array( 'enabled' => false, 'start' => '', 'end' => '', 'slot_duration_minutes' => '' ); ?>
								<div class="dak-availability-row" data-period="<?php echo esc_attr( $period_slug ); ?>">
									<label class="dak-checkbox">
										<input type="checkbox" class="dak-availability-toggle" <?php checked( ! empty( $period['enabled'] ) ); ?>>
										<span><?php echo esc_html( $period_label ); ?></span>
									</label>
									<input type="time" class="dak-availability-start" aria-label="<?php echo esc_attr( sprintf( __( '%s start time', 'doctor-ak-portal' ), $period_label ) ); ?>" value="<?php echo esc_attr( $period['start'] ); ?>" <?php disabled( empty( $period['enabled'] ) ); ?>>
									<span class="dak-availability-sep">&ndash;</span>
									<input type="time" class="dak-availability-end" aria-label="<?php echo esc_attr( sprintf( __( '%s end time', 'doctor-ak-portal' ), $period_label ) ); ?>" value="<?php echo esc_attr( $period['end'] ); ?>" <?php disabled( empty( $period['enabled'] ) ); ?>>
									<input type="number" min="5" max="240" class="dak-clinic-slot-duration" aria-label="<?php echo esc_attr( sprintf( __( '%s slot duration in minutes', 'doctor-ak-portal' ), $period_label ) ); ?>" placeholder="<?php esc_attr_e( 'min', 'doctor-ak-portal' ); ?>" value="<?php echo esc_attr( '' !== $period['slot_duration_minutes'] ? $period['slot_duration_minutes'] : '' ); ?>" <?php disabled( empty( $period['enabled'] ) ); ?>>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<span class="dak-field-error" data-field="sessions"></span>
		</div>

		<div class="dak-admin-user-form-actions">
			<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Cancel', 'doctor-ak-portal' ); ?></a>
			<button type="submit" class="dak-button dak-button-primary" id="dak-admin-session-submit">
				<span class="dak-button-label"><?php esc_html_e( 'Save Changes', 'doctor-ak-portal' ); ?></span>
			</button>
		</div>
	</form>
</section>
