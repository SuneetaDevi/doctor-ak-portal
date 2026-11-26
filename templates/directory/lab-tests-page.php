<?php
/**
 * Template: the public "Book a lab test" page ([lab_tests]).
 *
 * A promotional header, then two steps on one page: pick tests (search,
 * popular chips, one-tap Add), then book — the labs offering all of the
 * picked tests compared by price, lab visit or home collection, a day and a
 * time. doctor-ak-lab-tests-page.js does the picking and comparing from the
 * JSON below; the test list is also rendered here so it works without it.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array      $catalog       Lab_Tests_Page::catalog() — { tests, labs }.
 * @var array|null $promo         Lab_Tests_Page::promo(), null when nothing is bookable.
 * @var int[]      $picked        Test IDs to start with (?tests=, e.g. back from logging in).
 * @var string     $viewer        'member' (logged in — anyone can book) | 'guest' (asked to log in).
 * @var string     $page_url      This page's URL.
 * @var string     $login_url     Login page URL.
 * @var array      $days          Bookable days — { value, label, isToday }.
 * @var array      $windows       Time windows, slug => label.
 * @var int        $hour          Current hour (site time).
 * @var string     $saved_address The patient's last home-collection address.
 * @var string     $phone         The website's phone number.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_icons = array(
	'flask'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2.5h4M8.5 2.5v5L4.2 15a1.5 1.5 0 0 0 1.3 2.3h9a1.5 1.5 0 0 0 1.3-2.3L11.5 7.5v-5"/><path d="M6 12.5h8"/></svg>',
	'home'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 10 3.5l7 6"/><path d="M5 8.2V16.5h10V8.2"/><path d="M8.3 16.5v-4.3h3.4v4.3"/></svg>',
	'pin'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/></svg>',
	'file'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11.5 2.5H6A1.5 1.5 0 0 0 4.5 4v12A1.5 1.5 0 0 0 6 17.5h8a1.5 1.5 0 0 0 1.5-1.5V6.5z"/><path d="M11.5 2.5v4h4"/><path d="M7.5 10.5h5M7.5 13.5h5"/></svg>',
	'tag'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2.5l6.5 6.5-7.5 7.5-6.5-6.5V3.5z"/><circle cx="6.5" cy="6.5" r="1.2" fill="currentColor" stroke="none"/></svg>',
	'wallet' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="5" width="15" height="11" rx="1.8"/><path d="M2.5 8.5h15"/><circle cx="14.2" cy="12" r="1"/></svg>',
	'search' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M16.5 16.5l-3.6-3.6"/></svg>',
	'alert'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.2"/><path d="M10 6.5v4"/><path d="M10 13.5h.01"/></svg>',
	'check'  => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 10.5l3.5 3.5 7.5-8"/></svg>',
);

$dak_money       = function ( $amount ) {
	return 'PKR ' . number_format_i18n( (float) $amount );
};
$dak_window_ends = array( 'morning' => 12, 'afternoon' => 16, 'evening' => 20 );
?>
<div class="dak-portal dak-labs-page" data-dak-labs-page data-viewer="<?php echo esc_attr( $viewer ); ?>" data-hour="<?php echo esc_attr( $hour ); ?>" data-page-url="<?php echo esc_url( $page_url ); ?>" data-login-url="<?php echo esc_url( $login_url ); ?>">

	<section class="dak-labs-hero" aria-labelledby="dak-labs-title">
		<div class="dak-labs-hero-copy">
			<span class="dak-labs-eyebrow"><?php echo $dak_icons['flask']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Lab tests', 'doctor-ak-portal' ); ?></span>
			<h1 id="dak-labs-title">
				<?php esc_html_e( 'Book lab tests', 'doctor-ak-portal' ); ?>
				<em><?php echo esc_html( $promo && $promo['home'] ? __( 'from home.', 'doctor-ak-portal' ) : __( 'in minutes.', 'doctor-ak-portal' ) ); ?></em>
			</h1>
			<p><?php esc_html_e( 'Compare our partner labs, choose a lab visit or home sample collection, and get your results right in your patient portal.', 'doctor-ak-portal' ); ?></p>

			<?php if ( $promo ) : ?>
				<ul class="dak-labs-perks">
					<?php if ( $promo['home'] ) : ?>
						<li><?php echo $dak_icons['home']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Home sample collection', 'doctor-ak-portal' ); ?></li>
					<?php endif; ?>
					<li><?php echo $dak_icons['tag']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( sprintf( /* translators: %d: number of labs. */ _n( 'Prices from %d partner lab', 'Compare %d partner labs', $promo['labs'], 'doctor-ak-portal' ), $promo['labs'] ) ); ?></li>
					<li><?php echo $dak_icons['file']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Results in your portal', 'doctor-ak-portal' ); ?></li>
					<li><?php echo $dak_icons['wallet']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Pay at collection', 'doctor-ak-portal' ); ?></li>
				</ul>

				<div class="dak-labs-hero-actions">
					<a class="dak-button dak-button-primary dak-labs-hero-cta" href="#dak-labs-picker"><?php esc_html_e( 'Choose your tests', 'doctor-ak-portal' ); ?></a>
					<span class="dak-labs-from">
						<?php
						/* translators: 1: number of tests, 2: lowest price. */
						echo esc_html( sprintf( __( '%1$d tests · from %2$s', 'doctor-ak-portal' ), $promo['tests'], $dak_money( $promo['from'] ) ) );
						?>
					</span>
				</div>
			<?php endif; ?>
		</div>

		<ol class="dak-labs-steps" aria-label="<?php esc_attr_e( 'How it works', 'doctor-ak-portal' ); ?>">
			<li><span>1</span><strong><?php esc_html_e( 'Choose your tests', 'doctor-ak-portal' ); ?></strong><em><?php esc_html_e( 'Or book the ones your doctor suggested', 'doctor-ak-portal' ); ?></em></li>
			<li><span>2</span><strong><?php esc_html_e( 'Pick a lab, place and time', 'doctor-ak-portal' ); ?></strong><em><?php esc_html_e( 'Lab visit or home collection', 'doctor-ak-portal' ); ?></em></li>
			<li><span>3</span><strong><?php esc_html_e( 'Get results online', 'doctor-ak-portal' ); ?></strong><em><?php esc_html_e( 'In your patient portal', 'doctor-ak-portal' ); ?></em></li>
		</ol>
	</section>

	<?php if ( ! $promo ) : ?>
		<div class="dak-labs-empty">
			<h2><?php esc_html_e( 'Online lab test booking is coming soon', 'doctor-ak-portal' ); ?></h2>
			<?php if ( '' !== $phone ) : ?>
				<p>
					<?php esc_html_e( 'Meanwhile, call us to book a test:', 'doctor-ak-portal' ); ?>
					<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
				</p>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<script type="application/json" id="dak-labs-data"><?php echo wp_json_encode( array( 'tests' => $catalog['tests'], 'labs' => $catalog['labs'], 'picked' => $picked ) ); ?></script>

		<div class="dak-labs-layout">
			<section class="dak-labs-card" id="dak-labs-picker" aria-labelledby="dak-labs-pick-title">
				<h2 id="dak-labs-pick-title"><span class="dak-labs-num">1</span><?php esc_html_e( 'Choose your tests', 'doctor-ak-portal' ); ?></h2>

				<label class="dak-labs-search">
					<?php echo $dak_icons['search']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="dak-visually-hidden"><?php esc_html_e( 'Search tests', 'doctor-ak-portal' ); ?></span>
					<input type="search" data-dak-labs-search placeholder="<?php esc_attr_e( 'Search tests — e.g. CBC, sugar, thyroid', 'doctor-ak-portal' ); ?>" autocomplete="off">
				</label>

				<?php if ( ! empty( $promo['popular'] ) ) : ?>
					<div class="dak-labs-popular" aria-label="<?php esc_attr_e( 'Popular tests', 'doctor-ak-portal' ); ?>">
						<span><?php esc_html_e( 'Popular:', 'doctor-ak-portal' ); ?></span>
						<?php foreach ( $catalog['tests'] as $test ) : ?>
							<?php if ( in_array( $test['name'], wp_list_pluck( $promo['popular'], 'name' ), true ) ) : ?>
								<button type="button" class="dak-labs-chip" data-dak-labs-toggle="<?php echo esc_attr( $test['id'] ); ?>" aria-pressed="false">+ <?php echo esc_html( $test['name'] ); ?></button>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<ul class="dak-labs-tests" data-dak-labs-list>
					<?php foreach ( $catalog['tests'] as $test ) : ?>
						<li class="dak-labs-test" data-dak-labs-test="<?php echo esc_attr( $test['id'] ); ?>" data-search="<?php echo esc_attr( mb_strtolower( $test['name'] . ' ' . $test['keywords'] . ' ' . $test['sample'] ) ); ?>">
							<div class="dak-labs-test-main">
								<strong><?php echo esc_html( $test['name'] ); ?></strong>
								<span class="dak-labs-test-meta"><?php echo esc_html( implode( ' · ', array_filter( array( $test['sample'] ? sprintf( /* translators: %s: sample type. */ __( '%s sample', 'doctor-ak-portal' ), $test['sample'] ) : '', $test['report'] ? sprintf( /* translators: %s: e.g. "Same day". */ __( 'Report: %s', 'doctor-ak-portal' ), $test['report'] ) : '' ) ) ) ); ?></span>
								<?php if ( '' !== $test['prep'] ) : ?>
									<span class="dak-labs-prep"><?php echo $dak_icons['alert']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $test['prep'] ); ?></span>
								<?php endif; ?>
							</div>
							<div class="dak-labs-test-side">
								<span class="dak-labs-test-price">
									<?php
									/* translators: %s: lowest price. */
									echo esc_html( sprintf( __( 'from %s', 'doctor-ak-portal' ), $dak_money( $test['from'] ) ) );
									?>
								</span>
								<span class="dak-labs-test-labs"><?php echo esc_html( sprintf( /* translators: %d: number of labs. */ _n( '%d lab', '%d labs', $test['labs'], 'doctor-ak-portal' ), $test['labs'] ) ); ?></span>
								<button type="button" class="dak-labs-add" data-dak-labs-toggle="<?php echo esc_attr( $test['id'] ); ?>" aria-pressed="false" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: test name. */ __( 'Add %s', 'doctor-ak-portal' ), $test['name'] ) ); ?>"><?php esc_html_e( 'Add', 'doctor-ak-portal' ); ?></button>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
				<p class="dak-labs-nomatch" data-dak-labs-nomatch hidden><?php esc_html_e( 'No tests match your search.', 'doctor-ak-portal' ); ?></p>
			</section>

			<section class="dak-labs-card dak-labs-book" id="dak-labs-book" aria-labelledby="dak-labs-book-title" data-dak-labs-book hidden>
				<h2 id="dak-labs-book-title"><span class="dak-labs-num">2</span><?php esc_html_e( 'Book', 'doctor-ak-portal' ); ?></h2>

				<div class="dak-alert dak-alert-success" data-dak-labs-success role="status" hidden></div>

				<ul class="dak-labs-picked" data-dak-labs-picked aria-label="<?php esc_attr_e( 'Your tests', 'doctor-ak-portal' ); ?>"></ul>

				<form data-dak-labs-form novalidate>
					<fieldset class="dak-labs-step">
						<legend><?php esc_html_e( 'Choose a lab', 'doctor-ak-portal' ); ?></legend>
						<div class="dak-labs-choices" data-dak-labs-choices></div>
					</fieldset>

					<div data-dak-labs-rest>
						<fieldset class="dak-labs-step">
							<legend><?php esc_html_e( 'Where', 'doctor-ak-portal' ); ?></legend>
							<div class="dak-labs-pills">
								<label class="dak-labs-pill"><input type="radio" name="collection" value="lab" checked><span><?php echo $dak_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Visit the lab', 'doctor-ak-portal' ); ?></span></label>
								<label class="dak-labs-pill"><input type="radio" name="collection" value="home"><span><?php echo $dak_icons['home']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Home sample collection', 'doctor-ak-portal' ); ?></span></label>
							</div>
							<div class="dak-field dak-labs-address" data-dak-labs-address hidden>
								<label for="dak-labs-address"><?php esc_html_e( 'Address for sample collection', 'doctor-ak-portal' ); ?></label>
								<textarea id="dak-labs-address" name="address" rows="2" autocomplete="street-address" placeholder="<?php esc_attr_e( 'House, street, area, city', 'doctor-ak-portal' ); ?>"><?php echo esc_textarea( $saved_address ); ?></textarea>
							</div>
						</fieldset>

						<fieldset class="dak-labs-step">
							<legend><?php esc_html_e( 'When', 'doctor-ak-portal' ); ?></legend>
							<div class="dak-labs-pills dak-labs-days">
								<?php foreach ( $days as $dak_index => $day ) : ?>
									<label class="dak-labs-pill"><input type="radio" name="date" value="<?php echo esc_attr( $day['value'] ); ?>" data-today="<?php echo $day['isToday'] ? '1' : '0'; ?>"<?php checked( 0, $dak_index ); ?>><span><?php echo esc_html( $day['label'] ); ?></span></label>
								<?php endforeach; ?>
							</div>
							<div class="dak-labs-pills">
								<?php $dak_index = 0; ?>
								<?php foreach ( $windows as $dak_slug => $dak_label ) : ?>
									<label class="dak-labs-pill"><input type="radio" name="time_window" value="<?php echo esc_attr( $dak_slug ); ?>" data-ends="<?php echo esc_attr( $dak_window_ends[ $dak_slug ] ); ?>"<?php checked( 0, $dak_index++ ); ?>><span><?php echo esc_html( $dak_label ); ?></span></label>
								<?php endforeach; ?>
							</div>
						</fieldset>
					</div>

					<div class="dak-alert dak-alert-error" data-dak-labs-error role="alert" hidden></div>

					<div class="dak-labs-footer">
						<div class="dak-labs-total">
							<span><?php esc_html_e( 'Total', 'doctor-ak-portal' ); ?></span>
							<strong data-dak-labs-total>PKR 0</strong>
							<span><?php esc_html_e( 'Pay at collection', 'doctor-ak-portal' ); ?></span>
						</div>
						<?php if ( 'guest' === $viewer ) : ?>
							<div class="dak-labs-login">
								<a class="dak-button dak-button-primary" data-dak-labs-login href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Log in to book', 'doctor-ak-portal' ); ?></a>
								<span><?php esc_html_e( 'New here? You can create a free account from the login page — your tests stay picked.', 'doctor-ak-portal' ); ?></span>
							</div>
						<?php else : ?>
							<button type="submit" class="dak-button dak-button-primary" data-dak-labs-submit><?php esc_html_e( 'Book tests', 'doctor-ak-portal' ); ?></button>
						<?php endif; ?>
					</div>
				</form>
			</section>
		</div>

		<div class="dak-labs-cart" data-dak-labs-cart hidden>
			<span class="dak-labs-cart-text" data-dak-labs-cart-text></span>
			<a class="dak-button dak-button-primary" href="#dak-labs-book"><?php esc_html_e( 'Continue', 'doctor-ak-portal' ); ?></a>
		</div>
	<?php endif; ?>
</div>
