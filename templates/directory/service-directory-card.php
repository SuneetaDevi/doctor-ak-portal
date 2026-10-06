<?php
/**
 * Template: One service in the [services_directory] grid. (The home page
 * keeps its own directory/home-service-card.php.)
 *
 * @package DoctorAKPortal\Templates
 *
 * @var int    $id              Representative Services row ID (detail-page link).
 * @var string $name            Service name, exactly as stored.
 * @var string $excerpt         Plain-text excerpt of the stored description (Services::plain_excerpt()), or ''.
 * @var string $image_url       Service image, or ''.
 * @var string $category        Category slug (the bucket it is listed under).
 * @var string $category_label  Category label.
 * @var string $keywords        Admin-set search keywords, comma-separated.
 * @var bool   $requires_doctor Booked with a doctor (false: a request form on the detail page).
 * @var int    $provider_count  Distinct doctors offering it.
 * @var array  $price           Services::public_price(): { state, label, amount }.
 * @var string $profile_url     The service's detail page.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_title_id = 'dak-svc-' . (int) $id;
$dak_action   = $requires_doctor && $provider_count > 0 ? __( 'View providers', 'doctor-ak-portal' ) : __( 'View service', 'doctor-ak-portal' );
?>
<li
	class="dak-dir-service"
	data-dak-dir-service
	data-category="<?php echo esc_attr( $category ); ?>"
	data-search="<?php echo esc_attr( mb_strtolower( $name . ' ' . $keywords . ' ' . $category_label ) ); ?>"
>
	<article class="dak-dir-service-card" aria-labelledby="<?php echo esc_attr( $dak_title_id ); ?>">
		<div class="dak-dir-service-media<?php echo '' === $image_url ? ' is-empty' : ''; ?>">
			<?php if ( '' !== $image_url ) : ?>
				<img src="<?php echo esc_url( $image_url ); ?>" alt="" loading="lazy">
			<?php else : ?>
				<svg viewBox="0 0 20 20" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M10 16.2S3.8 12.4 3.8 8.1A3.1 3.1 0 0 1 10 6.3a3.1 3.1 0 0 1 6.2 1.8c0 4.3-6.2 8.1-6.2 8.1z"/><path d="M6.5 10h2l1-2 1.5 4 1-2h1.5"/></svg>
			<?php endif; ?>
		</div>

		<div class="dak-dir-service-body">
			<?php if ( '' !== $category_label ) : ?>
				<p class="dak-dir-service-category"><?php echo esc_html( $category_label ); ?></p>
			<?php endif; ?>

			<h3 class="dak-dir-service-title" id="<?php echo esc_attr( $dak_title_id ); ?>">
				<a href="<?php echo esc_url( $profile_url ); ?>"><?php echo esc_html( $name ); ?></a>
			</h3>

			<?php if ( '' !== $excerpt ) : ?>
				<p class="dak-dir-service-excerpt"><?php echo esc_html( $excerpt ); ?></p>
			<?php endif; ?>

			<dl class="dak-dir-service-meta">
				<div>
					<dt><?php esc_html_e( 'Price', 'doctor-ak-portal' ); ?></dt>
					<dd class="<?php echo 'unset' === $price['state'] ? 'is-unset' : ''; ?>"><?php echo esc_html( $price['label'] ); ?></dd>
				</div>
				<?php if ( $requires_doctor && $provider_count > 0 ) : ?>
					<div>
						<dt><?php esc_html_e( 'Offered by', 'doctor-ak-portal' ); ?></dt>
						<dd>
							<?php
							/* translators: %d: number of doctors. */
							echo esc_html( sprintf( _n( '%d doctor', '%d doctors', $provider_count, 'doctor-ak-portal' ), $provider_count ) );
							?>
						</dd>
					</div>
				<?php endif; ?>
			</dl>

			<a class="dak-dir-btn dak-dir-btn-secondary dak-dir-service-action" href="<?php echo esc_url( $profile_url ); ?>" tabindex="-1" aria-hidden="true">
				<?php echo esc_html( $dak_action ); ?>
			</a>
		</div>
	</article>
</li>
