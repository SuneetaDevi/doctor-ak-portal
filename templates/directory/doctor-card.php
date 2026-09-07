<?php
/**
 * Template: Single doctor card within the directory grid.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var int      $id                    Doctor's user ID.
 * @var string   $name                  Doctor's display name.
 * @var string   $avatar_url            Doctor's photo (or fallback avatar) URL.
 * @var string[] $specialization_labels Selected specialization labels.
 * @var int|string $years_experience     Doctor's years of experience, or '' if not set.
 * @var string   $clinic_location       Doctor's primary (first) physical clinic's name (falls back to its address if it somehow has none), or '' if no physical clinic at all.
 * @var string   $clinic_city_label     That same primary clinic's city label, or '' if it has none set.
 * @var string   $clinic_country_label  That same primary clinic's country label, or '' if it has none set.
 * @var int      $extra_clinic_count    Number of additional physical clinics beyond the primary one.
 * @var string[] $country_slugs         Country slugs across every physical clinic this doctor has (or their own profile country if none), for the Country filter.
 * @var string[] $city_slugs            City slugs across every physical clinic this doctor has (or their own profile city if none), for the City filter.
 * @var string[] $area_slugs            Area slugs across every physical clinic this doctor has (or their own profile area if none), for the Area filter.
 * @var string[] $clinic_labels         Every physical clinic name this doctor has, for the clinic filter.
 * @var bool     $is_available          Whether the doctor has any clinic with an enabled session day.
 * @var bool     $video_consultation    Whether the doctor offers online video consultations.
 * @var string   $profile_url           URL of this doctor's [doctor_profile_view] page.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_card_icons = array(
	'pin'      => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'calendar' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4" width="15" height="13" rx="1.5"/><path d="M2.5 8h15"/><path d="M6 2.5v3M14 2.5v3"/></svg>',
	'person'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M4 17c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/></svg>',
	'video'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5" width="10" height="10" rx="1.5"/><path d="M17.5 7.5 12.5 10l5 2.5z"/></svg>',
	'check'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>',
	'arrow'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10h12M11 5l5 5-5 5"/></svg>',
	'user'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M3.5 17c1-3.5 4-5 6.5-5s5.5 1.5 6.5 5"/></svg>',
);

// Just the primary specialty as a single pill — matches the reference
// design's one-tag card; a doctor with several is still fully searchable
// via data-search-specializations below, they just aren't all badged here.
$dak_card_primary_specialty = ! empty( $specialization_labels ) ? $specialization_labels[0] : '';
$dak_card_location_line2    = implode( ', ', array_filter( array( $clinic_city_label, $clinic_country_label ) ) );
$dak_card_display_name      = sprintf( 'Dr. %s', $name );
?>
<div
	class="dak-doctor-card"
	data-doctor-card
	data-search-name="<?php echo esc_attr( mb_strtolower( $name ) ); ?>"
	data-search-specializations="<?php echo esc_attr( mb_strtolower( implode( ',', $specialization_labels ) ) ); ?>"
	data-search-country="<?php echo esc_attr( implode( ',', $country_slugs ) ); ?>"
	data-search-city="<?php echo esc_attr( implode( ',', $city_slugs ) ); ?>"
	data-search-area="<?php echo esc_attr( implode( ',', $area_slugs ) ); ?>"
	data-search-clinics="<?php echo esc_attr( mb_strtolower( implode( ',', $clinic_labels ) ) ); ?>"
	data-search-available="<?php echo esc_attr( $is_available ? '1' : '0' ); ?>"
	data-search-video="<?php echo esc_attr( $video_consultation ? '1' : '0' ); ?>"
	data-sort-name="<?php echo esc_attr( $name ); ?>"
	data-sort-experience="<?php echo esc_attr( '' !== $years_experience ? (int) $years_experience : 0 ); ?>"
>
	<span class="dak-avatar dak-avatar-lg">
		<?php if ( $avatar_url ) : ?>
			<img src="<?php echo esc_url( $avatar_url ); ?>" alt="">
		<?php else : ?>
			<?php echo $dak_card_icons['person']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
	</span>

	<div class="dak-doctor-card-info">
		<div class="dak-doctor-card-name-row">
			<h3 class="dak-doctor-card-name"><?php echo esc_html( $dak_card_display_name ); ?></h3>

			<?php if ( $is_available ) : ?>
				<span class="dak-doctor-card-badge">
					<?php echo $dak_card_icons['check']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php esc_html_e( 'Available', 'doctor-ak-portal' ); ?>
				</span>
			<?php endif; ?>
		</div>

		<?php if ( '' !== $dak_card_primary_specialty ) : ?>
			<div class="dak-specialty-tags dak-doctor-card-specialties">
				<span class="dak-specialty-tag"><?php echo esc_html( $dak_card_primary_specialty ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $years_experience ) : ?>
			<p class="dak-doctor-card-experience">
				<?php echo $dak_card_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of years of experience. */
						_n( '%d year of experience', '%d years of experience', (int) $years_experience, 'doctor-ak-portal' ),
						(int) $years_experience
					)
				);
				?>
			</p>
		<?php endif; ?>

		<?php if ( $clinic_location ) : ?>
			<div class="dak-doctor-card-location">
				<span class="dak-location-icon" aria-hidden="true"><?php echo $dak_card_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span>
					<?php echo esc_html( $clinic_location ); ?>
					<?php if ( $extra_clinic_count > 0 ) : ?>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of additional clinics. */
								_n( ' +%d more', ' +%d more', $extra_clinic_count, 'doctor-ak-portal' ),
								$extra_clinic_count
							)
						);
						?>
					<?php endif; ?>
					<?php if ( '' !== $dak_card_location_line2 ) : ?>
						<br><?php echo esc_html( $dak_card_location_line2 ); ?>
					<?php endif; ?>
				</span>
			</div>
		<?php endif; ?>
	</div>

	<div class="dak-doctor-card-actions">
		<button
			type="button"
			class="dak-button dak-button-primary dak-button-block"
			data-dak-book-appointment
			data-doctor-id="<?php echo esc_attr( $id ); ?>"
			data-doctor-name="<?php echo esc_attr( $dak_card_display_name ); ?>"
			<?php if ( ! $video_consultation ) : ?>data-video-disabled="1"<?php endif; ?>
		>
			<?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?>
			<?php echo $dak_card_icons['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</button>

		<?php if ( $video_consultation ) : ?>
			<button
				type="button"
				class="dak-button dak-button-secondary dak-doctor-card-video-btn"
				data-dak-book-appointment
				data-doctor-id="<?php echo esc_attr( $id ); ?>"
				data-doctor-name="<?php echo esc_attr( $dak_card_display_name ); ?>"
				data-booking-type="video"
			>
				<?php echo $dak_card_icons['video']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php esc_html_e( 'Video Consultation', 'doctor-ak-portal' ); ?>
			</button>
		<?php endif; ?>

		<a class="dak-button dak-button-ghost dak-doctor-card-profile-btn" href="<?php echo esc_url( $profile_url ); ?>">
			<?php echo $dak_card_icons['user']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php esc_html_e( 'View Profile', 'doctor-ak-portal' ); ?>
		</a>
	</div>
</div>
