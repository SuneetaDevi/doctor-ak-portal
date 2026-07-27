<?php
/**
 * Template: Reusable profile-editing form.
 *
 * Shared by the standalone [doctor_profile] page (templates/profile/edit-profile.php)
 * and the doctor dashboard's in-page "Profile" tab
 * (templates/dashboard/doctor-dashboard.php), so the form markup, AJAX
 * wiring (assets/js/doctor-ak-profile.js) and validation only exist once.
 * Clinic location and video-consultation availability are managed from the
 * dashboard's separate "Clinics" tab instead (see doctor-clinics-tab.php).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var \WP_User $user                       Currently logged-in user.
 * @var bool     $is_doctor                  Whether the user holds the Doctor role.
 * @var array    $specializations            All specialization slug => label.
 * @var array    $current_specializations    Doctor's currently selected specialization slugs.
 * @var string   $current_years_experience   Doctor's current years of experience.
 * @var string   $current_qualification      Doctor's current qualification(s), e.g. "MBBS, FCPS".
 * @var string   $current_country            Doctor's current country slug, or ''.
 * @var string   $current_city               Doctor's current city slug, or ''.
 * @var string   $current_area               Doctor's current area slug, or ''.
 * @var string   $current_short_description  Doctor's current one-line profile tagline, or ''.
 * @var string   $current_expertise          Doctor's current other-expertise free text, or ''.
 * @var array    $current_awards             Doctor's current awards, see Doctor_Awards::get_for_doctor().
 * @var string   $current_phone_number       Patient's current phone number.
 * @var int      $current_profile_picture_id Current profile picture attachment ID.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_picture_url = $current_profile_picture_id ? wp_get_attachment_image_url( $current_profile_picture_id, 'thumbnail' ) : '';
?>
<div class="dak-alert dak-alert-success dak-hidden" id="dak-profile-success" role="status"></div>
<div class="dak-alert dak-alert-error dak-hidden" id="dak-profile-general-error" role="alert"></div>

<form id="dak-profile-form" novalidate>

	<div class="dak-field">
		<span class="dak-field-label"><?php esc_html_e( 'Profile Picture', 'doctor-ak-portal' ); ?></span>
		<div class="dak-upload-control">
			<div class="dak-upload-preview" id="dak-profile-picture-preview">
				<?php if ( $current_picture_url ) : ?>
					<img src="<?php echo esc_url( $current_picture_url ); ?>" alt="">
				<?php else : ?>
					<span class="dak-upload-placeholder"><?php esc_html_e( 'No photo selected', 'doctor-ak-portal' ); ?></span>
				<?php endif; ?>
			</div>
			<label class="dak-button dak-button-secondary" for="dak-profile-picture-input"><?php esc_html_e( 'Change Photo', 'doctor-ak-portal' ); ?></label>
			<input type="file" id="dak-profile-picture-input" accept="image/jpeg,image/png,image/webp" class="dak-visually-hidden">
			<span class="dak-upload-status" id="dak-upload-status"></span>
		</div>
	</div>

	<div class="dak-field-row">
		<div class="dak-field">
			<label for="dak-profile-first-name"><?php esc_html_e( 'First Name', 'doctor-ak-portal' ); ?></label>
			<input type="text" id="dak-profile-first-name" name="first_name" value="<?php echo esc_attr( $user->first_name ); ?>" required>
			<span class="dak-field-error" data-field="first_name"></span>
		</div>
		<div class="dak-field">
			<label for="dak-profile-last-name"><?php esc_html_e( 'Last Name', 'doctor-ak-portal' ); ?></label>
			<input type="text" id="dak-profile-last-name" name="last_name" value="<?php echo esc_attr( $user->last_name ); ?>" required>
			<span class="dak-field-error" data-field="last_name"></span>
		</div>
	</div>

	<div class="dak-field">
		<label for="dak-profile-email"><?php esc_html_e( 'Email', 'doctor-ak-portal' ); ?></label>
		<input type="email" id="dak-profile-email" name="email" value="<?php echo esc_attr( $user->user_email ); ?>" required autocomplete="email">
		<span class="dak-field-error" data-field="email"></span>
	</div>

	<?php if ( $is_doctor ) : ?>

		<div class="dak-field">
			<label for="dak-profile-years-experience"><?php esc_html_e( 'Years of Experience', 'doctor-ak-portal' ); ?></label>
			<input type="number" min="0" max="80" id="dak-profile-years-experience" name="years_experience" value="<?php echo esc_attr( $current_years_experience ); ?>">
			<span class="dak-field-error" data-field="years_experience"></span>
		</div>

		<div class="dak-field">
			<label for="dak-profile-qualification"><?php esc_html_e( 'Qualification', 'doctor-ak-portal' ); ?> <span class="dak-required">*</span></label>
			<input type="text" id="dak-profile-qualification" name="qualification" value="<?php echo esc_attr( $current_qualification ); ?>" placeholder="<?php esc_attr_e( 'e.g. MBBS, FCPS (Gastroenterology)', 'doctor-ak-portal' ); ?>" required>
			<span class="dak-field-error" data-field="qualification"></span>
		</div>

		<div class="dak-field-row">
			<div class="dak-field">
				<label for="dak-profile-country"><?php esc_html_e( 'Country', 'doctor-ak-portal' ); ?> <span class="dak-required">*</span></label>
				<select id="dak-profile-country" name="country" data-current="<?php echo esc_attr( $current_country ); ?>"></select>
				<span class="dak-field-error" data-field="country"></span>
			</div>
			<div class="dak-field">
				<label for="dak-profile-city"><?php esc_html_e( 'City', 'doctor-ak-portal' ); ?> <span class="dak-required">*</span></label>
				<select id="dak-profile-city" name="city" data-current="<?php echo esc_attr( $current_city ); ?>"></select>
				<span class="dak-field-error" data-field="city"></span>
			</div>
			<div class="dak-field">
				<label for="dak-profile-area"><?php esc_html_e( 'Area', 'doctor-ak-portal' ); ?> <span class="dak-required">*</span></label>
				<select id="dak-profile-area" name="area" data-current="<?php echo esc_attr( $current_area ); ?>"></select>
				<span class="dak-field-error" data-field="area"></span>
			</div>
		</div>

		<div class="dak-field">
			<label for="dak-profile-short-description"><?php esc_html_e( 'Short Description', 'doctor-ak-portal' ); ?></label>
			<textarea id="dak-profile-short-description" name="short_description" rows="3" placeholder="<?php esc_attr_e( 'e.g. Compassionate primary care with 12+ years of experience', 'doctor-ak-portal' ); ?>"><?php echo esc_textarea( $current_short_description ); ?></textarea>
			<p class="dak-field-hint"><?php esc_html_e( 'A tagline shown on your public profile.', 'doctor-ak-portal' ); ?></p>
			<span class="dak-field-error" data-field="short_description"></span>
		</div>

		<div class="dak-field">
			<label for="dak-profile-specializations"><?php esc_html_e( 'Specialization', 'doctor-ak-portal' ); ?></label>
			<select id="dak-profile-specializations" name="specializations[]" multiple>
				<?php foreach ( $specializations as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( in_array( $slug, $current_specializations, true ), true ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<span class="dak-field-error" data-field="specializations"></span>
		</div>

		<div class="dak-field">
			<label for="dak-profile-expertise"><?php esc_html_e( 'Other Expertise', 'doctor-ak-portal' ); ?></label>
			<textarea id="dak-profile-expertise" name="expertise" rows="3" placeholder="<?php esc_attr_e( 'Any additional skills, procedures, or areas of interest not covered above (optional).', 'doctor-ak-portal' ); ?>"><?php echo esc_textarea( $current_expertise ); ?></textarea>
			<span class="dak-field-error" data-field="expertise"></span>
		</div>

		<div class="dak-field">
			<span class="dak-field-label"><?php esc_html_e( 'Awards & Recognition', 'doctor-ak-portal' ); ?></span>
			<div class="dak-awards-editor" data-awards-editor>
				<div class="dak-awards-rows" data-awards-rows>
					<?php foreach ( $current_awards as $award ) : ?>
						<div class="dak-awards-row" data-awards-row>
							<input type="text" name="award_title[]" placeholder="<?php esc_attr_e( 'Award title', 'doctor-ak-portal' ); ?>" value="<?php echo esc_attr( $award['title'] ); ?>">
							<input type="number" name="award_year[]" placeholder="<?php esc_attr_e( 'Year', 'doctor-ak-portal' ); ?>" min="1950" max="<?php echo esc_attr( gmdate( 'Y' ) + 1 ); ?>" value="<?php echo esc_attr( $award['year'] ); ?>">
							<button type="button" class="dak-awards-remove" data-awards-remove-row aria-label="<?php esc_attr_e( 'Remove award', 'doctor-ak-portal' ); ?>">&times;</button>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="dak-button dak-button-secondary dak-button-sm" data-awards-add-row>
					<?php esc_html_e( '+ Add Award', 'doctor-ak-portal' ); ?>
				</button>
			</div>
			<span class="dak-field-error" data-field="awards"></span>
		</div>

	<?php else : ?>

		<div class="dak-field">
			<label for="dak-profile-phone-number"><?php esc_html_e( 'Phone Number', 'doctor-ak-portal' ); ?> <span class="dak-required">*</span></label>
			<input type="tel" id="dak-profile-phone-number" name="phone_number" value="<?php echo esc_attr( $current_phone_number ); ?>" required>
			<span class="dak-field-error" data-field="phone_number"></span>
		</div>

	<?php endif; ?>

	<h2><?php esc_html_e( 'Change Password', 'doctor-ak-portal' ); ?></h2>
	<p class="dak-field-hint"><?php esc_html_e( 'Leave these blank to keep your current password.', 'doctor-ak-portal' ); ?></p>

	<div class="dak-field">
		<label for="dak-profile-current-password"><?php esc_html_e( 'Current Password', 'doctor-ak-portal' ); ?></label>
		<input type="password" id="dak-profile-current-password" name="current_password" autocomplete="current-password">
		<span class="dak-field-error" data-field="current_password"></span>
	</div>

	<div class="dak-field-row">
		<div class="dak-field">
			<label for="dak-profile-new-password"><?php esc_html_e( 'New Password', 'doctor-ak-portal' ); ?></label>
			<input type="password" id="dak-profile-new-password" name="new_password" autocomplete="new-password">
			<span class="dak-field-error" data-field="new_password"></span>
		</div>
		<div class="dak-field">
			<label for="dak-profile-confirm-new-password"><?php esc_html_e( 'Confirm New Password', 'doctor-ak-portal' ); ?></label>
			<input type="password" id="dak-profile-confirm-new-password" name="confirm_new_password" autocomplete="new-password">
			<span class="dak-field-error" data-field="confirm_new_password"></span>
		</div>
	</div>

	<button type="submit" class="dak-button dak-button-primary dak-button-block" id="dak-profile-submit">
		<span class="dak-button-label"><?php esc_html_e( 'Save Changes', 'doctor-ak-portal' ); ?></span>
	</button>
</form>
