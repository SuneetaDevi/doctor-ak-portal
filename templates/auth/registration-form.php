<?php
/**
 * Template: Registration form body for the [doctor_register] shortcode.
 *
 * Patient sign-up is one short page; doctor sign-up is three steps
 * (account → professional profile → location & review).
 * Everything is one form posted once at the end — nothing is created on
 * "Continue" — and the server validates the whole submission again
 * (Registration_Handler::handle_register()). Behaviour lives in
 * assets/js/doctor-ak-account-register.js (flow) and doctor-ak-registration.js
 * (shared tag picker), over AJAX.
 *
 * Role context: general links open Patient; `?role=doctor` (e.g. "Join as a
 * Doctor") opens Doctor. Only those two roles are ever accepted server-side.
 *
 * @package DoctorAKPortal\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$specializations = \DoctorAKPortal\Includes\Specializations::get_all();
$keywords        = \DoctorAKPortal\Includes\Doctor_Keywords::get_all();
$login_url       = \DoctorAKPortal\Includes\Page_Finder::url_for_shortcode( 'doctor_login' );
$home_url        = home_url( '/' );
$loader          = new \DoctorAKPortal\Includes\Template_Loader();

// A caller (e.g. the booking page's "Create an account" link) can ask to be
// returned somewhere after signing up. Patient registration doesn't log the
// new user in, so that target is carried through the login page instead —
// the same-site check here and Login_Handler's own wp_validate_redirect()
// keep it from becoming an open redirect.
$dak_register_redirect_to = isset( $_GET['redirect_to'] ) ? wp_validate_redirect( wp_unslash( $_GET['redirect_to'] ), '' ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only redirect target, validated same-site.

if ( '' !== $dak_register_redirect_to && $login_url ) {
	$login_url = add_query_arg( 'redirect_to', rawurlencode( $dak_register_redirect_to ), $login_url );
}

// Which role the form opens with — Patient unless a doctor link asked otherwise.
$dak_requested_role = isset( $_GET['role'] ) ? sanitize_key( wp_unslash( $_GET['role'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display preference.
$dak_initial_role   = 'doctor' === $dak_requested_role ? 'doctor' : 'patient';

$dak_privacy_page = function_exists( 'get_page_by_path' ) ? get_page_by_path( 'privacy-policy' ) : null;
$dak_privacy_url  = $dak_privacy_page ? get_permalink( $dak_privacy_page ) : '';

/**
 * An inline error slot linked to its field.
 *
 * @param string $field Field name the server reports errors under.
 * @param string $id    Element id (referenced by the field's aria-describedby).
 */
$dak_error = function ( $field, $id ) {
	echo '<span class="dak-field-error" id="' . esc_attr( $id ) . '" data-field="' . esc_attr( $field ) . '"></span>';
};

$dak_req = '<span class="dak-required" aria-hidden="true">*</span>';

$dak_months = array();
for ( $dak_m = 1; $dak_m <= 12; $dak_m++ ) {
	$dak_months[ $dak_m ] = date_i18n( 'F', mktime( 0, 0, 0, $dak_m, 1, 2000 ) );
}
?>
<div class="dak-portal dak-acct dak-acct--register" data-dak-register data-initial-role="<?php echo esc_attr( $dak_initial_role ); ?>">
	<div class="dak-acct-shell">
		<?php
		echo $loader->get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial escapes its own output.
			'auth/partials/account-aside.php',
			array(
				'title' => __( 'Book and manage care with trusted doctors', 'doctor-ak-portal' ),
				'text'  => __( 'Patients can start booking straight away. Doctors join after a short review by our team.', 'doctor-ak-portal' ),
			)
		);
		?>

		<main class="dak-acct-card">
			<div class="dak-acct-main">
			<header class="dak-acct-header">
				<h1 id="dak-register-title" tabindex="-1"
					data-patient="<?php esc_attr_e( 'Create your patient account', 'doctor-ak-portal' ); ?>"
					data-doctor="<?php esc_attr_e( 'Register as a doctor', 'doctor-ak-portal' ); ?>"
				><?php echo 'doctor' === $dak_initial_role ? esc_html__( 'Register as a doctor', 'doctor-ak-portal' ) : esc_html__( 'Create your patient account', 'doctor-ak-portal' ); ?></h1>
				<p id="dak-register-intro"
					data-patient="<?php esc_attr_e( 'Book appointments and manage your visits in one place.', 'doctor-ak-portal' ); ?>"
					data-doctor="<?php esc_attr_e( 'Tell us about yourself and your practice. Our team reviews every doctor registration before the account can sign in, and we email you once it is approved.', 'doctor-ak-portal' ); ?>"
				><?php echo 'doctor' === $dak_initial_role ? esc_html__( 'Tell us about yourself and your practice. Our team reviews every doctor registration before the account can sign in, and we email you once it is approved.', 'doctor-ak-portal' ) : esc_html__( 'Book appointments and manage your visits in one place.', 'doctor-ak-portal' ); ?></p>
			</header>

			<div class="dak-alert dak-alert-error dak-hidden" id="dak-register-general-error" role="alert" tabindex="-1">
				<p class="dak-acct-summary-title" data-summary-title></p>
				<ul class="dak-acct-summary-list" data-summary-list></ul>
			</div>

			<form id="dak-registration-form" class="dak-acct-form" novalidate>

				<fieldset class="dak-acct-roles">
					<legend><?php esc_html_e( 'I am registering as', 'doctor-ak-portal' ); ?></legend>
					<div class="dak-role-cards">
						<label class="dak-role-card<?php echo 'patient' === $dak_initial_role ? ' is-selected' : ''; ?>" data-role="patient">
							<input type="radio" name="register_as" value="patient" <?php checked( 'patient', $dak_initial_role ); ?>>
							<span class="dak-role-card-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="6.5" r="3"/><path d="M4 17c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/></svg></span>
							<span class="dak-role-card-text">
								<strong><?php esc_html_e( 'Patient', 'doctor-ak-portal' ); ?></strong>
								<small><?php esc_html_e( 'Book appointments and manage your care.', 'doctor-ak-portal' ); ?></small>
							</span>
						</label>
						<label class="dak-role-card<?php echo 'doctor' === $dak_initial_role ? ' is-selected' : ''; ?>" data-role="doctor">
							<input type="radio" name="register_as" value="doctor" <?php checked( 'doctor', $dak_initial_role ); ?>>
							<span class="dak-role-card-icon" aria-hidden="true"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6.5 3v4.5a3.5 3.5 0 0 0 7 0V3"/><path d="M6.5 5H5M13.5 5H15"/><path d="M13.5 9v2a3.5 3.5 0 0 1-7 0v-1"/><circle cx="15.5" cy="11.5" r="1.5"/></svg></span>
							<span class="dak-role-card-text">
								<strong><?php esc_html_e( 'Doctor', 'doctor-ak-portal' ); ?></strong>
								<small><?php esc_html_e( 'Manage your practice and connect with patients.', 'doctor-ak-portal' ); ?></small>
							</span>
						</label>
					</div>
				</fieldset>

				<?php // ------------------------------------------------------------ Patient. ?>
				<div id="dak-patient-fields" class="dak-role-fields" data-role-fields="patient"<?php echo 'patient' !== $dak_initial_role ? ' hidden' : ''; ?>>
					<div class="dak-field-row">
						<div class="dak-field">
							<label for="dak-patient-first-name"><?php esc_html_e( 'First name', 'doctor-ak-portal' ); ?> <?php echo $dak_req; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static. ?></label>
							<input type="text" id="dak-patient-first-name" name="first_name" required aria-required="true" autocomplete="given-name" data-share="first_name" aria-describedby="dak-patient-first-name-error">
							<?php $dak_error( 'first_name', 'dak-patient-first-name-error' ); ?>
						</div>
						<div class="dak-field">
							<label for="dak-patient-last-name"><?php esc_html_e( 'Last name', 'doctor-ak-portal' ); ?> <?php echo $dak_req; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static. ?></label>
							<input type="text" id="dak-patient-last-name" name="last_name" required aria-required="true" autocomplete="family-name" data-share="last_name" aria-describedby="dak-patient-last-name-error">
							<?php $dak_error( 'last_name', 'dak-patient-last-name-error' ); ?>
						</div>
					</div>

					<div class="dak-field">
						<label for="dak-patient-email"><?php esc_html_e( 'Email', 'doctor-ak-portal' ); ?> <?php echo $dak_req; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static. ?></label>
						<input type="email" id="dak-patient-email" name="email" required aria-required="true" autocomplete="email" autocapitalize="none" spellcheck="false" data-share="email" aria-describedby="dak-patient-email-hint dak-patient-email-error">
						<p class="dak-acct-hint" id="dak-patient-email-hint"><?php esc_html_e( 'You will use this email to sign in.', 'doctor-ak-portal' ); ?></p>
						<?php $dak_error( 'email', 'dak-patient-email-error' ); ?>
					</div>

					<div class="dak-field">
						<label for="dak-phone-number-number"><?php esc_html_e( 'Phone number', 'doctor-ak-portal' ); ?> <?php echo $dak_req; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static. ?></label>
						<?php
						echo $loader->get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes its own output.
							'partials/phone-field.php',
							array(
								'id_prefix' => 'dak-phone-number',
								'dial_code' => \DoctorAKPortal\Includes\Phone::DEFAULT_DIAL_CODE,
								'number'    => '',
								'required'  => true,
							)
						);
						?>
						<?php $dak_error( 'phone_number', 'dak-phone-number-error' ); ?>
					</div>

					<fieldset class="dak-field dak-acct-dob" aria-describedby="dak-patient-dob-hint dak-patient-dob-error">
						<legend class="dak-field-label"><?php esc_html_e( 'Date of birth', 'doctor-ak-portal' ); ?> <?php echo $dak_req; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static. ?></legend>
						<div class="dak-acct-dob-parts" data-dob>
							<div class="dak-acct-dob-part">
								<label for="dak-patient-dob-day" class="dak-acct-sublabel"><?php esc_html_e( 'Day', 'doctor-ak-portal' ); ?></label>
								<input type="text" id="dak-patient-dob-day" inputmode="numeric" pattern="[0-9]*" maxlength="2" autocomplete="bday-day" placeholder="<?php esc_attr_e( 'DD', 'doctor-ak-portal' ); ?>" data-dob-day>
							</div>
							<div class="dak-acct-dob-part dak-acct-dob-month">
								<label for="dak-patient-dob-month" class="dak-acct-sublabel"><?php esc_html_e( 'Month', 'doctor-ak-portal' ); ?></label>
								<select id="dak-patient-dob-month" autocomplete="bday-month" data-dob-month>
									<option value=""><?php esc_html_e( 'Month', 'doctor-ak-portal' ); ?></option>
									<?php foreach ( $dak_months as $dak_num => $dak_name ) : ?>
										<option value="<?php echo esc_attr( $dak_num ); ?>"><?php echo esc_html( $dak_name ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="dak-acct-dob-part">
								<label for="dak-patient-dob-year" class="dak-acct-sublabel"><?php esc_html_e( 'Year', 'doctor-ak-portal' ); ?></label>
								<input type="text" id="dak-patient-dob-year" inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="bday-year" placeholder="<?php esc_attr_e( 'YYYY', 'doctor-ak-portal' ); ?>" data-dob-year>
							</div>
						</div>
						<input type="hidden" name="date_of_birth" id="dak-patient-dob" data-dob-value>
						<p class="dak-acct-hint" id="dak-patient-dob-hint"><?php esc_html_e( 'For example 14 March 1985.', 'doctor-ak-portal' ); ?></p>
						<?php $dak_error( 'date_of_birth', 'dak-patient-dob-error' ); ?>
					</fieldset>
				</div>

				<?php // ------------------------------------------------------------ Doctor. ?>
				<div id="dak-doctor-fields" class="dak-role-fields" data-role-fields="doctor"<?php echo 'doctor' !== $dak_initial_role ? ' hidden' : ''; ?>>
					<ol class="dak-acct-steps" aria-label="<?php esc_attr_e( 'Registration steps', 'doctor-ak-portal' ); ?>">
						<li data-step-indicator="1"><span class="dak-acct-step-num">1</span><span class="dak-acct-step-name"><?php esc_html_e( 'Account', 'doctor-ak-portal' ); ?></span></li>
						<li data-step-indicator="2"><span class="dak-acct-step-num">2</span><span class="dak-acct-step-name"><?php esc_html_e( 'Professional profile', 'doctor-ak-portal' ); ?></span></li>
						<li data-step-indicator="3"><span class="dak-acct-step-num">3</span><span class="dak-acct-step-name"><?php esc_html_e( 'Review', 'doctor-ak-portal' ); ?></span></li>
					</ol>
					<p class="dak-acct-step-status" aria-live="polite" data-step-status></p>

					<section class="dak-acct-step" data-step="1" aria-labelledby="dak-doctor-step-1-title">
						<h2 id="dak-doctor-step-1-title" tabindex="-1"><?php esc_html_e( 'Account details', 'doctor-ak-portal' ); ?></h2>

						<div class="dak-field-row">
							<div class="dak-field">
								<label for="dak-doctor-first-name"><?php esc_html_e( 'First name', 'doctor-ak-portal' ); ?> <?php echo $dak_req; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static. ?></label>
								<input type="text" id="dak-doctor-first-name" name="first_name" required aria-required="true" autocomplete="given-name" data-share="first_name" aria-describedby="dak-doctor-first-name-error">
								<?php $dak_error( 'first_name', 'dak-doctor-first-name-error' ); ?>
							</div>
							<div class="dak-field">
								<label for="dak-doctor-last-name"><?php esc_html_e( 'Last name', 'doctor-ak-portal' ); ?> <?php echo $dak_req; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static. ?></label>
								<input type="text" id="dak-doctor-last-name" name="last_name" required aria-required="true" autocomplete="family-name" data-share="last_name" aria-describedby="dak-doctor-last-name-error">
								<?php $dak_error( 'last_name', 'dak-doctor-last-name-error' ); ?>
							</div>
						</div>

						<div class="dak-field">
							<label for="dak-doctor-email"><?php esc_html_e( 'Email', 'doctor-ak-portal' ); ?> <?php echo $dak_req; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static. ?></label>
							<input type="email" id="dak-doctor-email" name="email" required aria-required="true" autocomplete="email" autocapitalize="none" spellcheck="false" data-share="email" aria-describedby="dak-doctor-email-hint dak-doctor-email-error">
							<p class="dak-acct-hint" id="dak-doctor-email-hint"><?php esc_html_e( 'You will use this email to sign in.', 'doctor-ak-portal' ); ?></p>
							<?php $dak_error( 'email', 'dak-doctor-email-error' ); ?>
						</div>

						<div class="dak-field-row">
							<div class="dak-field">
								<label for="dak-doctor-phone-number"><?php esc_html_e( 'Phone number', 'doctor-ak-portal' ); ?> <?php echo $dak_req; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static. ?></label>
								<?php
								echo $loader->get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes its own output.
									'partials/phone-field.php',
									array(
										'id_prefix' => 'dak-doctor-phone',
										'dial_code' => \DoctorAKPortal\Includes\Phone::DEFAULT_DIAL_CODE,
										'number'    => '',
										'required'  => true,
									)
								);
								?>
								<?php $dak_error( 'phone_number', 'dak-doctor-phone-error' ); ?>
							</div>
							<div class="dak-field dak-field-narrow">
								<label for="dak-gender"><?php esc_html_e( 'Gender', 'doctor-ak-portal' ); ?> <?php echo $dak_req; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static. ?></label>
								<select id="dak-gender" name="gender" required aria-required="true" aria-describedby="dak-gender-error">
									<option value=""><?php esc_html_e( 'Select', 'doctor-ak-portal' ); ?></option>
									<?php foreach ( \DoctorAKPortal\Includes\Doctor_Gender::get_all() as $dak_gender_slug => $dak_gender_label ) : ?>
										<option value="<?php echo esc_attr( $dak_gender_slug ); ?>"><?php echo esc_html( $dak_gender_label ); ?></option>
									<?php endforeach; ?>
								</select>
								<?php $dak_error( 'gender', 'dak-gender-error' ); ?>
							</div>
						</div>

						<div data-password-slot="doctor"></div>
					</section>

					<section class="dak-acct-step" data-step="2" aria-labelledby="dak-doctor-step-2-title" hidden>
						<h2 id="dak-doctor-step-2-title" tabindex="-1"><?php esc_html_e( 'Professional profile', 'doctor-ak-portal' ); ?></h2>

						<div class="dak-field-row">
							<div class="dak-field">
								<label for="dak-qualification"><?php esc_html_e( 'Qualifications', 'doctor-ak-portal' ); ?> <?php echo $dak_req; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static. ?></label>
								<input type="text" id="dak-qualification" name="qualification" required aria-required="true" placeholder="<?php esc_attr_e( 'e.g. MBBS, FCPS (Gastroenterology)', 'doctor-ak-portal' ); ?>" aria-describedby="dak-qualification-error">
								<?php $dak_error( 'qualification', 'dak-qualification-error' ); ?>
							</div>
							<div class="dak-field dak-field-narrow">
								<label for="dak-years-experience"><?php esc_html_e( 'Years of experience', 'doctor-ak-portal' ); ?> <?php echo $dak_req; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static. ?></label>
								<input type="number" min="0" max="80" step="1" inputmode="numeric" id="dak-years-experience" name="years_experience" required aria-required="true" aria-describedby="dak-years-experience-error">
								<?php $dak_error( 'years_experience', 'dak-years-experience-error' ); ?>
							</div>
						</div>

						<div class="dak-field">
							<label for="dak-specializations" id="dak-specializations-label"><?php esc_html_e( 'Specializations', 'doctor-ak-portal' ); ?> <?php echo $dak_req; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static. ?></label>
							<select id="dak-specializations" name="specializations[]" multiple aria-describedby="dak-specializations-hint dak-specializations-error">
								<?php foreach ( $specializations as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="dak-acct-hint" id="dak-specializations-hint"><?php esc_html_e( 'Choose at least one. Search, then press Enter or click to add.', 'doctor-ak-portal' ); ?></p>
							<?php $dak_error( 'specializations', 'dak-specializations-error' ); ?>
						</div>

						<div class="dak-field">
							<label for="dak-short-description"><?php esc_html_e( 'Short description', 'doctor-ak-portal' ); ?> <span class="dak-acct-optional"><?php esc_html_e( '(optional)', 'doctor-ak-portal' ); ?></span></label>
							<textarea id="dak-short-description" name="short_description" rows="2" maxlength="300" placeholder="<?php esc_attr_e( 'e.g. Consultant gastroenterologist focusing on liver disease', 'doctor-ak-portal' ); ?>" aria-describedby="dak-short-description-hint dak-short-description-error"></textarea>
							<p class="dak-acct-hint" id="dak-short-description-hint"><?php esc_html_e( 'One line shown on your public profile.', 'doctor-ak-portal' ); ?></p>
							<?php $dak_error( 'short_description', 'dak-short-description-error' ); ?>
						</div>

						<div class="dak-field">
							<label for="dak-keywords" id="dak-keywords-label"><?php esc_html_e( 'Keywords', 'doctor-ak-portal' ); ?> <span class="dak-acct-optional"><?php esc_html_e( '(optional)', 'doctor-ak-portal' ); ?></span></label>
							<select id="dak-keywords" name="keywords[]" multiple aria-describedby="dak-keywords-hint dak-keywords-error">
								<?php foreach ( $keywords as $dak_keyword ) : ?>
									<option value="<?php echo esc_attr( $dak_keyword ); ?>"><?php echo esc_html( $dak_keyword ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="dak-acct-hint" id="dak-keywords-hint"><?php esc_html_e( 'Procedures or conditions patients search for, e.g. Colonoscopy. Type one and press Enter.', 'doctor-ak-portal' ); ?></p>
							<?php $dak_error( 'keywords', 'dak-keywords-error' ); ?>
						</div>

						<div class="dak-field">
							<label for="dak-expertise" id="dak-expertise-label"><?php esc_html_e( 'Other expertise', 'doctor-ak-portal' ); ?> <span class="dak-acct-optional"><?php esc_html_e( '(optional)', 'doctor-ak-portal' ); ?></span></label>
							<div class="dak-rich-text" data-rich-text>
								<?php echo \DoctorAKPortal\Includes\Rich_Text::toolbar_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapes its own output. ?>
								<div
									id="dak-expertise"
									class="dak-rich-text-editor"
									contenteditable="true"
									role="textbox"
									aria-multiline="true"
									aria-labelledby="dak-expertise-label"
									data-placeholder="<?php esc_attr_e( 'Skills, procedures or interests not covered above.', 'doctor-ak-portal' ); ?>"
								></div>
								<input type="hidden" name="expertise" data-rich-text-value>
							</div>
							<?php $dak_error( 'expertise', 'dak-expertise-error' ); ?>
						</div>

						<div class="dak-field">
							<span class="dak-field-label" id="dak-photo-label"><?php esc_html_e( 'Profile photo', 'doctor-ak-portal' ); ?> <span class="dak-acct-optional"><?php esc_html_e( '(optional)', 'doctor-ak-portal' ); ?></span></span>
							<div class="dak-acct-photo" data-photo>
								<div class="dak-acct-photo-preview" id="dak-profile-picture-preview" aria-hidden="true">
									<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M4 17c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/></svg>
								</div>
								<div class="dak-acct-photo-body">
									<div class="dak-acct-photo-actions">
										<label class="dak-button dak-button-secondary dak-button-sm" for="dak-profile-picture-input" data-photo-choose><?php esc_html_e( 'Upload photo', 'doctor-ak-portal' ); ?></label>
										<button type="button" class="dak-button dak-button-secondary dak-button-sm" data-photo-remove hidden><?php esc_html_e( 'Remove', 'doctor-ak-portal' ); ?></button>
									</div>
									<input type="file" id="dak-profile-picture-input" accept="image/jpeg,image/png,image/webp" class="dak-visually-hidden" aria-labelledby="dak-photo-label" aria-describedby="dak-photo-hint dak-upload-status">
									<input type="hidden" name="profile_picture_id" id="dak-profile-picture-id" value="">
									<p class="dak-acct-hint" id="dak-photo-hint"><?php esc_html_e( 'JPG, PNG or WebP, up to 5 MB.', 'doctor-ak-portal' ); ?></p>
									<p class="dak-upload-status" id="dak-upload-status" role="status" aria-live="polite"></p>
								</div>
							</div>
							<?php $dak_error( 'profile_picture', 'dak-profile-picture-error' ); ?>
						</div>
					</section>

					<section class="dak-acct-step" data-step="3" aria-labelledby="dak-doctor-step-3-title" hidden>
						<h2 id="dak-doctor-step-3-title" tabindex="-1"><?php esc_html_e( 'Location and review', 'doctor-ak-portal' ); ?></h2>

						<fieldset class="dak-acct-group">
							<legend><?php esc_html_e( 'Where you practise', 'doctor-ak-portal' ); ?></legend>
							<div class="dak-field-row dak-field-row-3">
								<div class="dak-field">
									<label for="dak-country"><?php esc_html_e( 'Country', 'doctor-ak-portal' ); ?> <?php echo $dak_req; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static. ?></label>
									<select id="dak-country" name="country" required aria-required="true" aria-describedby="dak-country-error"></select>
									<?php $dak_error( 'country', 'dak-country-error' ); ?>
								</div>
								<div class="dak-field">
									<label for="dak-city"><?php esc_html_e( 'City', 'doctor-ak-portal' ); ?> <?php echo $dak_req; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static. ?></label>
									<select id="dak-city" name="city" required aria-required="true" aria-describedby="dak-city-hint dak-city-error"></select>
									<p class="dak-acct-hint" id="dak-city-hint" data-location-hint="city"><?php esc_html_e( 'Choose a country first.', 'doctor-ak-portal' ); ?></p>
									<?php $dak_error( 'city', 'dak-city-error' ); ?>
								</div>
								<div class="dak-field">
									<label for="dak-area"><?php esc_html_e( 'Area', 'doctor-ak-portal' ); ?> <?php echo $dak_req; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static. ?></label>
									<select id="dak-area" name="area" required aria-required="true" aria-describedby="dak-area-hint dak-area-error"></select>
									<p class="dak-acct-hint" id="dak-area-hint" data-location-hint="area"><?php esc_html_e( 'Choose a city first.', 'doctor-ak-portal' ); ?></p>
									<?php $dak_error( 'area', 'dak-area-error' ); ?>
								</div>
							</div>
						</fieldset>

						<div class="dak-acct-review" data-review aria-label="<?php esc_attr_e( 'Review your details', 'doctor-ak-portal' ); ?>"></div>

						<div data-terms-slot="doctor"></div>

						<p class="dak-acct-note"><strong><?php esc_html_e( 'What happens next:', 'doctor-ak-portal' ); ?></strong> <?php esc_html_e( 'our team reviews your registration and we email you once it is approved. You can then sign in and add your clinics and weekly session hours.', 'doctor-ak-portal' ); ?></p>
					</section>
				</div>

				<?php // ------------------------------------------------------------ Shared: password, then consent (each moved by JS into the active role's slot). ?>
				<div data-password-slot="patient"></div>
				<div class="dak-acct-security" data-password-block>
					<div class="dak-field-row">
						<div class="dak-field">
							<label for="dak-password"><?php esc_html_e( 'Password', 'doctor-ak-portal' ); ?> <?php echo $dak_req; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static. ?></label>
							<?php
							echo $loader->get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial escapes its own output.
								'auth/partials/password-input.php',
								array(
									'id'           => 'dak-password',
									'name'         => 'password',
									'autocomplete' => 'new-password',
									'describedby'  => 'dak-password-hint dak-password-error',
								)
							);
							?>
							<p class="dak-acct-hint dak-acct-rule" id="dak-password-hint" data-password-rule><?php esc_html_e( 'At least 8 characters.', 'doctor-ak-portal' ); ?></p>
							<?php $dak_error( 'password', 'dak-password-error' ); ?>
						</div>
						<div class="dak-field">
							<label for="dak-confirm-password"><?php esc_html_e( 'Confirm password', 'doctor-ak-portal' ); ?> <?php echo $dak_req; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static. ?></label>
							<?php
							echo $loader->get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial escapes its own output.
								'auth/partials/password-input.php',
								array(
									'id'           => 'dak-confirm-password',
									'name'         => 'confirm_password',
									'autocomplete' => 'new-password',
									'describedby'  => 'dak-confirm-password-error',
								)
							);
							?>
							<?php $dak_error( 'confirm_password', 'dak-confirm-password-error' ); ?>
						</div>
					</div>
				</div>

				<div data-terms-slot="patient"></div>
				<div class="dak-acct-consent" data-terms-block>
					<div class="dak-field dak-terms-field">
						<div class="dak-checkbox dak-acct-terms">
							<input type="checkbox" id="dak-terms-accepted" name="terms_accepted" value="1" required aria-required="true" aria-describedby="dak-terms-accepted-error">
							<span>
								<label for="dak-terms-accepted"><?php esc_html_e( 'I have read and agree to the', 'doctor-ak-portal' ); ?></label>
								<button type="button" id="dak-terms-view-link" class="dak-acct-link" aria-haspopup="dialog"><?php esc_html_e( 'Terms & Conditions', 'doctor-ak-portal' ); ?></button><?php if ( '' !== $dak_privacy_url ) : ?><?php esc_html_e( '. See our', 'doctor-ak-portal' ); ?>
									<a href="<?php echo esc_url( $dak_privacy_url ); ?>" class="dak-acct-link" target="_blank" rel="noopener"><?php esc_html_e( 'Privacy Policy', 'doctor-ak-portal' ); ?><span class="dak-visually-hidden"> <?php esc_html_e( '(opens in a new tab)', 'doctor-ak-portal' ); ?></span></a><?php endif; ?>.
							</span>
						</div>
						<?php $dak_error( 'terms_accepted', 'dak-terms-accepted-error' ); ?>
					</div>
				</div>

				<div class="dak-acct-actions">
					<button type="button" class="dak-button dak-button-secondary" data-step-back hidden><?php esc_html_e( 'Back', 'doctor-ak-portal' ); ?></button>
					<button type="button" class="dak-button dak-button-primary" data-step-next hidden><?php esc_html_e( 'Continue', 'doctor-ak-portal' ); ?></button>
					<button type="submit" class="dak-button dak-button-primary dak-acct-submit" id="dak-register-submit"
						data-patient-label="<?php esc_attr_e( 'Create account', 'doctor-ak-portal' ); ?>"
						data-doctor-label="<?php esc_attr_e( 'Submit registration', 'doctor-ak-portal' ); ?>"
						data-loading-label="<?php esc_attr_e( 'Submitting…', 'doctor-ak-portal' ); ?>">
						<span class="dak-button-label"><?php echo 'doctor' === $dak_initial_role ? esc_html__( 'Submit registration', 'doctor-ak-portal' ) : esc_html__( 'Create account', 'doctor-ak-portal' ); ?></span>
					</button>
				</div>
			</form>

			<?php if ( $login_url ) : ?>
				<p class="dak-acct-switch">
					<?php esc_html_e( 'Already have an account?', 'doctor-ak-portal' ); ?>
					<a href="<?php echo esc_url( $login_url ); ?>" class="dak-acct-link"><?php esc_html_e( 'Sign in', 'doctor-ak-portal' ); ?></a>
				</p>
			<?php endif; ?>
			</div>
		</main>
	</div>
	<?php echo $loader->get_template( 'auth/partials/account-footer.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial escapes its own output. ?>
</div>

<div class="dak-portal dak-modal dak-acct-modal" id="dak-terms-modal" aria-hidden="true">
	<div class="dak-modal-overlay" data-dak-terms-close></div>

	<div class="dak-modal-dialog dak-terms-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-terms-modal-title" tabindex="-1">
		<button type="button" class="dak-modal-close" data-dak-terms-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>

		<div class="dak-modal-header">
			<h2 id="dak-terms-modal-title"><?php esc_html_e( 'Terms & Conditions', 'doctor-ak-portal' ); ?></h2>
		</div>

		<div class="dak-terms-modal-body">
			<div id="dak-doctor-terms-content"<?php echo 'doctor' !== $dak_initial_role ? ' class="dak-hidden"' : ''; ?>>
				<?php echo $loader->get_template( 'auth/partials/doctor-terms-content.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own doctor-terms-content.php template, which escapes its own output. ?>
			</div>
			<div id="dak-patient-terms-content"<?php echo 'patient' !== $dak_initial_role ? ' class="dak-hidden"' : ''; ?>>
				<?php echo $loader->get_template( 'auth/partials/patient-terms-content.php' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered by our own patient-terms-content.php template, which escapes its own output. ?>
			</div>
		</div>

		<div class="dak-terms-modal-footer">
			<?php // Closing the terms never ticks the box — agreeing is a separate, deliberate action. ?>
			<button type="button" class="dak-button dak-button-secondary" data-dak-terms-close><?php esc_html_e( 'Close', 'doctor-ak-portal' ); ?></button>
		</div>
	</div>
</div>

<div class="dak-portal dak-modal dak-acct-modal" id="dak-register-success-modal" aria-hidden="true"
	data-patient-url="<?php echo esc_url( $login_url ? $login_url : $home_url ); ?>"
	data-doctor-url="<?php echo esc_url( $home_url ); ?>">
	<div class="dak-modal-overlay"></div>

	<div class="dak-modal-dialog dak-register-success-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-register-success-modal-title" aria-describedby="dak-register-success-modal-message" tabindex="-1">
		<div class="dak-register-success-icon" aria-hidden="true">
			<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="8"/><path d="M6.5 10.2l2.3 2.3 4.7-5"/></svg>
		</div>

		<h2 id="dak-register-success-modal-title"
			data-patient="<?php esc_attr_e( 'Account created', 'doctor-ak-portal' ); ?>"
			data-doctor="<?php esc_attr_e( 'Registration submitted', 'doctor-ak-portal' ); ?>"><?php esc_html_e( 'Account created', 'doctor-ak-portal' ); ?></h2>
		<p id="dak-register-success-modal-message"></p>
		<p class="dak-acct-hint" data-doctor-only hidden><?php esc_html_e( 'Your account is pending review. You can sign in once it has been approved.', 'doctor-ak-portal' ); ?></p>

		<button type="button" class="dak-button dak-button-primary dak-button-block" id="dak-register-success-ok"
			data-patient="<?php esc_attr_e( 'Go to sign in', 'doctor-ak-portal' ); ?>"
			data-doctor="<?php esc_attr_e( 'Back to home', 'doctor-ak-portal' ); ?>"><?php esc_html_e( 'OK', 'doctor-ak-portal' ); ?></button>
	</div>
</div>
