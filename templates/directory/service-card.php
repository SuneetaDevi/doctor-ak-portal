<?php
/**
 * Template: Single service card within the services directory grid.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var int    $id          Representative Services row ID for this service name (see Services::grouped_active_for_public_directory()) — the detail page looks up every doctor offering it from there.
 * @var string $name        Service name.
 * @var string $description Full description (excerpted below), or '' if the admin hasn't added one.
 * @var string $price_label Formatted price, e.g. "PKR 5,000" or "From PKR 5,000" (varies by doctor) or "Free".
 * @var string $image_url   Service image URL, or '' if none uploaded.
 * @var string $profile_url URL of this service's [service_profile_view] page.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_service_card_icons = array(
	'image'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="3.5" width="15" height="13" rx="1.5"/><circle cx="7" cy="8" r="1.5"/><path d="M17.5 13.5l-4-4-3 3-2.5-2.5-5 5"/></svg>',
	'calendar' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4" width="15" height="13" rx="1.5"/><path d="M2.5 8h15"/><path d="M6 2.5v3M14 2.5v3"/></svg>',
);

$dak_service_excerpt = wp_trim_words( $description, 20 );
?>
<div class="dak-service-card">
	<span class="dak-service-card-image">
		<?php if ( $image_url ) : ?>
			<img src="<?php echo esc_url( $image_url ); ?>" alt="">
		<?php else : ?>
			<?php echo $dak_service_card_icons['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
	</span>

	<div class="dak-service-card-body">
		<h3 class="dak-service-card-name"><?php echo esc_html( $name ); ?></h3>

		<?php if ( '' !== $dak_service_excerpt ) : ?>
			<p class="dak-service-card-excerpt"><?php echo esc_html( $dak_service_excerpt ); ?></p>
		<?php endif; ?>

		<div class="dak-service-card-price">
			<span><?php esc_html_e( 'Price', 'doctor-ak-portal' ); ?></span>
			<strong><?php echo esc_html( $price_label ); ?></strong>
		</div>

		<div class="dak-service-card-actions">
			<a class="dak-button dak-button-primary dak-button-block" href="<?php echo esc_url( $profile_url ); ?>">
				<?php echo $dak_service_card_icons['calendar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php esc_html_e( 'Book Appointment', 'doctor-ak-portal' ); ?>
			</a>
		</div>
	</div>
</div>
