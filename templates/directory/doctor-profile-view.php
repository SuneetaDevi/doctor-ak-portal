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
 *     @type array    $clinics               Doctor's clinics, each with added 'hours_label'/'fee_label'.
 *     @type string   $years_experience      Years of experience.
 *     @type string   $qualification         Qualification(s), e.g. "MBBS, FCPS".
 *     @type string   $short_description     One-line profile tagline, or ''.
 *     @type string   $expertise             Other-expertise text — may contain rich-text HTML (bold/italic/lists/links) from the doctor's/admin's formatting toolbar, or ''.
 *     @type array    $awards                Awards list, see Doctor_Awards::get_for_doctor().
 *     @type bool     $video_consultation    Whether video consultations are offered.
 *     @type string   $phone                 First clinic with a phone number on file, or ''.
 * }
 * @var string $directory_url      "All Doctors" breadcrumb link.
 * @var string $starting_fee_label Cheapest configured consultation fee, or '' if none configured.
 * @var string $cancellation_note  Doctor's real cancellation policy text.
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
);
?>
<div class="dak-portal dak-directory">
	<?php if ( ! $doctor ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'Doctor not found.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<?php $dak_first_specialization = ! empty( $doctor['specialization_labels'] ) ? $doctor['specialization_labels'][0] : ''; ?>

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

		<div class="dak-profile-header-card">
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

				<?php if ( '' !== $doctor['short_description'] ) : ?>
					<p class="dak-profile-tagline"><?php echo esc_html( $doctor['short_description'] ); ?></p>
				<?php endif; ?>

				<div class="dak-profile-stats">
					<?php if ( $doctor['years_experience'] ) : ?>
						<span class="dak-profile-stat">
							<span class="dak-profile-stat-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['clock']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<strong><?php echo esc_html( $doctor['years_experience'] ); ?> <?php esc_html_e( 'yrs', 'doctor-ak-portal' ); ?></strong>
							<span><?php esc_html_e( 'Experience', 'doctor-ak-portal' ); ?></span>
						</span>
					<?php endif; ?>

					<?php if ( ! empty( $doctor['clinics'] ) ) : ?>
						<a class="dak-profile-stat dak-profile-stat-link" href="#dak-profile-clinics">
							<span class="dak-profile-stat-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<strong><?php echo count( $doctor['clinics'] ); ?></strong>
							<span><?php echo esc_html( _n( 'Location', 'Locations', count( $doctor['clinics'] ), 'doctor-ak-portal' ) ); ?></span>
						</a>
					<?php endif; ?>

					<?php if ( $doctor['video_consultation'] ) : ?>
						<span class="dak-profile-stat">
							<span class="dak-profile-stat-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['video']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<strong><?php esc_html_e( 'Online', 'doctor-ak-portal' ); ?></strong>
							<span><?php esc_html_e( 'Video Consults', 'doctor-ak-portal' ); ?></span>
						</span>
					<?php endif; ?>
				</div>

				<div class="dak-profile-header-actions">
					<button
						type="button"
						class="dak-button dak-button-primary"
						data-dak-book-appointment
						data-doctor-id="<?php echo esc_attr( $doctor['id'] ); ?>"
						data-doctor-name="<?php echo esc_attr( sprintf( 'Dr. %s', $doctor['name'] ) ); ?>"
						<?php if ( ! $doctor['video_consultation'] ) : ?>data-video-disabled="1"<?php endif; ?>
					>
						<?php echo $dak_profile_view_icons['badge']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?>
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

		<div class="dak-profile-layout">
			<div class="dak-profile-main">
				<?php if ( ! empty( $doctor['specialization_labels'] ) ) : ?>
					<div class="dak-profile-card">
						<h2><?php esc_html_e( 'Specializations', 'doctor-ak-portal' ); ?></h2>
						<div class="dak-specialty-tags">
							<?php foreach ( $doctor['specialization_labels'] as $label ) : ?>
								<span class="dak-specialty-tag"><?php echo esc_html( $label ); ?></span>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( '' !== $doctor['expertise'] ) : ?>
					<div class="dak-profile-card">
						<h2><?php esc_html_e( 'Other Expertise', 'doctor-ak-portal' ); ?></h2>
						<div class="dak-profile-expertise dak-rich-text-content"><?php echo wp_kses_post( $doctor['expertise'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() output. ?></div>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $doctor['awards'] ) ) : ?>
					<div class="dak-profile-card">
						<h2>
							<span class="dak-profile-card-title-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['award']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php esc_html_e( 'Awards & Recognition', 'doctor-ak-portal' ); ?>
						</h2>

						<ul class="dak-profile-awards">
							<?php foreach ( $doctor['awards'] as $award ) : ?>
								<li>
									<span class="dak-profile-award-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['award']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
									<span class="dak-profile-award-title"><?php echo esc_html( $award['title'] ); ?></span>
									<?php if ( '' !== $award['year'] ) : ?>
										<span class="dak-profile-award-year"><?php echo esc_html( $award['year'] ); ?></span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $doctor['clinics'] ) ) : ?>
					<div class="dak-profile-card" id="dak-profile-clinics">
						<h2>
							<span class="dak-profile-card-title-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php esc_html_e( 'Clinics', 'doctor-ak-portal' ); ?>
						</h2>

						<div class="dak-profile-clinics" id="dak-profile-clinic-list">
							<?php foreach ( $doctor['clinics'] as $clinic ) : ?>
								<?php
								$dak_clinic_is_video = \DoctorAKPortal\Includes\Clinics::TYPE_VIDEO === $clinic['type'];
								$dak_clinic_label    = $dak_clinic_is_video ? __( 'Online Consultation', 'doctor-ak-portal' ) : $clinic['name'];
								?>
								<div
									class="dak-profile-clinic-row"
									data-clinic-select
									role="button"
									tabindex="0"
									aria-pressed="false"
									data-booking-type="<?php echo esc_attr( $dak_clinic_is_video ? 'video' : 'clinic' ); ?>"
									data-clinic-label="<?php echo esc_attr( $dak_clinic_label ); ?>"
									data-fee-label="<?php echo esc_attr( $clinic['fee_label'] ); ?>"
								>
									<div class="dak-profile-clinic-main">
										<span class="dak-profile-clinic-radio" aria-hidden="true"></span>
										<div class="dak-profile-clinic-info">
										<strong>
											<?php echo esc_html( $dak_clinic_label ); ?>
										</strong>

										<?php if ( ! $dak_clinic_is_video && ( '' !== $clinic['address'] || '' !== $clinic['area_label'] || '' !== $clinic['city_label'] ) ) : ?>
											<span class="dak-profile-clinic-meta">
												<span class="dak-location-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
												<?php echo esc_html( implode( ', ', array_filter( array( $clinic['address'], $clinic['area_label'], $clinic['city_label'] ) ) ) ); ?>
											</span>
										<?php endif; ?>

										<?php if ( ! empty( $clinic['enabled_days'] ) ) : ?>
											<span class="dak-profile-clinic-meta">
												<span class="dak-location-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['badge']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
												<?php echo esc_html( implode( ', ', $clinic['enabled_days'] ) ); ?>
											</span>
										<?php endif; ?>

										<?php if ( '' !== $clinic['hours_label'] ) : ?>
											<span class="dak-profile-clinic-meta">
												<span class="dak-location-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['clock']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
												<?php echo esc_html( $clinic['hours_label'] ); ?>
											</span>
										<?php endif; ?>
										</div>
									</div>

									<?php if ( '' !== $clinic['fee_label'] ) : ?>
										<div class="dak-profile-clinic-fee">
											<span><?php esc_html_e( 'Fee', 'doctor-ak-portal' ); ?></span>
											<strong><?php echo esc_html( $clinic['fee_label'] ); ?></strong>
										</div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<aside class="dak-profile-sidebar">
				<div class="dak-profile-card dak-profile-booking-card">
					<span class="dak-eyebrow"><?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?></span>
					<h2><?php esc_html_e( 'Consult Dr.', 'doctor-ak-portal' ); ?> <?php echo esc_html( $doctor['name'] ); ?></h2>
					<p class="dak-profile-booking-hint" id="dak-profile-booking-hint">
						<?php
						echo ! empty( $doctor['clinics'] )
							? esc_html__( 'Select a clinic from the list to see its fee, then choose a date and time on the next step.', 'doctor-ak-portal' )
							: esc_html__( 'Choose a date and time that works for you on the next step.', 'doctor-ak-portal' );
						?>
					</p>

					<div class="dak-profile-booking-fee<?php echo '' === $starting_fee_label ? ' dak-hidden' : ''; ?>" id="dak-profile-booking-fee">
						<span id="dak-profile-booking-fee-label"><?php esc_html_e( 'Consultation from', 'doctor-ak-portal' ); ?></span>
						<strong id="dak-profile-booking-fee-amount"><?php echo esc_html( $starting_fee_label ); ?></strong>
					</div>

					<button
						type="button"
						class="dak-button dak-button-primary dak-button-block"
						id="dak-profile-booking-button"
						data-dak-book-appointment
						data-doctor-id="<?php echo esc_attr( $doctor['id'] ); ?>"
						data-doctor-name="<?php echo esc_attr( sprintf( 'Dr. %s', $doctor['name'] ) ); ?>"
						<?php if ( ! $doctor['video_consultation'] ) : ?>data-video-disabled="1"<?php endif; ?>
						<?php if ( ! empty( $doctor['clinics'] ) ) : ?>disabled title="<?php esc_attr_e( 'Select a clinic below first', 'doctor-ak-portal' ); ?>"<?php endif; ?>
					>
						<?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?>
					</button>

					<?php if ( '' !== $cancellation_note ) : ?>
						<p class="dak-profile-booking-note"><?php echo esc_html( $cancellation_note ); ?></p>
					<?php endif; ?>
				</div>
			</aside>
		</div>
	<?php endif; ?>
</div>
