<?php
/**
 * Partial: the "% OFF" tag on a service's image (home page services and the
 * [services_directory] grid). Shows the largest discount currently active
 * across the doctors offering it — "Up to" when several doctors offer it,
 * since not every one may have the same discount.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var int $dak_discount  Discount percentage (> 0).
 * @var int $dak_providers Optional: how many doctors offer it.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_providers = isset( $dak_providers ) ? (int) $dak_providers : ( isset( $provider_count ) ? (int) $provider_count : 1 );
?>
<span class="dak-service-discount-tag">
	<?php
	echo esc_html(
		$dak_providers > 1
			/* translators: %d: discount percentage. */
			? sprintf( __( 'Up to %d%% OFF', 'doctor-ak-portal' ), $dak_discount )
			/* translators: %d: discount percentage. */
			: sprintf( __( '%d%% OFF', 'doctor-ak-portal' ), $dak_discount )
	);
	?>
</span>
