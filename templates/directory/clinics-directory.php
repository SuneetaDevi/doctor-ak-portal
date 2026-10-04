<?php
/**
 * Template: clinic finder for the [clinics_directory] shortcode.
 *
 * Search, city, area and sort are a plain GET form (works without
 * JavaScript; the server already filtered the cards). doctor-ak-clinic-finder.js
 * then filters live and keeps the URL in step, so Back restores the results.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string[]   $cards_html    Pre-rendered directory/clinic-card.php output for every clinic (non-matching ones carry `hidden`).
 * @var int        $total         Number of published clinics.
 * @var int        $visible       Number matching the current filters.
 * @var array      $filters       { q, city, area, sort, city_label, area_label }.
 * @var array      $cities        Clinic_Public_Data::cities().
 * @var array      $sort_options  key => label.
 * @var string     $city_label    "Karachi, Hyderabad and Quetta".
 * @var array|null $booking_line  { display, href } or null.
 * @var bool       $load_error    Whether the clinic list failed to load.
 * @var string     $home_url      Home page URL.
 * @var string     $directory_url This page's URL (the form's action and "Clear filters").
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use DoctorAKPortal\Frontend\Public_Pages;

$dak_cf_areas = array();

foreach ( $cities as $dak_cf_city ) {
	$dak_cf_areas[ $dak_cf_city['slug'] ] = array_map(
		function ( $area ) {
			return array(
				'slug'  => $area['slug'],
				'label' => $area['label'],
			);
		},
		$dak_cf_city['areas']
	);
}

$dak_cf_selected_areas = '' !== $filters['city'] && isset( $dak_cf_areas[ $filters['city'] ] ) ? $dak_cf_areas[ $filters['city'] ] : array();
$dak_cf_has_filters    = '' !== $filters['q'] || '' !== $filters['city'] || '' !== $filters['area'];

// Strings the finder script needs when it re-renders the count and chips.
$dak_cf_strings = array(
	/* translators: %s: number of clinics. */
	'totalOne'     => _n( '%s clinic', '%s clinics', 1, 'doctor-ak-portal' ),
	/* translators: %s: number of clinics. */
	'totalMany'    => _n( '%s clinic', '%s clinics', 2, 'doctor-ak-portal' ),
	/* translators: 1: number of matching clinics, 2: total number of clinics. */
	'showingOne'   => _n( 'Showing %1$s of %2$s clinic', 'Showing %1$s of %2$s clinics', 1, 'doctor-ak-portal' ),
	/* translators: 1: number of matching clinics, 2: total number of clinics. */
	'showingMany'  => _n( 'Showing %1$s of %2$s clinic', 'Showing %1$s of %2$s clinics', 2, 'doctor-ak-portal' ),
	'allAreas'     => __( 'All areas', 'doctor-ak-portal' ),
	'chooseCity'   => __( 'Choose a city first', 'doctor-ak-portal' ),
	'removeSearch' => __( 'Remove search', 'doctor-ak-portal' ),
	'removeCity'   => __( 'Remove city filter', 'doctor-ak-portal' ),
	'removeArea'   => __( 'Remove area filter', 'doctor-ak-portal' ),
);
?>
<div class="dak-pub dak-pub-clinics">
	<div class="pub-wrap pub-page">
		<nav class="pub-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'doctor-ak-portal' ); ?>">
			<ol>
				<li><a href="<?php echo esc_url( $home_url ); ?>"><?php esc_html_e( 'Home', 'doctor-ak-portal' ); ?></a></li>
				<li><span aria-current="page"><?php esc_html_e( 'Clinics', 'doctor-ak-portal' ); ?></span></li>
			</ol>
		</nav>

		<header class="pub-finder-head">
			<h1 class="pub-h1"><?php esc_html_e( 'Find a clinic', 'doctor-ak-portal' ); ?></h1>
			<p class="pub-lead">
				<?php
				if ( $total > 0 && '' !== $city_label ) {
					echo esc_html(
						sprintf(
							/* translators: 1: number of clinics, 2: list of cities. */
							_n( '%1$s clinic in %2$s. Search by name or address, or narrow by city and area.', '%1$s clinics in %2$s. Search by name or address, or narrow by city and area.', $total, 'doctor-ak-portal' ),
							number_format_i18n( $total ),
							$city_label
						)
					);
				} else {
					esc_html_e( 'Search our clinic locations and see which doctors practise at each one.', 'doctor-ak-portal' );
				}
				?>
			</p>
			<?php if ( $booking_line ) : ?>
				<p class="pub-meta">
					<span class="pub-icon"><?php echo Public_Pages::icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<span>
						<?php esc_html_e( 'Central booking line for all clinics:', 'doctor-ak-portal' ); ?>
						<a class="pub-phone-link" href="<?php echo esc_attr( $booking_line['href'] ); ?>"><?php echo esc_html( $booking_line['display'] ); ?></a>
					</span>
				</p>
			<?php endif; ?>
		</header>

		<?php if ( $load_error ) : ?>
			<div class="pub-state" role="alert">
				<span class="pub-state-icon"><?php echo Public_Pages::icon( 'alert' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<h2 class="pub-h3"><?php esc_html_e( 'We couldn’t load the clinic list', 'doctor-ak-portal' ); ?></h2>
				<p><?php esc_html_e( 'This is usually temporary. Please try again in a moment.', 'doctor-ak-portal' ); ?></p>
				<div class="pub-state-actions">
					<a class="pub-btn pub-btn-primary" href="<?php echo esc_url( $directory_url ); ?>"><?php esc_html_e( 'Try again', 'doctor-ak-portal' ); ?></a>
					<?php if ( $booking_line ) : ?>
						<a class="pub-btn pub-btn-secondary" href="<?php echo esc_attr( $booking_line['href'] ); ?>"><?php echo Public_Pages::icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( sprintf( /* translators: %s: phone number. */ __( 'Call %s', 'doctor-ak-portal' ), $booking_line['display'] ) ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		<?php elseif ( 0 === $total ) : ?>
			<div class="pub-state">
				<span class="pub-state-icon"><?php echo Public_Pages::icon( 'building' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<h2 class="pub-h3"><?php esc_html_e( 'No clinics are listed yet', 'doctor-ak-portal' ); ?></h2>
				<p><?php esc_html_e( 'Please check back soon.', 'doctor-ak-portal' ); ?></p>
			</div>
		<?php else : ?>
			<form
				class="pub-card pub-filters"
				method="get"
				action="<?php echo esc_url( $directory_url ); ?>"
				role="search"
				aria-label="<?php esc_attr_e( 'Filter clinics', 'doctor-ak-portal' ); ?>"
				data-clinic-finder
				data-areas="<?php echo esc_attr( wp_json_encode( $dak_cf_areas ) ); ?>"
				data-strings="<?php echo esc_attr( wp_json_encode( $dak_cf_strings ) ); ?>"
			>
				<div class="pub-field pub-field-search">
					<label for="dak-cf-q"><?php esc_html_e( 'Clinic name or address', 'doctor-ak-portal' ); ?></label>
					<div class="pub-input-icon">
						<span class="pub-icon"><?php echo Public_Pages::icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
						<input class="pub-input" type="search" id="dak-cf-q" name="q" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Hilal Ahmer, Clifton', 'doctor-ak-portal' ); ?>" autocomplete="off" maxlength="100">
					</div>
				</div>

				<div class="pub-field">
					<label for="dak-cf-city"><?php esc_html_e( 'City', 'doctor-ak-portal' ); ?></label>
					<select class="pub-select" id="dak-cf-city" name="city">
						<option value=""><?php esc_html_e( 'All cities', 'doctor-ak-portal' ); ?></option>
						<?php foreach ( $cities as $dak_cf_city ) : ?>
							<option value="<?php echo esc_attr( $dak_cf_city['slug'] ); ?>" <?php selected( $filters['city'], $dak_cf_city['slug'] ); ?>><?php echo esc_html( $dak_cf_city['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="pub-field">
					<label for="dak-cf-area"><?php esc_html_e( 'Area', 'doctor-ak-portal' ); ?></label>
					<select class="pub-select" id="dak-cf-area" name="area" <?php disabled( empty( $dak_cf_selected_areas ) ); ?> aria-describedby="dak-cf-area-hint">
						<option value=""><?php echo esc_html( empty( $dak_cf_selected_areas ) ? __( 'Choose a city first', 'doctor-ak-portal' ) : __( 'All areas', 'doctor-ak-portal' ) ); ?></option>
						<?php foreach ( $dak_cf_selected_areas as $dak_cf_area ) : ?>
							<option value="<?php echo esc_attr( $dak_cf_area['slug'] ); ?>" <?php selected( $filters['area'], $dak_cf_area['slug'] ); ?>><?php echo esc_html( $dak_cf_area['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<span class="pub-sr-only" id="dak-cf-area-hint"><?php esc_html_e( 'Areas depend on the selected city.', 'doctor-ak-portal' ); ?></span>
				</div>

				<div class="pub-field pub-field-sort">
					<label for="dak-cf-sort"><?php esc_html_e( 'Sort by', 'doctor-ak-portal' ); ?></label>
					<select class="pub-select" id="dak-cf-sort" name="sort">
						<?php foreach ( $sort_options as $dak_cf_key => $dak_cf_label ) : ?>
							<option value="<?php echo esc_attr( $dak_cf_key ); ?>" <?php selected( $filters['sort'], $dak_cf_key ); ?>><?php echo esc_html( $dak_cf_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<noscript>
					<button type="submit" class="pub-btn pub-btn-primary"><?php esc_html_e( 'Apply filters', 'doctor-ak-portal' ); ?></button>
				</noscript>
			</form>

			<div class="pub-results-bar">
				<p class="pub-results-count" id="dak-cf-count" role="status" aria-live="polite">
					<?php
					if ( $dak_cf_has_filters ) {
						/* translators: 1: number of matching clinics, 2: total number of clinics. */
						echo esc_html( sprintf( _n( 'Showing %1$s of %2$s clinic', 'Showing %1$s of %2$s clinics', $total, 'doctor-ak-portal' ), number_format_i18n( $visible ), number_format_i18n( $total ) ) );
					} else {
						/* translators: %s: number of clinics. */
						echo esc_html( sprintf( _n( '%s clinic', '%s clinics', $total, 'doctor-ak-portal' ), number_format_i18n( $total ) ) );
					}
					?>
				</p>
				<div class="pub-active-filters" id="dak-cf-active" aria-label="<?php esc_attr_e( 'Active filters', 'doctor-ak-portal' ); ?>">
					<?php if ( '' !== $filters['q'] ) : ?>
						<a class="pub-chip" href="<?php echo esc_url( remove_query_arg( 'q' ) ); ?>" data-clear="q">
							<?php /* translators: %s: search text. */ echo esc_html( sprintf( __( '“%s”', 'doctor-ak-portal' ), $filters['q'] ) ); ?>
							<?php echo Public_Pages::icon( 'x' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="pub-sr-only"><?php esc_html_e( 'Remove search', 'doctor-ak-portal' ); ?></span>
						</a>
					<?php endif; ?>
					<?php if ( '' !== $filters['city'] ) : ?>
						<a class="pub-chip" href="<?php echo esc_url( remove_query_arg( array( 'city', 'area' ) ) ); ?>" data-clear="city">
							<?php echo esc_html( $filters['city_label'] ); ?>
							<?php echo Public_Pages::icon( 'x' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="pub-sr-only"><?php esc_html_e( 'Remove city filter', 'doctor-ak-portal' ); ?></span>
						</a>
					<?php endif; ?>
					<?php if ( '' !== $filters['area'] ) : ?>
						<a class="pub-chip" href="<?php echo esc_url( remove_query_arg( 'area' ) ); ?>" data-clear="area">
							<?php echo esc_html( $filters['area_label'] ); ?>
							<?php echo Public_Pages::icon( 'x' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span class="pub-sr-only"><?php esc_html_e( 'Remove area filter', 'doctor-ak-portal' ); ?></span>
						</a>
					<?php endif; ?>
				</div>
				<a class="pub-btn pub-btn-ghost pub-btn-sm<?php echo $dak_cf_has_filters ? '' : ' dak-hidden'; ?>" id="dak-cf-clear" href="<?php echo esc_url( $directory_url ); ?>"><?php esc_html_e( 'Clear filters', 'doctor-ak-portal' ); ?></a>
			</div>

			<div class="pub-clinic-grid" id="dak-cf-grid">
				<?php foreach ( $cards_html as $dak_cf_card ) : ?>
					<?php echo $dak_cf_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card partial escapes its own output. ?>
				<?php endforeach; ?>
			</div>

			<div class="pub-state<?php echo 0 === $visible ? '' : ' dak-hidden'; ?>" id="dak-cf-empty">
				<span class="pub-state-icon"><?php echo Public_Pages::icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<h2 class="pub-h3"><?php esc_html_e( 'No clinics match your filters', 'doctor-ak-portal' ); ?></h2>
				<p><?php esc_html_e( 'Try a different name or address, or choose another city or area.', 'doctor-ak-portal' ); ?></p>
				<div class="pub-state-actions">
					<a class="pub-btn pub-btn-primary" href="<?php echo esc_url( $directory_url ); ?>" data-clear="all"><?php esc_html_e( 'Clear filters', 'doctor-ak-portal' ); ?></a>
					<?php if ( $booking_line ) : ?>
						<a class="pub-btn pub-btn-secondary" href="<?php echo esc_attr( $booking_line['href'] ); ?>"><?php echo Public_Pages::icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( sprintf( /* translators: %s: phone number. */ __( 'Call %s for help', 'doctor-ak-portal' ), $booking_line['display'] ) ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
