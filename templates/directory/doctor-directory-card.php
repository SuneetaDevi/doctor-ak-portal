<?php
/**
 * Template: One doctor in the [doctors_directory] list — photo, name,
 * specialty, experience, locations, next open slot, fees and booking.
 * (The home page and clinic pages keep their own directory/doctor-card.php.)
 *
 * @package DoctorAKPortal\Templates
 *
 * @var int        $id                    Doctor's user ID.
 * @var string     $name                  Stored display name (for search/sort).
 * @var string     $display_name          Name with a single "Dr." title.
 * @var string     $avatar_url            Photo (or fallback avatar) URL.
 * @var string[]   $specialization_labels Specialty labels.
 * @var int|null   $experience_years      Years of experience, or null when not on file.
 * @var string     $gender                'male', 'female' or ''.
 * @var array      $locations             Physical clinics: { name, place ("Area, City") }.
 * @var array      $city_options          City slug => label, for the Location filter.
 * @var bool       $offers_clinic         Has at least one physical clinic.
 * @var bool       $video_consultation    Offers video consultations.
 * @var array|null $clinic_fee            Services::public_price() for a clinic visit, or null.
 * @var array|null $video_fee             Services::public_price() for a video consultation, or null.
 * @var string     $next_available        "Today, 4:30 pm · Clinic", or '' when nothing is open in the next 7 days.
 * @var string     $availability          'today', 'week' or ''.
 * @var string     $next_at               'Y-m-d H:i' of that slot, or '' — for the "Soonest available" sort.
 * @var string     $clinic_booking_url    Booking page for a clinic visit with this doctor, or ''.
 * @var string     $video_booking_url     Booking page for a video consultation with this doctor, or ''.
 * @var string     $profile_url           This doctor's profile page.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_card_icon = function ( $paths ) {
	return '<svg viewBox="0 0 20 20" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths . '</svg>';
};

$dak_card_icons = array(
	'pin'      => $dak_card_icon( '<path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/>' ),
	'award'    => $dak_card_icon( '<circle cx="10" cy="7.5" r="4.5"/><path d="M7.3 11.2 6.5 17.5l3.5-2 3.5 2-.8-6.3"/>' ),
	'clock'    => $dak_card_icon( '<circle cx="10" cy="10" r="7.2"/><path d="M10 6v4l3 2"/>' ),
	'video'    => $dak_card_icon( '<rect x="2.5" y="5" width="10" height="10" rx="1.5"/><path d="M17.5 7.5 12.5 10l5 2.5z"/>' ),
	'building' => $dak_card_icon( '<rect x="4" y="2.5" width="12" height="15" rx="1"/><path d="M8 17.5v-3h4v3"/><path d="M7.5 6h1M11.5 6h1M7.5 9h1M11.5 9h1M7.5 12h1M11.5 12h1"/>' ),
	'chevron'  => $dak_card_icon( '<path d="M5.5 7.5l4.5 4.5 4.5-4.5"/>' ),
);

$dak_specialties  = array_slice( $specialization_labels, 0, 2 );
$dak_more_specs   = max( 0, count( $specialization_labels ) - 2 );
$dak_first_place  = ! empty( $locations ) ? $locations[0] : null;
$dak_other_places = array_slice( $locations, 1 );
$dak_initials     = '';

foreach ( array_slice( preg_split( '/\s+/', trim( preg_replace( '/^(dr|doctor|prof|professor)\b\.?\s*/i', '', $name ) ) ), 0, 2 ) as $dak_part ) {
	$dak_initials .= mb_strtoupper( mb_substr( $dak_part, 0, 1 ) );
}

// The dominant action books the visit type the doctor offers (clinic when
// both are); the other type, when offered, is a quieter second button.
$dak_primary_url   = $clinic_booking_url ? $clinic_booking_url : $video_booking_url;
$dak_primary_label = $clinic_booking_url ? __( 'Book clinic visit', 'doctor-ak-portal' ) : __( 'Book video consultation', 'doctor-ak-portal' );
$dak_has_fee       = ( $clinic_fee && 'unset' !== $clinic_fee['state'] ) || ( $video_fee && 'unset' !== $video_fee['state'] );
$dak_details_id    = 'dak-dir-locations-' . (int) $id;
$dak_visit_types   = implode( ',', array_filter( array( $offers_clinic ? 'clinic' : '', $video_consultation ? 'video' : '' ) ) );
?>
<li
	class="dak-dir-doctor"
	data-dak-dir-doctor
	data-id="<?php echo esc_attr( $id ); ?>"
	data-search="<?php echo esc_attr( mb_strtolower( $name . ',' . implode( ',', $specialization_labels ) ) ); ?>"
	data-name="<?php echo esc_attr( mb_strtolower( $name ) ); ?>"
	data-specialties="<?php echo esc_attr( mb_strtolower( implode( '|', $specialization_labels ) ) ); ?>"
	data-visit="<?php echo esc_attr( $dak_visit_types ); ?>"
	data-gender="<?php echo esc_attr( $gender ); ?>"
	data-cities="<?php echo esc_attr( implode( ',', array_keys( $city_options ) ) ); ?>"
	data-experience="<?php echo esc_attr( null === $experience_years ? '' : $experience_years ); ?>"
	data-availability="<?php echo esc_attr( $availability ); ?>"
	data-next-at="<?php echo esc_attr( $next_at ); ?>"
>
	<article class="dak-dir-doctor-card" aria-labelledby="dak-dir-doctor-<?php echo esc_attr( $id ); ?>">
		<a class="dak-dir-avatar" href="<?php echo esc_url( $profile_url ); ?>" tabindex="-1" aria-hidden="true">
			<?php if ( $avatar_url ) : ?>
				<img src="<?php echo esc_url( $avatar_url ); ?>" alt="" loading="lazy" width="96" height="96">
			<?php else : ?>
				<span class="dak-dir-avatar-initials"><?php echo esc_html( $dak_initials ); ?></span>
			<?php endif; ?>
		</a>

		<div class="dak-dir-doctor-main">
			<h3 class="dak-dir-doctor-name" id="dak-dir-doctor-<?php echo esc_attr( $id ); ?>">
				<a href="<?php echo esc_url( $profile_url ); ?>"><?php echo esc_html( $display_name ); ?></a>
			</h3>

			<?php if ( ! empty( $dak_specialties ) ) : ?>
				<p class="dak-dir-doctor-specialty">
					<?php echo esc_html( implode( ' · ', $dak_specialties ) ); ?>
					<?php if ( $dak_more_specs > 0 ) : ?>
						<span class="dak-dir-muted">
							<?php
							/* translators: %d: number of further specialties. */
							echo esc_html( sprintf( _n( '+%d more', '+%d more', $dak_more_specs, 'doctor-ak-portal' ), $dak_more_specs ) );
							?>
						</span>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<ul class="dak-dir-facts">
				<?php if ( null !== $experience_years ) : ?>
					<li>
						<?php echo $dak_card_icons['award']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
						<span>
							<?php
							/* translators: %d: years of experience. */
							echo esc_html( sprintf( _n( '%d year experience', '%d years experience', $experience_years, 'doctor-ak-portal' ), $experience_years ) );
							?>
						</span>
					</li>
				<?php endif; ?>

				<?php if ( $dak_first_place ) : ?>
					<li class="dak-dir-fact-location">
						<?php echo $dak_card_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
						<span>
							<span class="dak-dir-location-name"><?php echo esc_html( $dak_first_place['name'] ); ?></span><?php if ( '' !== $dak_first_place['place'] ) : ?><span class="dak-dir-muted">, <?php echo esc_html( $dak_first_place['place'] ); ?></span><?php endif; ?>
						</span>
					</li>
				<?php elseif ( $video_consultation ) : ?>
					<li>
						<?php echo $dak_card_icons['video']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
						<span><?php esc_html_e( 'Online consultations only', 'doctor-ak-portal' ); ?></span>
					</li>
				<?php endif; ?>
			</ul>

			<?php if ( ! empty( $dak_other_places ) ) : ?>
				<div class="dak-dir-more-locations">
					<button type="button" class="dak-dir-link-button dak-dir-disclosure" aria-expanded="false" aria-controls="<?php echo esc_attr( $dak_details_id ); ?>" data-dak-dir-disclosure>
						<?php
						/* translators: %d: number of further clinic locations. */
						echo esc_html( sprintf( _n( '+%d location', '+%d locations', count( $dak_other_places ), 'doctor-ak-portal' ), count( $dak_other_places ) ) );
						?>
						<?php echo $dak_card_icons['chevron']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					</button>
					<ul class="dak-dir-location-list" id="<?php echo esc_attr( $dak_details_id ); ?>" hidden>
						<?php foreach ( $dak_other_places as $dak_place ) : ?>
							<li>
								<span class="dak-dir-location-name"><?php echo esc_html( $dak_place['name'] ); ?></span>
								<?php if ( '' !== $dak_place['place'] ) : ?>
									<span class="dak-dir-muted"><?php echo esc_html( $dak_place['place'] ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( $next_available ) : ?>
				<div class="dak-dir-tags">
					<span class="dak-dir-tag<?php echo 'today' === $availability ? ' is-positive' : ''; ?>">
						<?php echo $dak_card_icons['clock']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
						<?php
						/* translators: %s: e.g. "Today, 4:30 pm · Clinic". */
						echo esc_html( sprintf( __( 'Next open slot: %s', 'doctor-ak-portal' ), $next_available ) );
						?>
					</span>
				</div>
			<?php endif; ?>
		</div>

		<div class="dak-dir-doctor-side">
			<?php if ( $offers_clinic || $video_consultation ) : ?>
				<dl class="dak-dir-fees">
					<?php if ( $clinic_fee && 'unset' !== $clinic_fee['state'] ) : ?>
						<div>
							<dt><?php esc_html_e( 'Clinic services', 'doctor-ak-portal' ); ?></dt>
							<dd><?php echo esc_html( $clinic_fee['label'] ); ?></dd>
						</div>
					<?php endif; ?>
					<?php if ( $video_fee && 'unset' !== $video_fee['state'] ) : ?>
						<div>
							<dt><?php esc_html_e( 'Video consultation', 'doctor-ak-portal' ); ?></dt>
							<dd><?php echo esc_html( $video_fee['label'] ); ?></dd>
						</div>
					<?php endif; ?>
					<?php if ( ! $dak_has_fee ) : ?>
						<div>
							<dt><?php esc_html_e( 'Fees', 'doctor-ak-portal' ); ?></dt>
							<dd><a href="<?php echo esc_url( $profile_url ); ?>"><?php esc_html_e( 'View fees', 'doctor-ak-portal' ); ?></a></dd>
						</div>
					<?php endif; ?>
				</dl>
			<?php endif; ?>

			<div class="dak-dir-actions">
				<?php if ( $dak_primary_url ) : ?>
					<a class="dak-dir-btn dak-dir-btn-primary" href="<?php echo esc_url( $dak_primary_url ); ?>">
						<?php echo esc_html( $dak_primary_label ); ?>
						<span class="dak-dir-sr"><?php echo esc_html( sprintf( /* translators: %s: doctor's name. */ __( 'with %s', 'doctor-ak-portal' ), $display_name ) ); ?></span>
					</a>
				<?php endif; ?>
				<?php if ( $clinic_booking_url && $video_booking_url ) : ?>
					<a class="dak-dir-btn dak-dir-btn-secondary" href="<?php echo esc_url( $video_booking_url ); ?>">
						<?php echo $dak_card_icons['video']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
						<?php esc_html_e( 'Video consultation', 'doctor-ak-portal' ); ?>
						<span class="dak-dir-sr"><?php echo esc_html( sprintf( /* translators: %s: doctor's name. */ __( 'with %s', 'doctor-ak-portal' ), $display_name ) ); ?></span>
					</a>
				<?php endif; ?>
				<a class="dak-dir-text-link" href="<?php echo esc_url( $profile_url ); ?>">
					<?php esc_html_e( 'View profile', 'doctor-ak-portal' ); ?>
					<span class="dak-dir-sr"><?php echo esc_html( $display_name ); ?></span>
				</a>
			</div>
		</div>
	</article>
</li>
