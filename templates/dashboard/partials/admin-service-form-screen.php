<?php
/**
 * Template: Full-screen Add/Edit Service form — replaces the Services
 * table's content area (see Admin_Dashboard::service_form_screen_html())
 * when the URL has `?view=form`. Same fields the old modal had (Doctor(s)/
 * Name/Category/Charge/Duration/Active/Description/Image/Clinics + per-
 * clinic price), submitted to the same Service_Handler admin AJAX
 * endpoints, just rendered as an in-page screen instead of a popup — mirrors
 * the Add/Edit Doctor and Add/Edit Session screens' own pattern.
 *
 * The Doctor field is a multi-select: adding a new service with several
 * picked creates one row per doctor (see
 * Service_Handler::handle_admin_save_service()); editing an existing row
 * always targets just the one it already belongs to.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array      $doctor_options   Doctor user ID => { name, is_disabled }.
 * @var array      $categories       Category slug => label, see Specializations::get_all().
 * @var array      $clinic_locations Rows from Clinic_Locations::get_all().
 * @var string     $list_url         Back-to-list URL (the Services table).
 * @var array|null $editing_service  Decoded service row (see Services::find()) when editing, null when adding.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_is_editing = null !== $editing_service;
?>
<div class="dak-dashboard-greeting dak-admin-users-header">
	<div>
		<a class="dak-back-link" href="<?php echo esc_url( $list_url ); ?>">
			&larr; <?php esc_html_e( 'Back to Services', 'doctor-ak-portal' ); ?>
		</a>
		<h1><?php echo esc_html( $dak_is_editing ? __( 'Edit Service', 'doctor-ak-portal' ) : __( 'Add Service', 'doctor-ak-portal' ) ); ?></h1>
	</div>
</div>

<div class="dak-alert dak-alert-error dak-hidden" id="dak-admin-service-general-error" role="alert"></div>

<section class="dak-dashboard-card dak-admin-user-form-card">
	<form id="dak-admin-service-form" novalidate data-list-url="<?php echo esc_url( $list_url ); ?>">
		<input type="hidden" name="service_id" value="<?php echo esc_attr( $dak_is_editing ? $editing_service['id'] : 0 ); ?>">
		<input type="hidden" id="dak-admin-service-image-id" name="image_id" value="<?php echo esc_attr( $dak_is_editing ? $editing_service['image_id'] : 0 ); ?>">
		<input type="hidden" id="dak-admin-service-has-portfolio-fields" name="has_portfolio_fields" value="1">

		<div class="dak-field">
			<label for="dak-admin-service-doctor"><?php esc_html_e( 'Doctor(s)', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-service-doctor" class="dak-select-searchable" data-placeholder="<?php esc_attr_e( 'Select doctors…', 'doctor-ak-portal' ); ?>" multiple>
				<?php foreach ( $doctor_options as $doctor_id => $doctor_option ) : ?>
					<option
						value="<?php echo esc_attr( $doctor_id ); ?>"
						<?php selected( $dak_is_editing && (int) $editing_service['doctor_id'] === (int) $doctor_id ); ?>
						<?php disabled( $doctor_option['is_disabled'] ); ?>
					><?php echo esc_html( $doctor_option['is_disabled'] ? sprintf( __( '%s (deactivated)', 'doctor-ak-portal' ), $doctor_option['name'] ) : $doctor_option['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="dak-field-hint"><?php esc_html_e( 'Adding a new service: selecting several doctors creates a separate copy for each, editable individually afterward. Editing an existing service: only the first selected doctor applies.', 'doctor-ak-portal' ); ?></p>
			<span class="dak-field-error" data-field="doctor_id"></span>
		</div>

		<div class="dak-field">
			<label for="dak-admin-service-name"><?php esc_html_e( 'Service Name', 'doctor-ak-portal' ); ?></label>
			<input type="text" id="dak-admin-service-name" name="name" placeholder="<?php esc_attr_e( 'e.g. OPD Consultation', 'doctor-ak-portal' ); ?>" value="<?php echo esc_attr( $dak_is_editing ? $editing_service['name'] : '' ); ?>">
			<span class="dak-field-error" data-field="name"></span>
		</div>

		<div class="dak-field">
			<label for="dak-admin-service-category"><?php esc_html_e( 'Category', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-service-category" name="category">
				<option value=""><?php esc_html_e( 'None', 'doctor-ak-portal' ); ?></option>
				<?php foreach ( $categories as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $dak_is_editing && $editing_service['category'] === $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="dak-field-row">
			<div class="dak-field">
				<label for="dak-admin-service-charge"><?php esc_html_e( 'Base Charge (PKR)', 'doctor-ak-portal' ); ?></label>
				<input type="number" min="0" step="0.01" id="dak-admin-service-charge" name="charge" value="<?php echo esc_attr( $dak_is_editing ? $editing_service['charge'] : 0 ); ?>">
				<span class="dak-field-error" data-field="charge"></span>
			</div>
			<div class="dak-field">
				<label for="dak-admin-service-duration"><?php esc_html_e( 'Duration (minutes)', 'doctor-ak-portal' ); ?></label>
				<input type="number" min="0" max="480" step="1" id="dak-admin-service-duration" name="duration_minutes" value="<?php echo esc_attr( $dak_is_editing ? $editing_service['duration_minutes'] : 0 ); ?>">
				<span class="dak-field-error" data-field="duration_minutes"></span>
			</div>
		</div>

		<div class="dak-field">
			<label class="dak-checkbox">
				<input type="checkbox" id="dak-admin-service-active" name="active" value="1" <?php checked( ! $dak_is_editing || ! empty( $editing_service['active'] ) ); ?>>
				<span><?php esc_html_e( 'Active (visible to patients when booking, and on the public Services page)', 'doctor-ak-portal' ); ?></span>
			</label>
		</div>

		<div class="dak-field">
			<label for="dak-admin-service-description"><?php esc_html_e( 'Description', 'doctor-ak-portal' ); ?></label>
			<div class="dak-rich-text" data-rich-text>
				<?php echo \DoctorAKPortal\Includes\Rich_Text::toolbar_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapes its own output. ?>
				<div
					id="dak-admin-service-description"
					class="dak-rich-text-editor"
					contenteditable="true"
					role="textbox"
					aria-multiline="true"
					aria-label="<?php esc_attr_e( 'Description', 'doctor-ak-portal' ); ?>"
					data-placeholder="<?php esc_attr_e( "What this service is, who it's for, what to expect… (shown on the public Services page)", 'doctor-ak-portal' ); ?>"
				><?php echo $dak_is_editing ? wp_kses_post( $editing_service['description'] ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() output; pre-fills the editor with the existing (already-sanitized) formatted content. ?></div>
				<input type="hidden" name="description" value="<?php echo esc_attr( $dak_is_editing ? $editing_service['description'] : '' ); ?>" data-rich-text-value>
			</div>
			<span class="dak-field-error" data-field="description"></span>
		</div>

		<div class="dak-field">
			<label for="dak-admin-service-image"><?php esc_html_e( 'Image', 'doctor-ak-portal' ); ?></label>
			<div class="dak-service-portfolio-image-picker">
				<span class="dak-avatar dak-avatar-lg" id="dak-admin-service-image-preview-wrap">
					<img id="dak-admin-service-image-preview" src="<?php echo esc_url( $dak_is_editing ? $editing_service['image_url'] : '' ); ?>" alt="" class="<?php echo ( $dak_is_editing && $editing_service['image_url'] ) ? '' : 'dak-hidden'; ?>">
				</span>
				<input type="file" id="dak-admin-service-image" name="image" accept="image/jpeg,image/png,image/webp">
			</div>
			<p class="dak-field-hint"><?php esc_html_e( 'Shown on the public Services page — optional.', 'doctor-ak-portal' ); ?></p>
			<span class="dak-field-error" data-field="image"></span>
		</div>

		<?php $dak_clinic_charges = $dak_is_editing ? $editing_service['clinic_charges'] : array(); ?>

		<div class="dak-field">
			<label for="dak-admin-service-clinics"><?php esc_html_e( 'Clinics', 'doctor-ak-portal' ); ?></label>
			<select id="dak-admin-service-clinics" class="dak-select-searchable" data-placeholder="<?php esc_attr_e( 'Select clinics…', 'doctor-ak-portal' ); ?>" multiple>
				<?php foreach ( $clinic_locations as $clinic_location ) : ?>
					<option
						value="<?php echo esc_attr( $clinic_location['id'] ); ?>"
						data-clinic-name="<?php echo esc_attr( sprintf( '%1$s — %2$s, %3$s', $clinic_location['name'], $clinic_location['area_label'], $clinic_location['city_label'] ) ); ?>"
						<?php selected( array_key_exists( $clinic_location['id'], $dak_clinic_charges ), true ); ?>
					><?php echo esc_html( sprintf( '%1$s — %2$s, %3$s', $clinic_location['name'], $clinic_location['area_label'], $clinic_location['city_label'] ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="dak-field-hint"><?php esc_html_e( 'Clinics where this service is offered — pick as many as apply.', 'doctor-ak-portal' ); ?></p>
			<?php if ( empty( $clinic_locations ) ) : ?>
				<p class="dak-field-hint"><?php esc_html_e( 'No clinics added yet — add one first from the admin "Clinic" section.', 'doctor-ak-portal' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="dak-field" id="dak-admin-service-clinic-charges-field" data-preset="<?php echo esc_attr( wp_json_encode( $dak_clinic_charges ) ); ?>">
			<span class="dak-field-label"><?php esc_html_e( 'Price Per Clinic', 'doctor-ak-portal' ); ?></span>
			<div id="dak-admin-service-clinic-charges"></div>
			<p class="dak-field-hint"><?php esc_html_e( 'Shown on the public Services page against each clinic above — defaults to the Base Charge, editable per clinic.', 'doctor-ak-portal' ); ?></p>
		</div>

		<div class="dak-admin-user-form-actions">
			<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Cancel', 'doctor-ak-portal' ); ?></a>
			<button type="submit" class="dak-button dak-button-primary" id="dak-admin-service-submit">
				<span class="dak-button-label"><?php esc_html_e( 'Save Service', 'doctor-ak-portal' ); ?></span>
			</button>
		</div>
	</form>
</section>
