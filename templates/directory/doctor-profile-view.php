<?php
/**
 * Template: Public doctor profile for the [doctor_profile_view] shortcode.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array|null $doctor {
 *     Null if no valid doctor_id was given.
 *
 *     @type int      $id                    Doctor's user ID.
 *     @type string   $name                  Display name.
 *     @type string   $avatar_url            Photo (or fallback avatar) URL.
 *     @type string[] $specialization_labels Selected specialization labels.
 *     @type string[] $keywords              Procedure/condition search keywords (see Doctor_Keywords), or an empty array.
 *     @type array    $clinics               Doctor's clinics (physical + video), each with added 'hours_label'/'fee_label'. Used for the phone number only — the visible picker is built from $clinics_with_services below.
 *     @type int      $physical_clinic_count Count of $clinics filtered to Clinics::TYPE_PHYSICAL only — never includes the video "clinic" row (see Doctor_Profile_View::render()).
 *     @type array    $clinics_with_services Doctor's physical clinics, each with the services actually offered there and that clinic's own exact fee — see Doctor_Profile_View::clinics_with_services() — { clinic_id, name, meta, hours_label, services: [ { id, name, charge, price_label } ] }.
 *     @type string   $video_fee_label       Video consultation's fee label ("PKR X" or "Fee not set"), or '' if video isn't offered at all.
 *     @type string   $years_experience      Years of experience.
 *     @type string   $qualification         Qualification(s), e.g. "MBBS, FCPS".
 *     @type string   $short_description     One-line profile tagline, or ''.
 *     @type string   $expertise             Other-expertise text — may contain rich-text HTML (bold/italic/lists/links) from the doctor's/admin's formatting toolbar, or ''.
 *     @type array    $awards                Awards list, see Doctor_Awards::get_for_doctor() — { title, year }.
 *     @type bool     $video_consultation    Whether video consultations are offered.
 *     @type string   $phone                 First clinic with a phone number on file, or ''.
 * }
 * @var string $directory_url         "All Doctors" breadcrumb link.
 * @var array  $starting_fee_summary  Doctor_Profile_View::starting_fee_summary() — { state: 'none'|'unset'|'paid'|'range', label }, for the sidebar's pre-selection teaser.
 * @var string $cancellation_note     Doctor's real cancellation policy text.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_profile_view_icons = array(
	'pin'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'person'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M4 17c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/></svg>',
	'clock'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M10 6.2V10l2.8 1.8"/></svg>',
	'badge'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2.5l1.7 1.9 2.5-.4.4 2.5 1.9 1.7-1.9 1.7-.4 2.5-2.5-.4L10 13.9l-1.7-1.9-2.5.4-.4-2.5-1.9-1.7 1.9-1.7.4-2.5 2.5.4z"/><path d="M8 10l1.4 1.4L12.5 8"/></svg>',
	'phone'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3.5h2.3l1 3.3-1.6 1.4a9 9 0 0 0 4.1 4.1l1.4-1.6 3.3 1v2.3c0 .8-.7 1.4-1.5 1.3C8.7 15 5 11.3 4.2 6c-.1-.8.5-1.5 1.3-1.5z"/></svg>',
	'video'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5" width="10" height="10" rx="1.5"/><path d="M12.5 8.5l5-2.5v8l-5-2.5"/></svg>',
	'award'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7.5" r="4.5"/><path d="M7.3 11.4L6 17.5l4-2 4 2-1.3-6.1"/></svg>',
	'graduation' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 7.5 10 4l7.5 3.5-7.5 3.5-7.5-3.5z"/><path d="M5.5 9.2v3.6c0 1.2 2 2.2 4.5 2.2s4.5-1 4.5-2.2V9.2"/></svg>',
);

/**
 * Truncates text to roughly $max_length characters without cutting a word
 * in half, for the header's short intro — the full, untruncated text still
 * shows in the About section below. Returns the original string unchanged
 * (and $was_truncated left false) when it's already short enough.
 *
 * @param string $text          Source text.
 * @param int    $max_length    Soft character budget.
 * @param bool   $was_truncated Set to true by reference if truncation happened.
 * @return string
 */
if ( ! function_exists( 'dak_profile_view_truncate' ) ) {
	function dak_profile_view_truncate( $text, $max_length, &$was_truncated ) {
		$was_truncated = false;

		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $text ) <= $max_length : strlen( $text ) <= $max_length ) {
			return $text;
		}

		$was_truncated = true;
		$truncated     = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max_length ) : substr( $text, 0, $max_length );
		$last_space    = strrpos( $truncated, ' ' );

		if ( false !== $last_space && $last_space > 0 ) {
			$truncated = substr( $truncated, 0, $last_space );
		}

		return rtrim( $truncated, " \t\n\r\0\x0B.,;:" ) . '…';
	}
}
?>
<div class="dak-portal dak-directory dak-profile-view">
	<?php if ( ! $doctor ) : ?>
		<div class="dak-profile-unavailable">
			<span class="dak-profile-unavailable-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<h1><?php esc_html_e( 'This doctor profile isn\'t available', 'doctor-ak-portal' ); ?></h1>
			<p><?php esc_html_e( 'The link you followed may be out of date, or this profile may no longer be published.', 'doctor-ak-portal' ); ?></p>
			<?php if ( $directory_url ) : ?>
				<a class="dak-button dak-button-primary" href="<?php echo esc_url( $directory_url ); ?>"><?php esc_html_e( 'Browse All Doctors', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<?php
		$dak_first_specialization = ! empty( $doctor['specialization_labels'] ) ? $doctor['specialization_labels'][0] : '';
		$dak_has_clinics_fees      = ! empty( $doctor['clinics_with_services'] ) || $doctor['video_consultation'];
		$dak_has_experience_section = '' !== $doctor['qualification'] || $doctor['years_experience'] || ! empty( $doctor['specialization_labels'] ) || ! empty( $doctor['awards'] ) || '' !== $doctor['expertise'] || ! empty( $doctor['keywords'] );
		$dak_has_reviews_content    = true; // Reviews section always has at least the "no reviews yet" / review-form state.
		$dak_was_truncated          = false;
		$dak_short_intro            = '' !== $doctor['short_description'] ? dak_profile_view_truncate( $doctor['short_description'], 160, $dak_was_truncated ) : '';
		?>

		<?php if ( $directory_url ) : ?>
			<nav class="dak-profile-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'doctor-ak-portal' ); ?>">
				<a href="<?php echo esc_url( $directory_url ); ?>"><?php esc_html_e( 'Doctors', 'doctor-ak-portal' ); ?></a>
				<?php if ( '' !== $dak_first_specialization ) : ?>
					<span aria-hidden="true">›</span>
					<span><?php echo esc_html( $dak_first_specialization ); ?></span>
				<?php endif; ?>
				<span aria-hidden="true">›</span>
				<span><?php echo esc_html( sprintf( 'Dr. %s', $doctor['name'] ) ); ?></span>
			</nav>
		<?php endif; ?>

		<div class="dak-profile-header-card" id="dak-profile-overview">
			<span class="dak-avatar dak-avatar-lg">
				<?php if ( $doctor['avatar_url'] ) : ?>
					<img src="<?php echo esc_url( $doctor['avatar_url'] ); ?>" alt="">
				<?php else : ?>
					<?php echo $dak_profile_view_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</span>

			<div class="dak-profile-header-main">
				<h1><?php echo esc_html( sprintf( 'Dr. %s', $doctor['name'] ) ); ?></h1>

				<?php if ( '' !== $doctor['qualification'] ) : ?>
					<p class="dak-profile-qualification"><?php echo esc_html( $doctor['qualification'] ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $doctor['specialization_labels'] ) ) : ?>
					<div class="dak-specialty-tags dak-doctor-card-specialties">
						<?php foreach ( $doctor['specialization_labels'] as $label ) : ?>
							<span class="dak-specialty-tag"><?php echo esc_html( $label ); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<div class="dak-profile-stats">
					<?php if ( $doctor['years_experience'] ) : ?>
						<span class="dak-profile-stat">
							<span class="dak-profile-stat-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['clock']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<strong><?php echo esc_html( $doctor['years_experience'] ); ?> <?php esc_html_e( 'yrs', 'doctor-ak-portal' ); ?></strong>
							<span><?php esc_html_e( 'Experience', 'doctor-ak-portal' ); ?></span>
						</span>
					<?php endif; ?>

					<?php if ( $doctor['physical_clinic_count'] > 0 ) : ?>
						<a class="dak-profile-stat dak-profile-stat-link" href="#dak-profile-clinics-fees">
							<span class="dak-profile-stat-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<strong><?php echo esc_html( $doctor['physical_clinic_count'] ); ?></strong>
							<span><?php echo esc_html( _n( 'Location', 'Locations', $doctor['physical_clinic_count'], 'doctor-ak-portal' ) ); ?></span>
						</a>
					<?php endif; ?>

					<?php if ( $doctor['video_consultation'] ) : ?>
						<a class="dak-profile-stat dak-profile-stat-link" href="#dak-profile-clinics-fees">
							<span class="dak-profile-stat-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['video']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<strong><?php esc_html_e( 'Online', 'doctor-ak-portal' ); ?></strong>
							<span><?php esc_html_e( 'Video Consults', 'doctor-ak-portal' ); ?></span>
						</a>
					<?php endif; ?>
				</div>

				<?php if ( '' !== $dak_short_intro ) : ?>
					<p class="dak-profile-tagline"><?php echo esc_html( $dak_short_intro ); ?></p>
				<?php endif; ?>

				<div class="dak-profile-header-actions">
					<button
						type="button"
						class="dak-button dak-button-primary dak-profile-cta"
						id="dak-profile-header-cta"
						data-doctor-id="<?php echo esc_attr( $doctor['id'] ); ?>"
						data-doctor-name="<?php echo esc_attr( sprintf( 'Dr. %s', $doctor['name'] ) ); ?>"
					>
						<?php echo $dak_profile_view_icons['badge']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="dak-profile-cta-label"><?php esc_html_e( 'Choose consultation', 'doctor-ak-portal' ); ?></span>
					</button>

					<?php if ( '' !== $doctor['phone'] ) : ?>
						<a class="dak-button dak-button-secondary" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $doctor['phone'] ) ); ?>">
							<?php echo $dak_profile_view_icons['phone']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php esc_html_e( 'Call Clinic', 'doctor-ak-portal' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<nav class="dak-profile-section-nav" aria-label="<?php esc_attr_e( 'Profile sections', 'doctor-ak-portal' ); ?>">
			<?php if ( '' !== $doctor['short_description'] || '' !== $doctor['expertise'] ) : ?>
				<a href="#dak-profile-overview"><?php esc_html_e( 'Overview', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
			<?php if ( $dak_has_clinics_fees ) : ?>
				<a href="#dak-profile-clinics-fees"><?php esc_html_e( 'Clinics & Fees', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
			<?php if ( $dak_has_experience_section ) : ?>
				<a href="#dak-profile-experience"><?php esc_html_e( 'Experience & Qualifications', 'doctor-ak-portal' ); ?></a>
			<?php endif; ?>
			<a href="#dak-profile-reviews"><?php esc_html_e( 'Reviews', 'doctor-ak-portal' ); ?></a>
		</nav>

		<div class="dak-profile-layout">
			<div class="dak-profile-main">
				<?php if ( '' !== $doctor['short_description'] ) : ?>
					<div class="dak-profile-card">
						<h2><?php esc_html_e( 'About', 'doctor-ak-portal' ); ?></h2>
						<p class="dak-profile-about-text"><?php echo esc_html( $doctor['short_description'] ); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( $dak_has_clinics_fees ) : ?>
					<div class="dak-profile-card" id="dak-profile-clinics-fees">
						<h2>
							<span class="dak-profile-card-title-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['badge']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php esc_html_e( 'Clinics & Fees', 'doctor-ak-portal' ); ?>
						</h2>
						<p class="dak-field-hint"><?php esc_html_e( 'Choose how you\'d like to consult, then a clinic and service, to see the exact fee.', 'doctor-ak-portal' ); ?></p>

						<?php
						$dak_both_types_available = $doctor['video_consultation'] && ! empty( $doctor['clinics_with_services'] );
						$dak_single_clinic         = 1 === count( $doctor['clinics_with_services'] ) ? $doctor['clinics_with_services'][0] : null;
						?>
						<div
							class="dak-profile-picker"
							id="dak-profile-picker"
							data-doctor-id="<?php echo esc_attr( $doctor['id'] ); ?>"
							data-doctor-name="<?php echo esc_attr( sprintf( 'Dr. %s', $doctor['name'] ) ); ?>"
							<?php if ( ! $dak_both_types_available ) : ?>
								data-fixed-type="<?php echo esc_attr( $doctor['video_consultation'] ? 'video' : 'clinic' ); ?>"
							<?php endif; ?>
							<?php if ( $dak_single_clinic ) : ?>
								data-fixed-clinic-id="<?php echo esc_attr( $dak_single_clinic['clinic_id'] ); ?>"
							<?php endif; ?>
						>
							<?php if ( $dak_both_types_available ) : ?>
								<fieldset class="dak-profile-picker-group" data-picker-group="visit-type">
									<legend><?php esc_html_e( 'Visit type', 'doctor-ak-portal' ); ?></legend>

									<label class="dak-profile-clinic-row dak-profile-radio-row">
										<input type="radio" name="dak-visit-type" value="clinic" class="dak-profile-radio-input">
										<span class="dak-profile-clinic-main">
											<span class="dak-profile-clinic-radio" aria-hidden="true"></span>
											<span class="dak-profile-clinic-info">
												<strong><?php esc_html_e( 'Clinic Visit', 'doctor-ak-portal' ); ?></strong>
												<span class="dak-profile-clinic-meta">
													<span class="dak-location-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
													<?php echo esc_html( sprintf( _n( '%d location', '%d locations', $doctor['physical_clinic_count'], 'doctor-ak-portal' ), $doctor['physical_clinic_count'] ) ); ?>
												</span>
											</span>
										</span>
									</label>

									<label class="dak-profile-clinic-row dak-profile-radio-row">
										<input type="radio" name="dak-visit-type" value="video" class="dak-profile-radio-input">
										<span class="dak-profile-clinic-main">
											<span class="dak-profile-clinic-radio" aria-hidden="true"></span>
											<span class="dak-profile-clinic-info">
												<strong><?php esc_html_e( 'Online Video', 'doctor-ak-portal' ); ?></strong>
												<span class="dak-profile-clinic-meta">
													<span class="dak-location-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['video']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
													<?php esc_html_e( 'Consult from anywhere', 'doctor-ak-portal' ); ?>
												</span>
											</span>
										</span>
										<span class="dak-profile-clinic-fee">
											<span><?php esc_html_e( 'Fee', 'doctor-ak-portal' ); ?></span>
											<strong><?php echo esc_html( $doctor['video_fee_label'] ); ?></strong>
										</span>
									</label>
								</fieldset>
							<?php endif; ?>

							<?php if ( ! empty( $doctor['clinics_with_services'] ) ) : ?>
								<?php if ( $dak_single_clinic ) : ?>
									<div class="dak-profile-clinic-static" data-picker-static="clinic">
										<span class="dak-profile-clinic-main">
											<span class="dak-profile-clinic-info">
												<strong><?php echo esc_html( $dak_single_clinic['name'] ); ?></strong>
												<?php if ( '' !== $dak_single_clinic['meta'] ) : ?>
													<span class="dak-profile-clinic-meta">
														<span class="dak-location-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
														<?php echo esc_html( $dak_single_clinic['meta'] ); ?>
													</span>
												<?php endif; ?>
												<?php if ( '' !== $dak_single_clinic['hours_label'] ) : ?>
													<span class="dak-profile-clinic-meta">
														<span class="dak-location-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['clock']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
														<?php echo esc_html( $dak_single_clinic['hours_label'] ); ?>
													</span>
												<?php endif; ?>
											</span>
										</span>
									</div>
								<?php else : ?>
									<fieldset class="dak-profile-picker-group" data-picker-group="clinic">
										<legend><?php esc_html_e( 'Choose a clinic', 'doctor-ak-portal' ); ?></legend>

										<?php foreach ( $doctor['clinics_with_services'] as $dak_clinic ) : ?>
											<label class="dak-profile-clinic-row dak-profile-radio-row">
												<input type="radio" name="dak-clinic-choice" value="<?php echo esc_attr( $dak_clinic['clinic_id'] ); ?>" class="dak-profile-radio-input">
												<span class="dak-profile-clinic-main">
													<span class="dak-profile-clinic-radio" aria-hidden="true"></span>
													<span class="dak-profile-clinic-info">
														<strong><?php echo esc_html( $dak_clinic['name'] ); ?></strong>
														<?php if ( '' !== $dak_clinic['meta'] ) : ?>
															<span class="dak-profile-clinic-meta">
																<span class="dak-location-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
																<?php echo esc_html( $dak_clinic['meta'] ); ?>
															</span>
														<?php endif; ?>
														<?php if ( '' !== $dak_clinic['hours_label'] ) : ?>
															<span class="dak-profile-clinic-meta">
																<span class="dak-location-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['clock']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
																<?php echo esc_html( $dak_clinic['hours_label'] ); ?>
															</span>
														<?php endif; ?>
													</span>
												</span>
											</label>
										<?php endforeach; ?>
									</fieldset>
								<?php endif; ?>

								<?php foreach ( $doctor['clinics_with_services'] as $dak_clinic ) : ?>
									<fieldset class="dak-profile-picker-group dak-profile-service-group" data-picker-group="service" data-clinic-id="<?php echo esc_attr( $dak_clinic['clinic_id'] ); ?>" hidden>
										<legend><?php echo esc_html( sprintf( /* translators: %s: clinic name. */ __( 'Services at %s', 'doctor-ak-portal' ), $dak_clinic['name'] ) ); ?></legend>

										<?php if ( count( $dak_clinic['services'] ) > 6 ) : ?>
											<div class="dak-profile-service-search">
												<input type="search" class="dak-profile-service-search-input" data-service-search placeholder="<?php esc_attr_e( 'Search services…', 'doctor-ak-portal' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: clinic name. */ __( 'Search services at %s', 'doctor-ak-portal' ), $dak_clinic['name'] ) ); ?>">
											</div>
										<?php endif; ?>

										<div class="dak-profile-service-options" data-service-options>
											<?php foreach ( $dak_clinic['services'] as $dak_service_index => $dak_service ) : ?>
												<label class="dak-profile-clinic-row dak-profile-radio-row dak-profile-service-option<?php echo $dak_service_index >= 6 ? ' dak-hidden' : ''; ?>" data-service-name="<?php echo esc_attr( mb_strtolower( $dak_service['name'] ) ); ?>">
													<input type="radio" name="dak-service-choice-<?php echo esc_attr( $dak_clinic['clinic_id'] ); ?>" value="<?php echo esc_attr( $dak_service['id'] ); ?>" class="dak-profile-radio-input">
													<span class="dak-profile-clinic-main">
														<span class="dak-profile-clinic-radio" aria-hidden="true"></span>
														<span class="dak-profile-clinic-info">
															<strong><?php echo esc_html( $dak_service['name'] ); ?></strong>
														</span>
													</span>
													<span class="dak-profile-clinic-fee">
														<span><?php esc_html_e( 'Fee', 'doctor-ak-portal' ); ?></span>
														<strong><?php echo esc_html( $dak_service['price_label'] ); ?></strong>
													</span>
												</label>
											<?php endforeach; ?>
										</div>

										<p class="dak-profile-service-no-results dak-hidden" data-service-no-results><?php esc_html_e( 'No services match your search.', 'doctor-ak-portal' ); ?></p>

										<?php if ( count( $dak_clinic['services'] ) > 6 ) : ?>
											<button type="button" class="dak-button dak-button-secondary dak-button-sm" data-show-all-services aria-expanded="false">
												<?php echo esc_html( sprintf( /* translators: %d: number of services. */ __( 'Show all %d services', 'doctor-ak-portal' ), count( $dak_clinic['services'] ) ) ); ?>
											</button>
										<?php endif; ?>
									</fieldset>
								<?php endforeach; ?>
							<?php endif; ?>

							<?php if ( $doctor['video_consultation'] ) : ?>
								<div class="dak-profile-clinic-static" data-picker-static="video" hidden>
									<span class="dak-profile-clinic-main">
										<span class="dak-profile-clinic-info">
											<strong><?php esc_html_e( 'Online Video Consultation', 'doctor-ak-portal' ); ?></strong>
											<span class="dak-profile-clinic-meta">
												<span class="dak-location-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['video']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
												<?php esc_html_e( 'Consult from anywhere', 'doctor-ak-portal' ); ?>
											</span>
										</span>
									</span>
									<span class="dak-profile-clinic-fee">
										<span><?php esc_html_e( 'Fee', 'doctor-ak-portal' ); ?></span>
										<strong><?php echo esc_html( $doctor['video_fee_label'] ); ?></strong>
									</span>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( $dak_has_experience_section ) : ?>
					<div id="dak-profile-experience">
						<?php if ( '' !== $doctor['qualification'] || $doctor['years_experience'] || ! empty( $doctor['specialization_labels'] ) ) : ?>
							<div class="dak-profile-card">
								<h2>
									<span class="dak-profile-card-title-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['graduation']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<?php esc_html_e( 'Experience & Qualifications', 'doctor-ak-portal' ); ?>
								</h2>
								<ul class="dak-profile-qualifications-list">
									<?php if ( '' !== $doctor['qualification'] ) : ?>
										<li><?php echo esc_html( $doctor['qualification'] ); ?></li>
									<?php endif; ?>
									<?php if ( $doctor['years_experience'] ) : ?>
										<li><?php echo esc_html( sprintf( /* translators: %s: number of years. */ __( '%s years of clinical experience', 'doctor-ak-portal' ), $doctor['years_experience'] ) ); ?></li>
									<?php endif; ?>
									<?php if ( ! empty( $doctor['specialization_labels'] ) ) : ?>
										<li><?php echo esc_html( implode( ', ', $doctor['specialization_labels'] ) ); ?></li>
									<?php endif; ?>
								</ul>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $doctor['keywords'] ) ) : ?>
							<?php
							$dak_keyword_preview_count = 10;
							$dak_keywords_overflow      = count( $doctor['keywords'] ) > $dak_keyword_preview_count;
							?>
							<div class="dak-profile-card">
								<h2><?php esc_html_e( 'Procedures & Conditions Treated', 'doctor-ak-portal' ); ?></h2>
								<div class="dak-specialty-tags" id="dak-profile-keywords-list">
									<?php foreach ( $doctor['keywords'] as $dak_keyword_index => $dak_keyword ) : ?>
										<span class="dak-specialty-tag<?php echo $dak_keyword_index >= $dak_keyword_preview_count ? ' dak-hidden' : ''; ?>"><?php echo esc_html( $dak_keyword ); ?></span>
									<?php endforeach; ?>
								</div>
								<?php if ( $dak_keywords_overflow ) : ?>
									<button type="button" class="dak-button dak-button-secondary dak-button-sm" data-show-all-tags="dak-profile-keywords-list" aria-expanded="false" aria-controls="dak-profile-keywords-list">
										<?php echo esc_html( sprintf( /* translators: %d: number of items. */ __( 'Show all %d', 'doctor-ak-portal' ), count( $doctor['keywords'] ) ) ); ?>
									</button>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php if ( '' !== $doctor['expertise'] ) : ?>
							<div class="dak-profile-card">
								<h2><?php esc_html_e( 'Other Expertise', 'doctor-ak-portal' ); ?></h2>
								<div class="dak-profile-expertise dak-rich-text-content"><?php echo wp_kses_post( $doctor['expertise'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() output. ?></div>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $doctor['awards'] ) ) : ?>
							<?php
							// Display-only grouping — the data model (Doctor_Awards) has no
							// field distinguishing an award from a professional membership,
							// so this is a title heuristic, not authoritative. Flagged in the
							// delivery notes for the clinic owner to confirm per entry.
							$dak_membership_pattern = '/\b(member|fellow|society|college of|association)\b/i';
							$dak_awards_list        = array();
							$dak_memberships_list   = array();

							foreach ( $doctor['awards'] as $dak_award ) {
								if ( preg_match( $dak_membership_pattern, $dak_award['title'] ) ) {
									$dak_memberships_list[] = $dak_award;
								} else {
									$dak_awards_list[] = $dak_award;
								}
							}
							?>
							<?php if ( ! empty( $dak_awards_list ) ) : ?>
								<div class="dak-profile-card">
									<h2>
										<span class="dak-profile-card-title-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['award']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<?php esc_html_e( 'Awards & Recognition', 'doctor-ak-portal' ); ?>
									</h2>
									<ul class="dak-profile-awards">
										<?php foreach ( $dak_awards_list as $dak_award ) : ?>
											<li>
												<span class="dak-profile-award-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['award']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
												<span class="dak-profile-award-title"><?php echo esc_html( $dak_award['title'] ); ?></span>
												<?php if ( '' !== $dak_award['year'] ) : ?>
													<span class="dak-profile-award-year"><?php echo esc_html( $dak_award['year'] ); ?></span>
												<?php endif; ?>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endif; ?>

							<?php if ( ! empty( $dak_memberships_list ) ) : ?>
								<div class="dak-profile-card">
									<h2><?php esc_html_e( 'Professional Memberships', 'doctor-ak-portal' ); ?></h2>
									<ul class="dak-profile-awards">
										<?php foreach ( $dak_memberships_list as $dak_membership ) : ?>
											<li>
												<span class="dak-profile-award-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['badge']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
												<span class="dak-profile-award-title"><?php echo esc_html( $dak_membership['title'] ); ?></span>
												<?php if ( '' !== $dak_membership['year'] ) : ?>
													<span class="dak-profile-award-year"><?php echo esc_html( $dak_membership['year'] ); ?></span>
												<?php endif; ?>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php
				$dak_rs    = $review_summary;
				$dak_my    = $my_review;
				$dak_stars = function ( $n ) {
					$out = '';
					for ( $i = 1; $i <= 5; $i++ ) {
						$out .= '<span class="dak-star' . ( $i <= $n ? ' is-on' : '' ) . '" aria-hidden="true">&#9733;</span>';
					}
					return $out;
				};
				?>
				<div class="dak-profile-card dak-reviews dak-profile-card-secondary" id="dak-profile-reviews">
					<h2><?php esc_html_e( 'Patient Reviews', 'doctor-ak-portal' ); ?></h2>

					<?php if ( $dak_rs['count'] > 0 ) : ?>
						<div class="dak-reviews-summary">
							<div class="dak-reviews-score">
								<strong><?php echo esc_html( number_format_i18n( $dak_rs['average'], 1 ) ); ?></strong>
								<span class="dak-reviews-stars"><?php echo $dak_stars( (int) round( $dak_rs['average'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
								<small><?php echo esc_html( sprintf( _n( '%d review', '%d reviews', $dak_rs['count'], 'doctor-ak-portal' ), $dak_rs['count'] ) ); ?></small>
							</div>
							<ul class="dak-reviews-bars">
								<?php foreach ( $dak_rs['stars'] as $dak_n => $dak_c ) : ?>
									<li>
										<span><?php echo esc_html( $dak_n ); ?>&#9733;</span>
										<span class="dak-reviews-bar"><i style="width:<?php echo esc_attr( round( $dak_c / $dak_rs['count'] * 100 ) ); ?>%"></i></span>
										<span><?php echo esc_html( $dak_c ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php else : ?>
						<p class="dak-reviews-note"><?php esc_html_e( 'No patient reviews yet.', 'doctor-ak-portal' ); ?></p>
					<?php endif; ?>

					<?php if ( $can_review ) : ?>
						<form class="dak-review-form" id="dak-review-form" data-doctor-id="<?php echo esc_attr( $doctor['id'] ); ?>" data-rating="<?php echo esc_attr( $dak_my ? $dak_my['rating'] : 0 ); ?>">
							<strong><?php echo esc_html( $dak_my ? __( 'Update your review', 'doctor-ak-portal' ) : __( 'Rate your experience', 'doctor-ak-portal' ) ); ?></strong>
							<div class="dak-review-picker" role="radiogroup" aria-label="<?php esc_attr_e( 'Rating', 'doctor-ak-portal' ); ?>">
								<?php for ( $dak_i = 1; $dak_i <= 5; $dak_i++ ) : ?>
									<button type="button" class="dak-review-star<?php echo ( $dak_my && $dak_my['rating'] >= $dak_i ) ? ' is-on' : ''; ?>" data-value="<?php echo esc_attr( $dak_i ); ?>" aria-label="<?php echo esc_attr( sprintf( _n( '%d star', '%d stars', $dak_i, 'doctor-ak-portal' ), $dak_i ) ); ?>">&#9733;</button>
								<?php endfor; ?>
							</div>
							<textarea name="comment" rows="3" maxlength="1000" placeholder="<?php esc_attr_e( 'Share details of your visit (optional)', 'doctor-ak-portal' ); ?>"><?php echo esc_textarea( $dak_my ? $dak_my['comment'] : '' ); ?></textarea>
							<div class="dak-alert dak-alert-error dak-hidden" id="dak-review-error" role="alert"></div>
							<button type="submit" class="dak-button dak-button-primary"><?php esc_html_e( 'Submit review', 'doctor-ak-portal' ); ?></button>
						</form>
					<?php elseif ( ! $is_logged_in ) : ?>
						<p class="dak-reviews-note"><?php esc_html_e( 'Log in as a patient to review this doctor after your appointment.', 'doctor-ak-portal' ); ?></p>
					<?php else : ?>
						<p class="dak-reviews-note"><?php esc_html_e( 'You can leave a review after a completed appointment with this doctor.', 'doctor-ak-portal' ); ?></p>
					<?php endif; ?>

					<?php if ( ! empty( $doctor['reviews'] ) ) : ?>
						<ul class="dak-reviews-list">
							<?php foreach ( $doctor['reviews'] as $dak_review ) : ?>
								<li>
									<div class="dak-reviews-head">
										<strong><?php echo esc_html( $dak_review['name'] ); ?></strong>
										<span class="dak-reviews-stars"><?php echo $dak_stars( $dak_review['rating'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
										<time><?php echo esc_html( $dak_review['date'] ); ?></time>
									</div>
									<?php if ( '' !== $dak_review['comment'] ) : ?>
										<p><?php echo esc_html( $dak_review['comment'] ); ?></p>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>

			<aside class="dak-profile-sidebar">
				<div class="dak-profile-card dak-profile-booking-card">
					<span class="dak-eyebrow"><?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?></span>
					<h2><?php esc_html_e( 'Consult Dr.', 'doctor-ak-portal' ); ?> <?php echo esc_html( $doctor['name'] ); ?></h2>

					<div class="dak-profile-booking-summary" id="dak-profile-booking-summary">
						<p class="dak-profile-booking-hint" id="dak-profile-booking-hint">
							<?php esc_html_e( 'Choose a visit type, clinic, and service above to see the exact fee.', 'doctor-ak-portal' ); ?>
						</p>
					</div>

					<div class="dak-profile-booking-fee<?php echo ( ! isset( $starting_fee_summary['state'] ) || 'none' === $starting_fee_summary['state'] || 'unset' === $starting_fee_summary['state'] ) ? ' dak-hidden' : ''; ?>" id="dak-profile-booking-fee">
						<span id="dak-profile-booking-fee-label"><?php esc_html_e( 'Consultation', 'doctor-ak-portal' ); ?></span>
						<strong id="dak-profile-booking-fee-amount"><?php echo esc_html( isset( $starting_fee_summary['label'] ) ? $starting_fee_summary['label'] : '' ); ?></strong>
					</div>

					<button
						type="button"
						class="dak-button dak-button-primary dak-button-block dak-profile-cta"
						id="dak-profile-sidebar-cta"
						data-doctor-id="<?php echo esc_attr( $doctor['id'] ); ?>"
						data-doctor-name="<?php echo esc_attr( sprintf( 'Dr. %s', $doctor['name'] ) ); ?>"
					>
						<span class="dak-profile-cta-label"><?php esc_html_e( 'Choose consultation', 'doctor-ak-portal' ); ?></span>
					</button>

					<?php if ( '' !== $cancellation_note ) : ?>
						<p class="dak-profile-booking-note"><?php echo esc_html( $cancellation_note ); ?></p>
					<?php endif; ?>
				</div>
			</aside>
		</div>

		<div class="dak-profile-mobile-bar" id="dak-profile-mobile-bar">
			<div class="dak-profile-mobile-bar-summary" id="dak-profile-mobile-bar-summary">
				<?php esc_html_e( 'Choose consultation', 'doctor-ak-portal' ); ?>
			</div>
			<button
				type="button"
				class="dak-button dak-button-primary dak-profile-cta"
				id="dak-profile-mobile-cta"
				data-doctor-id="<?php echo esc_attr( $doctor['id'] ); ?>"
				data-doctor-name="<?php echo esc_attr( sprintf( 'Dr. %s', $doctor['name'] ) ); ?>"
			>
				<span class="dak-profile-cta-label"><?php esc_html_e( 'Choose consultation', 'doctor-ak-portal' ); ?></span>
			</button>
		</div>
	<?php endif; ?>
</div>
