<?php
/**
 * Partial: a service's discount under its price in the dashboard services
 * tables (doctor "Services" tab and admin "Services" list) — the full price
 * it's discounted from and until when, or that a set discount has ended.
 * Included inside those tables' row loop.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $service Decoded service row (Services::decode_row()).
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $service['discount_percent'] ) ) {
	return;
}

if ( $service['discount_active'] ) :
	?>
	<span class="dak-cell-note dak-service-discount-note">
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: discount percentage, 2: full price before discount. */
				__( '%1$d%% off · was %2$s', 'doctor-ak-portal' ),
				(int) $service['discount_percent'],
				\DoctorAKPortal\Includes\Dashboard_Format::money( $service['base_effective_price'] )
			)
		);

		if ( '' !== $service['discount_ends_at'] ) {
			echo esc_html(
				' · ' . sprintf(
					/* translators: %s: date the discount ends. */
					__( 'until %s', 'doctor-ak-portal' ),
					date_i18n( 'j M Y, g:i a', strtotime( $service['discount_ends_at'] ) )
				)
			);
		}
		?>
	</span>
<?php else : ?>
	<span class="dak-cell-note"><?php echo esc_html( sprintf( /* translators: %d: discount percentage. */ __( '%d%% discount ended', 'doctor-ak-portal' ), (int) $service['discount_percent'] ) ); ?></span>
	<?php
endif;
