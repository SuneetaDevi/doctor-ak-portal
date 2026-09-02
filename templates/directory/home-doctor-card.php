<?php
/**
 * Template: Home page doctors slider card — a full-bleed photo with the
 * name/specialty always visible over a bottom scrim, and experience/actions
 * revealed on hover or keyboard focus (kept as its own template rather than
 * reusing directory/doctor-card.php, whose vertical photo+details layout is
 * shared with the standalone doctors directory grid and the
 * [featured_doctors] widget — changing that shared partial would change the
 * card everywhere it appears, not just here).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var int      $id                    Doctor's user ID.
 * @var string   $name                  Doctor's display name.
 * @var string   $avatar_url            Doctor's photo URL, or '' for the placeholder icon.
 * @var string[] $specialization_labels Selected specialization labels.
 * @var int|string $years_experience    Doctor's years of experience, or '' if not set.
 * @var bool     $is_available          Whether the doctor has any clinic with an enabled session day.
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
<div class="dak-home-doctor-card">
	<a class="dak-home-doctor-card-photo" href="<?php echo esc_url( $profile_url ); ?>">
		<?php if ( $avatar_url ) : ?>
			<img src="<?php echo esc_url( $avatar_url ); ?>" alt="">
		<?php else : ?>
			<span class="dak-home-doctor-card-placeholder" aria-hidden="true">
				<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M4 17c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/></svg>
			</span>
		<?php endif; ?>

		<?php if ( $is_available ) : ?>
			<span class="dak-home-doctor-card-badge"><?php esc_html_e( 'Available', 'doctor-ak-portal' ); ?></span>
		<?php endif; ?>
	</a>

	<div class="dak-home-doctor-card-info">
		<h3><?php echo esc_html( sprintf( 'Dr. %s', $name ) ); ?></h3>
		<span class="dak-home-doctor-card-specialty"><?php echo esc_html( $dak_home_doctor_specialty ); ?></span>

		<div class="dak-home-doctor-card-more">
			<?php if ( '' !== $years_experience ) : ?>
				<span class="dak-home-doctor-card-meta">
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

			<div class="dak-home-doctor-card-actions">
				<button type="button" class="dak-button dak-button-primary dak-button-sm" data-dak-book-appointment data-doctor-id="<?php echo esc_attr( $id ); ?>" data-doctor-name="<?php echo esc_attr( sprintf( 'Dr. %s', $name ) ); ?>">
					<?php esc_html_e( 'Book', 'doctor-ak-portal' ); ?>
				</button>

				<a class="dak-button dak-button-secondary dak-button-sm" href="<?php echo esc_url( $profile_url ); ?>">
					<?php esc_html_e( 'View Profile', 'doctor-ak-portal' ); ?>
				</a>
			</div>
		</div>
	</div>
</div>
