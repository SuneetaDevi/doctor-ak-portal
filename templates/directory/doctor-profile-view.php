<?php
/**
 * Template: Minimal, read-only public doctor profile for the
 * [doctor_profile_view] shortcode.
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
 *     @type array    $clinics               Doctor's clinics, see Clinics::get_for_doctor().
 *     @type string   $years_experience      Years of experience.
 *     @type bool     $video_consultation    Whether video consultations are offered.
 * }
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_profile_view_icons = array(
	'pin'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'person' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M4 17c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/></svg>',
);
?>
<div class="dak-portal dak-directory">
	<?php if ( ! $doctor ) : ?>
		<p class="dak-empty-state"><?php esc_html_e( 'Doctor not found.', 'doctor-ak-portal' ); ?></p>
	<?php else : ?>
		<div class="dak-doctor-profile-view">
			<span class="dak-avatar dak-avatar-lg">
				<?php if ( $doctor['avatar_url'] ) : ?>
					<img src="<?php echo esc_url( $doctor['avatar_url'] ); ?>" alt="">
				<?php else : ?>
					<?php echo $dak_profile_view_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</span>

			<h1><?php echo esc_html( sprintf( 'Dr. %s', $doctor['name'] ) ); ?></h1>

			<?php if ( ! empty( $doctor['specialization_labels'] ) ) : ?>
				<div class="dak-specialty-tags dak-doctor-card-specialties">
					<?php foreach ( $doctor['specialization_labels'] as $label ) : ?>
						<span class="dak-specialty-tag"><?php echo esc_html( $label ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $doctor['clinics'] ) ) : ?>
				<div class="dak-doctor-profile-view-clinics">
					<?php foreach ( $doctor['clinics'] as $clinic ) : ?>
						<div class="dak-doctor-card-location">
							<span class="dak-location-icon" aria-hidden="true"><?php echo $dak_profile_view_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span>
								<?php echo esc_html( 'video' === $clinic['type'] ? __( 'Online Consultation', 'doctor-ak-portal' ) : ( '' !== $clinic['address'] ? $clinic['name'] . ' — ' . $clinic['address'] : $clinic['name'] ) ); ?>
								<?php if ( ! empty( $clinic['enabled_days'] ) ) : ?>
									(<?php echo esc_html( implode( ', ', $clinic['enabled_days'] ) ); ?>)
								<?php endif; ?>
							</span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $doctor['years_experience'] ) : ?>
				<p class="dak-doctor-profile-view-experience">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: years of experience. */
							__( '%s years of experience', 'doctor-ak-portal' ),
							$doctor['years_experience']
						)
					);
					?>
				</p>
			<?php endif; ?>

			<button
				type="button"
				class="dak-button dak-button-primary dak-button-block"
				data-dak-book-appointment
				data-doctor-id="<?php echo esc_attr( $doctor['id'] ); ?>"
				data-doctor-name="<?php echo esc_attr( sprintf( 'Dr. %s', $doctor['name'] ) ); ?>"
				<?php if ( ! $doctor['video_consultation'] ) : ?>data-video-disabled="1"<?php endif; ?>
			>
				<?php esc_html_e( 'Book Consultation', 'doctor-ak-portal' ); ?>
			</button>
		</div>
	<?php endif; ?>
</div>
