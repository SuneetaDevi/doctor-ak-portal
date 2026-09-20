<?php
/**
 * Template: Home page doctors slider card — a tall photo with a status badge
 * (kept as its own template rather than reusing directory/doctor-card.php,
 * whose vertical photo+details layout is shared with the standalone doctors
 * directory grid and the [featured_doctors] widget — changing that shared
 * partial would change the card everywhere it appears, not just here), and a
 * white info panel overlapping the photo's bottom edge: name, specialty,
 * experience, and a round arrow into the profile.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var int      $id                    Doctor's user ID.
 * @var string   $name                  Doctor's display name.
 * @var string   $avatar_url            Doctor's photo URL, or '' for the placeholder icon.
 * @var string[] $specialization_labels Selected specialization labels.
 * @var int|string $years_experience    Doctor's years of experience, or '' if not set.
 * @var bool     $is_available          Whether the doctor has any clinic with an enabled session day.
 * @var bool     $video_consultation    Whether the doctor offers online video consultations — also read by the home page's "Video Consultation" filter (see doctor-ak-featured-doctors.js), via the data-search-video attribute.
 * @var string   $profile_url           URL of this doctor's [doctor_profile_view] page.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_home_doctor_specialty = ! empty( $specialization_labels )
	? $specialization_labels[0]
	: __( 'General Physician', 'doctor-ak-portal' );
?>
<div class="dak-home-doctor-card" data-search-video="<?php echo esc_attr( $video_consultation ? '1' : '0' ); ?>">
	<a class="dak-home-doctor-card-photo" href="<?php echo esc_url( $profile_url ); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( $avatar_url ) : ?>
			<img src="<?php echo esc_url( $avatar_url ); ?>" alt="">
		<?php else : ?>
			<span class="dak-home-doctor-card-placeholder" aria-hidden="true">
				<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M4 17c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/></svg>
			</span>
		<?php endif; ?>

		<?php if ( $is_available ) : ?>
			<span class="dak-home-doctor-card-badge"><?php esc_html_e( 'Available', 'doctor-ak-portal' ); ?></span>
		<?php elseif ( $video_consultation ) : ?>
			<span class="dak-home-doctor-card-badge dak-home-doctor-card-badge-consult"><?php esc_html_e( 'Consultation', 'doctor-ak-portal' ); ?></span>
		<?php endif; ?>
	</a>

	<a class="dak-home-doctor-card-info" href="<?php echo esc_url( $profile_url ); ?>">
		<h3><?php echo esc_html( sprintf( 'Dr. %s', $name ) ); ?></h3>

		<span class="dak-home-doctor-card-line">
			<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5.6 3.4v3.9a3 3 0 0 0 6 0V3.4"/><path d="M4.2 3.4h2.6M10.4 3.4H13"/><path d="M8.6 10.3v1.9a3.6 3.6 0 0 0 7.2 0v-1.4"/><circle cx="15.8" cy="9" r="1.6"/></svg>
			<?php echo esc_html( $dak_home_doctor_specialty ); ?>
		</span>

		<?php if ( '' !== $years_experience ) : ?>
			<span class="dak-home-doctor-card-line">
				<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2.5" y="4" width="15" height="13" rx="1.5"/><path d="M2.5 8h15"/><path d="M6 2.5v3M14 2.5v3"/></svg>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of years of experience. */
						_n( '%d year experience', '%d years experience', (int) $years_experience, 'doctor-ak-portal' ),
						(int) $years_experience
					)
				);
				?>
			</span>
		<?php endif; ?>

		<span class="dak-home-doctor-card-go" aria-hidden="true">
			<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 10h12"/><path d="M11 5.5l4.5 4.5-4.5 4.5"/></svg>
		</span>
	</a>
</div>
