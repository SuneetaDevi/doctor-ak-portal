<?php
/**
 * Template: Home page service row — a wide, stacked card with the copy on one
 * side and the service photo on the other.
 *
 * Its own template rather than directory/service-card.php, whose compact
 * portrait card the standalone [services_directory] grid renders; reshaping
 * that shared partial would change the services page too.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var int    $id           Representative Services row ID for this service name (see Services::grouped_active_for_public_directory()).
 * @var string $name         Service name.
 * @var string $description  Full description — may contain rich-text HTML (bold/italic/lists/links) from the admin's formatting toolbar (excerpted to plain text below), or '' if the admin hasn't added one.
 * @var string $price_label  Formatted price, e.g. "PKR 5,000" or "From PKR 5,000" (varies by doctor) or "Free".
 * @var int    $doctor_count How many doctors offer this service.
 * @var string $image_url    Service image URL, or '' if none uploaded.
 * @var string $profile_url  URL of this service's [service_profile_view] page.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_home_service_icons = array(
	'image'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="3.5" width="15" height="13" rx="1.5"/><circle cx="7" cy="8" r="1.5"/><path d="M17.5 13.5l-4-4-3 3-2.5-2.5-5 5"/></svg>',
	'tag'     => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2.5l6.5 6.5-7.5 7.5-6.5-6.5V3.5z"/><circle cx="6.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/></svg>',
	'user'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="7" r="3.2"/><path d="M3.5 17c1-3.5 4-5 6.5-5s5.5 1.5 6.5 5"/></svg>',
	'arrow'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 10h12"/><path d="M11 5.5l4.5 4.5-4.5 4.5"/></svg>',
);

// $description may contain rich-text HTML (bold/italic/lists/links) from the
// admin's formatting toolbar — strip it down to plain text before trimming
// to a word count, so the excerpt never mid-cuts a tag.
$dak_home_service_excerpt = wp_trim_words( wp_strip_all_tags( $description ), 22 );
?>
<article class="dak-home-service-row">
	<div class="dak-home-service-row-body">
		<h3><?php echo esc_html( $name ); ?></h3>

		<?php if ( '' !== $dak_home_service_excerpt ) : ?>
			<p><?php echo esc_html( $dak_home_service_excerpt ); ?></p>
		<?php endif; ?>

		<div class="dak-home-service-row-meta">
			<span class="dak-home-service-row-chip">
				<span aria-hidden="true"><?php echo $dak_home_service_icons['tag']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<?php echo esc_html( $price_label ); ?>
			</span>

			<?php if ( $doctor_count > 0 ) : ?>
				<span class="dak-home-service-row-chip">
					<span aria-hidden="true"><?php echo $dak_home_service_icons['user']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of doctors offering this service. */
							_n( '%d specialist', '%d specialists', $doctor_count, 'doctor-ak-portal' ),
							$doctor_count
						)
					);
					?>
				</span>
			<?php endif; ?>
		</div>

		<a class="dak-home-service-row-link" href="<?php echo esc_url( $profile_url ); ?>">
			<?php esc_html_e( 'Learn more', 'doctor-ak-portal' ); ?>
			<?php echo $dak_home_service_icons['arrow']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</a>
	</div>

	<div class="dak-home-service-row-media">
		<?php if ( $image_url ) : ?>
			<img src="<?php echo esc_url( $image_url ); ?>" alt="">
		<?php else : ?>
			<span class="dak-home-service-row-placeholder" aria-hidden="true"><?php echo $dak_home_service_icons['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		<?php endif; ?>
	</div>
</article>
