<?php
/**
 * Template: Full-screen Add/Edit Doctor or Patient form — replaces the
 * accounts table's content area (see Admin_Dashboard::user_form_screen_html())
 * when the URL has `?view=form`. Same fields the old modal had, submitted
 * to the same Admin_User_Handler::handle_save_user() endpoint, just
 * rendered as an in-page screen instead of a popup so there's more room
 * to work and the whole thing is a normal, linkable/back-button-friendly
 * page state rather than JS-only modal state.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string     $role            Roles::DOCTOR_ROLE or Roles::PATIENT_ROLE for the active section.
 * @var array      $specializations All specialization slug => label.
 * @var string     $section         'doctors' or 'patients'.
 * @var string     $list_url        Back-to-list URL (the table view of this same section).
 * @var array|null $editing_user    Row view-model (see Admin_Dashboard::row_data()) when editing, null when adding.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_is_doctor_form = \DoctorAKPortal\Includes\Roles::DOCTOR_ROLE === $role;
$dak_is_editing      = null !== $editing_user;
$dak_noun            = $dak_is_doctor_form ? __( 'Doctor', 'doctor-ak-portal' ) : __( 'Patient', 'doctor-ak-portal' );
?>
<div class="dak-dashboard-greeting dak-admin-users-header">
	<div>
		<a class="dak-back-link" href="<?php echo esc_url( $list_url ); ?>">
			&larr;
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: "Doctors" or "Patients". */
					__( 'Back to %s', 'doctor-ak-portal' ),
					'patients' === $section ? __( 'Patients', 'doctor-ak-portal' ) : __( 'Doctors', 'doctor-ak-portal' )
				)
			);
			?>
		</a>
		<h1>
			<?php
			echo esc_html(
				$dak_is_editing
					/* translators: %s: "Doctor" or "Patient". */
					? sprintf( __( 'Edit %s', 'doctor-ak-portal' ), $dak_noun )
					/* translators: %s: "Doctor" or "Patient". */
					: sprintf( __( 'Add %s', 'doctor-ak-portal' ), $dak_noun )
			);
			?>
		</h1>
	</div>
</div>

<div class="dak-alert dak-alert-error dak-hidden" id="dak-admin-user-modal-general-error" role="alert"></div>

<section class="dak-dashboard-card dak-admin-user-form-card">
	<form id="dak-admin-user-form" novalidate data-list-url="<?php echo esc_url( $list_url ); ?>">
		<input type="hidden" name="user_id" id="dak-admin-user-id" value="<?php echo esc_attr( $dak_is_editing ? $editing_user['id'] : 0 ); ?>">
		<input type="hidden" name="role" id="dak-admin-user-role" value="<?php echo esc_attr( $role ); ?>">
		<input type="hidden" name="profile_picture_id" id="dak-admin-user-picture-id" value="0">

		<div class="dak-field">
			<span class="dak-field-label"><?php esc_html_e( 'Profile Picture', 'doctor-ak-portal' ); ?></span>
			<div class="dak-upload-control">
				<div class="dak-upload-preview" id="dak-admin-user-picture-preview">
					<?php if ( $dak_is_editing && '' !== $editing_user['avatar_url'] ) : ?>
						<img src="<?php echo esc_url( $editing_user['avatar_url'] ); ?>" alt="">
					<?php else : ?>
						<span class="dak-upload-placeholder"><?php esc_html_e( 'No photo selected', 'doctor-ak-portal' ); ?></span>
					<?php endif; ?>
				</div>
				<label class="dak-button dak-button-secondary" for="dak-admin-user-picture-input"><?php esc_html_e( 'Upload Photo', 'doctor-ak-portal' ); ?></label>
				<input type="file" id="dak-admin-user-picture-input" accept="image/jpeg,image/png,image/webp" class="dak-visually-hidden">
				<span class="dak-upload-status" id="dak-admin-user-picture-status"></span>
			</div>
		</div>

		<div class="dak-field-row">
			<div class="dak-field">
				<label for="dak-admin-user-first-name"><?php esc_html_e( 'First Name', 'doctor-ak-portal' ); ?></label>
				<input type="text" id="dak-admin-user-first-name" name="first_name" value="<?php echo esc_attr( $dak_is_editing ? $editing_user['first_name'] : '' ); ?>" required>
				<span class="dak-field-error" data-field="first_name"></span>
			</div>
			<div class="dak-field">
				<label for="dak-admin-user-last-name"><?php esc_html_e( 'Last Name', 'doctor-ak-portal' ); ?></label>
				<input type="text" id="dak-admin-user-last-name" name="last_name" value="<?php echo esc_attr( $dak_is_editing ? $editing_user['last_name'] : '' ); ?>" required>
				<span class="dak-field-error" data-field="last_name"></span>
			</div>
		</div>

		<div class="dak-field">
			<label for="dak-admin-user-email"><?php esc_html_e( 'Email Address', 'doctor-ak-portal' ); ?></label>
			<input type="email" id="dak-admin-user-email" name="email" value="<?php echo esc_attr( $dak_is_editing ? $editing_user['email'] : '' ); ?>" required autocomplete="off">
			<span class="dak-field-error" data-field="email"></span>
		</div>

		<?php if ( $dak_is_doctor_form ) : ?>

			<div class="dak-field-row">
				<div class="dak-field">
					<label for="dak-admin-user-qualification"><?php esc_html_e( 'Qualification', 'doctor-ak-portal' ); ?></label>
					<input type="text" id="dak-admin-user-qualification" name="qualification" value="<?php echo esc_attr( $dak_is_editing ? $editing_user['qualification'] : '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. MBBS, FCPS (Gastroenterology)', 'doctor-ak-portal' ); ?>" required>
					<span class="dak-field-error" data-field="qualification"></span>
				</div>
				<div class="dak-field">
					<label for="dak-admin-user-years-experience"><?php esc_html_e( 'Years of Experience', 'doctor-ak-portal' ); ?></label>
					<input type="number" min="0" max="80" id="dak-admin-user-years-experience" name="years_experience" value="<?php echo esc_attr( $dak_is_editing ? $editing_user['years_experience'] : '' ); ?>" required>
					<span class="dak-field-error" data-field="years_experience"></span>
				</div>
			</div>

			<div class="dak-field">
				<label for="dak-admin-user-specializations"><?php esc_html_e( 'Specialization', 'doctor-ak-portal' ); ?></label>
				<select id="dak-admin-user-specializations" name="specializations[]" multiple required>
					<?php foreach ( $specializations as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $dak_is_editing && in_array( $slug, $editing_user['specializations'], true ), true ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
					<?php if ( $dak_is_editing ) : ?>
						<?php foreach ( $editing_user['specializations'] as $dak_existing_value ) : ?>
							<?php if ( ! isset( $specializations[ $dak_existing_value ] ) ) : ?>
								<option value="<?php echo esc_attr( $dak_existing_value ); ?>" selected><?php echo esc_html( $dak_existing_value ); ?></option>
							<?php endif; ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
				<p class="dak-field-hint"><?php esc_html_e( "Not in the list? Type it and press Tab or Enter to add it as a custom specialization.", 'doctor-ak-portal' ); ?></p>
				<span class="dak-field-error" data-field="specializations"></span>
			</div>

			<div class="dak-field">
				<label for="dak-admin-user-short-description"><?php esc_html_e( 'Short Description', 'doctor-ak-portal' ); ?></label>
				<input type="text" id="dak-admin-user-short-description" name="short_description" value="<?php echo esc_attr( $dak_is_editing ? $editing_user['short_description'] : '' ); ?>" maxlength="160" placeholder="<?php esc_attr_e( 'e.g. Compassionate primary care with 12+ years of experience', 'doctor-ak-portal' ); ?>">
				<p class="dak-field-hint"><?php esc_html_e( 'A one-line tagline shown on the doctor\'s public profile (up to 160 characters).', 'doctor-ak-portal' ); ?></p>
				<span class="dak-field-error" data-field="short_description"></span>
			</div>

			<div class="dak-field">
				<label for="dak-admin-user-expertise"><?php esc_html_e( 'Other Expertise', 'doctor-ak-portal' ); ?></label>
				<textarea id="dak-admin-user-expertise" name="expertise" rows="3" placeholder="<?php esc_attr_e( 'Any additional skills, procedures, or areas of interest not covered above (optional).', 'doctor-ak-portal' ); ?>"><?php echo esc_textarea( $dak_is_editing ? $editing_user['expertise'] : '' ); ?></textarea>
				<span class="dak-field-error" data-field="expertise"></span>
			</div>

			<div class="dak-field">
				<span class="dak-field-label"><?php esc_html_e( 'Awards & Recognition', 'doctor-ak-portal' ); ?></span>
				<div class="dak-awards-editor" data-awards-editor>
					<div class="dak-awards-rows" data-awards-rows>
						<?php foreach ( ( $dak_is_editing ? $editing_user['awards'] : array() ) as $award ) : ?>
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

			<div class="dak-field">
				<span class="dak-field-label"><?php esc_html_e( 'Add a Clinic (optional)', 'doctor-ak-portal' ); ?></span>
				<p class="dak-field-hint"><?php esc_html_e( 'Quickly add one clinic location now — weekly session hours and additional clinics can be set later from the Doctor Sessions tab.', 'doctor-ak-portal' ); ?></p>
				<div class="dak-field">
					<label for="dak-admin-user-clinic-name"><?php esc_html_e( 'Clinic Name', 'doctor-ak-portal' ); ?></label>
					<input type="text" id="dak-admin-user-clinic-name" name="clinic_name">
				</div>
				<div class="dak-field-row">
					<div class="dak-field">
						<label for="dak-admin-user-clinic-address"><?php esc_html_e( 'Address', 'doctor-ak-portal' ); ?></label>
						<input type="text" id="dak-admin-user-clinic-address" name="clinic_address">
						<span class="dak-field-error" data-field="clinic_address"></span>
					</div>
					<div class="dak-field">
						<label for="dak-admin-user-clinic-phone"><?php esc_html_e( 'Phone', 'doctor-ak-portal' ); ?></label>
						<input type="text" id="dak-admin-user-clinic-phone" name="clinic_phone">
					</div>
				</div>
			</div>

		<?php endif; ?>

		<div class="dak-field" id="dak-admin-user-password-field">
			<label for="dak-admin-user-password"><?php echo esc_html( $dak_is_editing ? __( 'New Password', 'doctor-ak-portal' ) : __( 'Password', 'doctor-ak-portal' ) ); ?></label>
			<input type="password" id="dak-admin-user-password" name="password" autocomplete="new-password" <?php echo $dak_is_editing ? '' : 'required'; ?>>
			<span class="dak-field-hint"><?php echo $dak_is_editing ? esc_html__( 'Leave blank to keep the current password.', 'doctor-ak-portal' ) : ''; ?></span>
			<span class="dak-field-error" data-field="password"></span>
		</div>

		<div class="dak-admin-user-form-actions">
			<a class="dak-button dak-button-secondary" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Cancel', 'doctor-ak-portal' ); ?></a>
			<button type="submit" class="dak-button dak-button-primary" id="dak-admin-user-submit">
				<span class="dak-button-label"><?php esc_html_e( 'Save', 'doctor-ak-portal' ); ?></span>
			</button>
		</div>
	</form>
</section>
