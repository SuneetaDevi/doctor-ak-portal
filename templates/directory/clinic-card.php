<?php
/**
 * Template: one clinic card in the clinics finder.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $clinic  One Clinic_Public_Data::locations() row.
 * @var bool   $hidden  Whether the card starts hidden (filtered out server-side).
 * @var string $heading Heading tag for the clinic name: 'h2' (default) or 'h3'.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DoctorAKPortal\Frontend\Clinic_Public_Data;
use DoctorAKPortal\Frontend\Public_Pages;

$dak_cc_heading = isset( $heading ) && 'h3' === $heading ? 'h3' : 'h2';
$dak_cc_title_id = 'dak-clinic-card-' . (int) $clinic['id'];
$dak_cc_count    = (int) $clinic['doctor_count'];
$dak_cc_search   = mb_strtolower( implode( ' ', array( $clinic['name'], $clinic['address'], $clinic['area_label'], $clinic['city_label'] ) ) );
?>
<article
	class="pub-card pub-clinic-card"
	aria-labelledby="<?php echo esc_attr( $dak_cc_title_id ); ?>"
	data-clinic-card
	data-name="<?php echo esc_attr( mb_strtolower( $clinic['name'] ) ); ?>"
	data-search="<?php echo esc_attr( $dak_cc_search ); ?>"
	data-city="<?php echo esc_attr( $clinic['city'] ); ?>"
	data-area="<?php echo esc_attr( $clinic['area'] ); ?>"
	data-doctors="<?php echo esc_attr( $dak_cc_count ); ?>"
	<?php echo ! empty( $hidden ) ? 'hidden' : ''; ?>
>
	<div class="pub-clinic-card-head">
		<span class="pub-clinic-mark"><?php echo Public_Pages::icon( 'building' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
		<div class="pub-clinic-card-title">
			<<?php echo esc_html( $dak_cc_heading ); ?> id="<?php echo esc_attr( $dak_cc_title_id ); ?>">
				<?php if ( '' !== $clinic['profile_url'] ) : ?>
					<a href="<?php echo esc_url( $clinic['profile_url'] ); ?>"><?php echo esc_html( $clinic['name'] ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $clinic['name'] ); ?>
				<?php endif; ?>
			</<?php echo esc_html( $dak_cc_heading ); ?>>
			<?php if ( '' !== $clinic['place'] ) : ?>
				<span class="pub-clinic-place"><?php echo esc_html( $clinic['place'] ); ?></span>
			<?php endif; ?>
		</div>
	</div>

	<div class="pub-clinic-card-body">
		<?php if ( '' !== $clinic['address_short'] ) : ?>
			<p class="pub-meta">
				<span class="pub-icon"><?php echo Public_Pages::icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<span class="pub-clinic-address" title="<?php echo esc_attr( $clinic['address_short'] ); ?>"><?php echo esc_html( $clinic['address_short'] ); ?></span>
			</p>
		<?php endif; ?>

		<p class="pub-meta">
			<span class="pub-icon"><?php echo Public_Pages::icon( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
			<span>
				<?php
				if ( $dak_cc_count > 0 ) {
					/* translators: %s: number of doctors. */
					echo esc_html( sprintf( _n( '%s doctor', '%s doctors', $dak_cc_count, 'doctor-ak-portal' ), number_format_i18n( $dak_cc_count ) ) );
				} else {
					esc_html_e( 'No doctors listed', 'doctor-ak-portal' );
				}
				?>
			</span>
		</p>

		<?php if ( '' !== $clinic['phone_href'] ) : ?>
			<p class="pub-meta">
				<span class="pub-icon"><?php echo Public_Pages::icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<span>
					<?php echo esc_html( 'central' === $clinic['phone_kind'] ? __( 'Booking line:', 'doctor-ak-portal' ) : __( 'Clinic phone:', 'doctor-ak-portal' ) ); ?>
					<a class="pub-phone-link" href="<?php echo esc_attr( $clinic['phone_href'] ); ?>"><?php echo esc_html( Clinic_Public_Data::format_phone( $clinic['phone'] ) ); ?></a>
				</span>
			</p>
		<?php endif; ?>
	</div>

	<div class="pub-clinic-card-actions">
		<?php if ( '' !== $clinic['profile_url'] ) : ?>
			<?php if ( $dak_cc_count > 0 ) : ?>
				<a class="pub-btn pub-btn-primary pub-btn-sm" href="<?php echo esc_url( $clinic['profile_url'] ); ?>" aria-describedby="<?php echo esc_attr( $dak_cc_title_id ); ?>">
					<?php esc_html_e( 'View doctors', 'doctor-ak-portal' ); ?>
				</a>
			<?php else : ?>
				<a class="pub-btn pub-btn-secondary pub-btn-sm" href="<?php echo esc_url( $clinic['profile_url'] ); ?>" aria-describedby="<?php echo esc_attr( $dak_cc_title_id ); ?>">
					<?php esc_html_e( 'View clinic details', 'doctor-ak-portal' ); ?>
				</a>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( '' !== $clinic['map_url'] ) : ?>
			<a class="pub-btn pub-btn-secondary pub-btn-sm" href="<?php echo esc_url( $clinic['map_url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-describedby="<?php echo esc_attr( $dak_cc_title_id ); ?>">
				<?php echo Public_Pages::icon( 'map' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				<?php esc_html_e( 'Get directions', 'doctor-ak-portal' ); ?>
				<span class="pub-sr-only"><?php esc_html_e( '(opens Google Maps in a new tab)', 'doctor-ak-portal' ); ?></span>
			</a>
		<?php endif; ?>
	</div>
</article>
