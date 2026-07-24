<?php
/**
 * Template: Registration form body for the [doctor_register] shortcode.
 *
 * Renders body content only — the active theme supplies header, navigation
 * and footer around this markup. All interactivity and submission is
 * handled by assets/js/doctor-ak-registration.js over AJAX.
 *
 * @package DoctorAKPortal\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$specializations = \DoctorAKPortal\Includes\Specializations::get_all();
$login_url       = \DoctorAKPortal\Includes\Page_Finder::url_for_shortcode( 'doctor_login' );

// Small, hand-drawn line icons (no external icon library dependency).
$dak_icons = array(
	'doctor'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6.5 3v4.5a3.5 3.5 0 0 0 7 0V3"/><path d="M6.5 5H5M13.5 5H15"/><path d="M13.5 9v2a3.5 3.5 0 0 1-7 0v-1"/><circle cx="15.5" cy="11.5" r="1.5"/></svg>',
	'patient'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="6.5" r="3"/><path d="M4 17c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/></svg>',
	'person'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M4 17c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/></svg>',
	'briefcase'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="14" height="9" rx="1.5"/><path d="M8 7V5.5A1.5 1.5 0 0 1 9.5 4h1A1.5 1.5 0 0 1 12 5.5V7"/></svg>',
	'camera'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="14" height="10" rx="2"/><path d="M7.2 6L8.4 4h3.2l1.2 2"/><circle cx="10" cy="11" r="3"/></svg>',
	'video'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="11" height="10" rx="1.5"/><path d="M13 8.3l5-2.8v9l-5-2.8"/></svg>',
	'clock'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7"/><path d="M10 6v4l3 2"/></svg>',
	'lock'       => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="9" width="12" height="8" rx="1.5"/><path d="M6.5 9V6.5a3.5 3.5 0 0 1 7 0V9"/></svg>',
	'upload'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 12.5V3.5"/><path d="M6.2 7.3L10 3.5l3.8 3.8"/><path d="M4 14v1.5A1.5 1.5 0 0 0 5.5 17h9a1.5 1.5 0 0 0 1.5-1.5V14"/></svg>',
	'check'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l3.5 3.5L16 5.5"/></svg>',
	'mail'       => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4.5" width="15" height="11" rx="1.5"/><path d="M3 5.5l7 5.5 7-5.5"/></svg>',
	'graduation' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 7.5l8-3.5 8 3.5-8 3.5-8-3.5z"/><path d="M5.5 9.2v3.3c0 1.1 2 2 4.5 2s4.5-.9 4.5-2V9.2"/><path d="M17 7.5v4.5"/></svg>',
	'pin'        => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17.5s6-5.1 6-9.5a6 6 0 1 0-12 0c0 4.4 6 9.5 6 9.5z"/><circle cx="10" cy="8" r="2"/></svg>',
	'phone'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4.3 2.8L7 3.4l0.8 2.6-1.8 1.5c0.9 2 2.5 3.6 4.5 4.5l1.5-1.8 2.6 0.8 0.6 2.7c0.1 0.5-0.3 1-0.8 1-5.8-0.3-10.5-5-10.8-10.8 0-0.5 0.5-0.9 1-0.8z"/></svg>',
);
?>
<div class="dak-portal dak-auth-page dak-registration-page">
	<div class="dak-auth-card dak-registration-card">
		<div class="dak-auth-header">
			<span class="dak-eyebrow"><?php esc_html_e( 'Join Us', 'doctor-ak-portal' ); ?></span>
			<h1><?php esc_html_e( 'Create your', 'doctor-ak-portal' ); ?> <span class="dak-accent"><?php esc_html_e( 'account', 'doctor-ak-portal' ); ?></span></h1>
			<p><?php esc_html_e( 'Register as a doctor to reach more patients, or as a patient to book consultations with our specialists.', 'doctor-ak-portal' ); ?></p>
		</div>

		<div class="dak-alert dak-alert-success dak-hidden" id="dak-register-success" role="status"></div>
		<div class="dak-alert dak-alert-error dak-hidden" id="dak-register-general-error" role="alert"></div>

		<form id="dak-registration-form" novalidate>

			<div class="dak-role-cards">
				<label class="dak-role-card is-selected" data-role="doctor">
					<input type="radio" name="register_as" value="doctor" checked>
					<span class="dak-role-card-icon"><?php echo $dak_icons['doctor']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted inline SVG. ?></span>
					<span class="dak-role-card-text">
						<strong><?php esc_html_e( "I'm a Doctor", 'doctor-ak-portal' ); ?></strong>
						<small><?php esc_html_e( 'Offer consultations & manage availability', 'doctor-ak-portal' ); ?></small>
					</span>
					<span class="dak-role-card-radio" aria-hidden="true"></span>
				</label>

				<label class="dak-role-card" data-role="patient">
					<input type="radio" name="register_as" value="patient">
					<span class="dak-role-card-icon"><?php echo $dak_icons['patient']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, trusted inline SVG. ?></span>
					<span class="dak-role-card-text">
						<strong><?php esc_html_e( "I'm a Patient", 'doctor-ak-portal' ); ?></strong>
						<small><?php esc_html_e( 'Book appointments & video consultations', 'doctor-ak-portal' ); ?></small>
					</span>
					<span class="dak-role-card-radio" aria-hidden="true"></span>
				</label>
			</div>

			<div id="dak-doctor-fields" class="dak-role-fields">

				<div class="dak-section">
					<div class="dak-section-header">
						<span class="dak-section-icon"><?php echo $dak_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<h2><?php esc_html_e( 'Personal Information', 'doctor-ak-portal' ); ?></h2>
					</div>

					<div class="dak-field-row">
						<div class="dak-field">
							<label for="dak-doctor-first-name"><?php esc_html_e( 'First Name', 'doctor-ak-portal' ); ?> <span class="dak-required">*</span></label>
							<input type="text" id="dak-doctor-first-name" name="first_name" required>
							<span class="dak-field-error" data-field="first_name"></span>
						</div>
						<div class="dak-field">
							<label for="dak-doctor-last-name"><?php esc_html_e( 'Last Name', 'doctor-ak-portal' ); ?> <span class="dak-required">*</span></label>
							<input type="text" id="dak-doctor-last-name" name="last_name" required>
							<span class="dak-field-error" data-field="last_name"></span>
						</div>
					</div>

					<div class="dak-field-row">
						<div class="dak-field">
							<label for="dak-doctor-email"><span class="dak-field-icon dak-field-icon-email"><?php echo $dak_icons['mail']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Email Address', 'doctor-ak-portal' ); ?> <span class="dak-required">*</span></label>
							<input type="email" id="dak-doctor-email" name="email" required autocomplete="email">
							<span class="dak-field-error" data-field="email"></span>
						</div>
						<div class="dak-field">
							<label for="dak-years-experience"><span class="dak-field-icon dak-field-icon-experience"><?php echo $dak_icons['graduation']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Years of Experience', 'doctor-ak-portal' ); ?> <span class="dak-required">*</span></label>
							<input type="number" min="0" max="80" id="dak-years-experience" name="years_experience" required>
							<span class="dak-field-error" data-field="years_experience"></span>
						</div>
					</div>

					<div class="dak-field">
						<label for="dak-qualification"><span class="dak-field-icon dak-field-icon-specialization"><?php echo $dak_icons['graduation']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Qualification', 'doctor-ak-portal' ); ?> <span class="dak-required">*</span></label>
						<input type="text" id="dak-qualification" name="qualification" placeholder="<?php esc_attr_e( 'e.g. MBBS, FCPS (Gastroenterology)', 'doctor-ak-portal' ); ?>" required>
						<span class="dak-field-error" data-field="qualification"></span>
					</div>

					<div class="dak-field">
						<label for="dak-specializations"><span class="dak-field-icon dak-field-icon-specialization"><?php echo $dak_icons['briefcase']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Specialization', 'doctor-ak-portal' ); ?> <span class="dak-required">*</span></label>
						<select id="dak-specializations" name="specializations[]" multiple>
							<?php foreach ( $specializations as $slug => $label ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="dak-field-error" data-field="specializations"></span>
					</div>

					<div class="dak-field">
						<label for="dak-expertise"><span class="dak-field-icon dak-field-icon-specialization"><?php echo $dak_icons['briefcase']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Other Expertise', 'doctor-ak-portal' ); ?></label>
						<textarea id="dak-expertise" name="expertise" rows="3" placeholder="<?php esc_attr_e( 'Any additional skills, procedures, or areas of interest not covered above (optional).', 'doctor-ak-portal' ); ?>"></textarea>
						<span class="dak-field-error" data-field="expertise"></span>
					</div>

					<p class="dak-field-hint"><?php esc_html_e( "You'll add your clinics and set your weekly session hours after registering, from your dashboard.", 'doctor-ak-portal' ); ?></p>
				</div>

				<div class="dak-section">
					<div class="dak-section-header">
						<span class="dak-section-icon"><?php echo $dak_icons['camera']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<h2><?php esc_html_e( 'Profile Picture', 'doctor-ak-portal' ); ?></h2>
					</div>

					<div class="dak-upload-control">
						<div class="dak-upload-preview" id="dak-profile-picture-preview" data-empty-html="<?php echo esc_attr( '<span class="dak-upload-placeholder-icon" aria-hidden="true">' . $dak_icons['person'] . '</span>' ); ?>">
							<span class="dak-upload-placeholder-icon" aria-hidden="true"><?php echo $dak_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						</div>
						<div class="dak-upload-control-text">
							<label class="dak-button dak-button-secondary dak-button-small" for="dak-profile-picture-input">
								<span class="dak-button-icon"><?php echo $dak_icons['upload']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php esc_html_e( 'Upload photo', 'doctor-ak-portal' ); ?>
							</label>
							<input type="file" id="dak-profile-picture-input" accept="image/jpeg,image/png,image/webp" class="dak-visually-hidden">
							<input type="hidden" name="profile_picture_id" id="dak-profile-picture-id" value="">
							<small><?php esc_html_e( 'JPG or PNG, up to 5MB', 'doctor-ak-portal' ); ?></small>
							<span class="dak-upload-status" id="dak-upload-status"></span>
						</div>
					</div>
					<span class="dak-field-error" data-field="profile_picture"></span>
				</div>

			</div>

			<div id="dak-patient-fields" class="dak-role-fields dak-hidden">
				<div class="dak-section">
					<div class="dak-section-header">
						<span class="dak-section-icon"><?php echo $dak_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<h2><?php esc_html_e( 'Your Information', 'doctor-ak-portal' ); ?></h2>
					</div>

					<div class="dak-field-row">
						<div class="dak-field">
							<label for="dak-patient-first-name"><?php esc_html_e( 'First Name', 'doctor-ak-portal' ); ?> <span class="dak-required">*</span></label>
							<input type="text" id="dak-patient-first-name" name="first_name" required>
							<span class="dak-field-error" data-field="first_name"></span>
						</div>
						<div class="dak-field">
							<label for="dak-patient-last-name"><?php esc_html_e( 'Last Name', 'doctor-ak-portal' ); ?> <span class="dak-required">*</span></label>
							<input type="text" id="dak-patient-last-name" name="last_name" required>
							<span class="dak-field-error" data-field="last_name"></span>
						</div>
					</div>

					<div class="dak-field-row">
						<div class="dak-field">
							<label for="dak-patient-email"><span class="dak-field-icon dak-field-icon-email"><?php echo $dak_icons['mail']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Email Address', 'doctor-ak-portal' ); ?> <span class="dak-required">*</span></label>
							<input type="email" id="dak-patient-email" name="email" required autocomplete="email">
							<span class="dak-field-error" data-field="email"></span>
						</div>
						<div class="dak-field">
							<label for="dak-phone-number"><span class="dak-field-icon dak-field-icon-phone"><?php echo $dak_icons['phone']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php esc_html_e( 'Phone Number', 'doctor-ak-portal' ); ?> <span class="dak-required">*</span></label>
							<input type="tel" id="dak-phone-number" name="phone_number" placeholder="0300000000" required>
							<span class="dak-field-error" data-field="phone_number"></span>
						</div>
					</div>
				</div>
			</div>

			<div class="dak-section">
				<div class="dak-section-header">
					<span class="dak-section-icon"><?php echo $dak_icons['lock']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<h2><?php esc_html_e( 'Set Your Password', 'doctor-ak-portal' ); ?></h2>
				</div>

				<div class="dak-field-row">
					<div class="dak-field">
						<label for="dak-password"><?php esc_html_e( 'Password', 'doctor-ak-portal' ); ?> <span class="dak-required">*</span></label>
						<div class="dak-password-field">
							<input type="password" id="dak-password" name="password" required autocomplete="new-password" placeholder="<?php esc_attr_e( 'At least 8 characters', 'doctor-ak-portal' ); ?>">
							<button type="button" class="dak-password-toggle" aria-label="<?php esc_attr_e( 'Show password', 'doctor-ak-portal' ); ?>">
								<svg class="dak-icon-eye" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1.5 10S4.5 4 10 4s8.5 6 8.5 6-3 6-8.5 6-8.5-6-8.5-6z"/><circle cx="10" cy="10" r="2.5"/></svg>
								<svg class="dak-icon-eye-off" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 2.5l15 15"/><path d="M8.3 4.3C8.85 4.1 9.42 4 10 4c5.5 0 8.5 6 8.5 6a14.9 14.9 0 0 1-3 3.9M5.5 5.6C3 7.2 1.5 10 1.5 10s3 6 8.5 6c1.1 0 2.1-.24 3-.63"/><path d="M7.8 7.8a2.5 2.5 0 0 0 3.4 3.5"/></svg>
							</button>
						</div>
						<span class="dak-field-error" data-field="password"></span>
					</div>
					<div class="dak-field">
						<label for="dak-confirm-password"><?php esc_html_e( 'Confirm Password', 'doctor-ak-portal' ); ?> <span class="dak-required">*</span></label>
						<div class="dak-password-field">
							<input type="password" id="dak-confirm-password" name="confirm_password" required autocomplete="new-password" placeholder="<?php esc_attr_e( 'Re-enter password', 'doctor-ak-portal' ); ?>">
							<button type="button" class="dak-password-toggle" aria-label="<?php esc_attr_e( 'Show password', 'doctor-ak-portal' ); ?>">
								<svg class="dak-icon-eye" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1.5 10S4.5 4 10 4s8.5 6 8.5 6-3 6-8.5 6-8.5-6-8.5-6z"/><circle cx="10" cy="10" r="2.5"/></svg>
								<svg class="dak-icon-eye-off" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 2.5l15 15"/><path d="M8.3 4.3C8.85 4.1 9.42 4 10 4c5.5 0 8.5 6 8.5 6a14.9 14.9 0 0 1-3 3.9M5.5 5.6C3 7.2 1.5 10 1.5 10s3 6 8.5 6c1.1 0 2.1-.24 3-.63"/><path d="M7.8 7.8a2.5 2.5 0 0 0 3.4 3.5"/></svg>
							</button>
						</div>
						<span class="dak-field-error" data-field="confirm_password"></span>
					</div>
				</div>

				<p class="dak-field-hint"><?php esc_html_e( 'Use your email along with this password to sign in later.', 'doctor-ak-portal' ); ?></p>
			</div>

			<p class="dak-terms-hint">
				<?php esc_html_e( 'By registering you agree to our Terms & Privacy Policy.', 'doctor-ak-portal' ); ?>
			</p>

			<button type="submit" class="dak-button dak-button-primary dak-button-block" id="dak-register-submit">
				<span class="dak-button-label" data-doctor-label="<?php esc_attr_e( 'Create Doctor Account', 'doctor-ak-portal' ); ?>" data-patient-label="<?php esc_attr_e( 'Create Patient Account', 'doctor-ak-portal' ); ?>"><?php esc_html_e( 'Create Doctor Account', 'doctor-ak-portal' ); ?></span>
			</button>

			<?php if ( $login_url ) : ?>
				<p class="dak-form-footer-link">
					<?php esc_html_e( 'Already have an account?', 'doctor-ak-portal' ); ?>
					<a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Sign in', 'doctor-ak-portal' ); ?></a>
				</p>
			<?php endif; ?>
		</form>
	</div>
</div>
