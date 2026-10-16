<?php
/**
 * Template partial: the small links under the account forms — back to the
 * home page, and the policy pages that exist.
 *
 * @package DoctorAKPortal\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_home_url = \DoctorAKPortal\Includes\Page_Finder::url_for_shortcode( 'dak_home' );
$dak_home_url = $dak_home_url ? $dak_home_url : home_url( '/' );
$dak_policies = array();

foreach ( array(
	'privacy-policy'       => __( 'Privacy Policy', 'doctor-ak-portal' ),
	'terms-and-conditions' => __( 'Terms & Conditions', 'doctor-ak-portal' ),
) as $dak_slug => $dak_label ) {
	$dak_page = function_exists( 'get_page_by_path' ) ? get_page_by_path( $dak_slug ) : null;

	if ( $dak_page ) {
		$dak_policies[ $dak_label ] = get_permalink( $dak_page );
	}
}
?>
<nav class="dak-acct-footer" aria-label="<?php esc_attr_e( 'More links', 'doctor-ak-portal' ); ?>">
	<a href="<?php echo esc_url( $dak_home_url ); ?>" class="dak-acct-footer-home">
		<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 15l-5-5 5-5"/></svg>
		<?php esc_html_e( 'Back to home', 'doctor-ak-portal' ); ?>
	</a>
	<?php if ( $dak_policies ) : ?>
		<ul class="dak-acct-footer-policies">
			<?php foreach ( $dak_policies as $dak_label => $dak_url ) : ?>
				<li><a href="<?php echo esc_url( $dak_url ); ?>"><?php echo esc_html( $dak_label ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</nav>
