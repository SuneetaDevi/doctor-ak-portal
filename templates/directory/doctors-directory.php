<?php
/**
 * Template: Doctors directory for the [doctors_directory] shortcode.
 *
 * Search on top, then quick-filter chips (discount slabs, fee caps) that
 * are always one tap away, then a filter bar — Specialty, Visit type,
 * Availability, Experience, Gender, Location — each a button that opens a
 * small panel underneath and shows what's picked. Below 1024px the bar's
 * filters move into a labelled drawer ("Filters"); the chips stay on the
 * page as one scrolling row. Results show a live "Showing 1–12 of 53
 * doctors" count, sorting and pagination. Every card is rendered here;
 * doctor-ak-directory.js filters and sorts the full set before paginating,
 * and keeps the state in the URL so Back from a profile returns to the
 * same list.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string[] $doctors_html     Pre-rendered directory/doctor-directory-card.php output, most experienced first.
 * @var array    $specialities     { slug (lowercase label), label, count }, alphabetical.
 * @var array    $cities           { slug, label, count }, most doctors first.
 * @var array    $facets           Doctors per option: clinic, video, today, week, male, female.
 * @var array    $discount_slabs   Doctors per discount chip: 'any', '30', '50'.
 * @var array    $fee_slabs        Doctors per fee chip (lowest fee up to): '500', '1000', '1500'.
 * @var array    $experience_slabs Doctors per experience option (at least N years): '5', '10', '15', '20'.
 * @var int      $doctors_count    Number of listed doctors.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_icon = function ( $paths ) {
	return '<svg viewBox="0 0 20 20" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths . '</svg>';
};

$dak_icons = array(
	'search'  => $dak_icon( '<circle cx="8.8" cy="8.8" r="5.3"/><path d="M17 17l-3.8-3.8"/>' ),
	'x'       => $dak_icon( '<path d="M5.5 5.5l9 9M14.5 5.5l-9 9"/>' ),
	'sliders' => $dak_icon( '<path d="M3 6h9M15 6h2M3 14h5M11 14h6"/><circle cx="13.5" cy="6" r="1.7"/><circle cx="9.5" cy="14" r="1.7"/>' ),
	'pin'     => $dak_icon( '<path d="M10 18s6-5.2 6-9.8A6 6 0 0 0 4 8.2C4 12.8 10 18 10 18z"/><circle cx="10" cy="8" r="2"/>' ),
	'arrow_l' => $dak_icon( '<path d="M12.5 4.5 7 10l5.5 5.5"/>' ),
	'arrow_r' => $dak_icon( '<path d="M7.5 4.5 13 10l-5.5 5.5"/>' ),
	'chevron' => $dak_icon( '<path d="M5.5 7.5l4.5 4.5 4.5-4.5"/>' ),
	'tag'     => $dak_icon( '<path d="M10.6 2.5H16a1.5 1.5 0 0 1 1.5 1.5v5.4a1.5 1.5 0 0 1-.44 1.06l-6.6 6.6a1.5 1.5 0 0 1-2.12 0L3 11.72a1.5 1.5 0 0 1 0-2.12l6.6-6.6a1.5 1.5 0 0 1 1-.5z"/><circle cx="13.5" cy="6.5" r="1.2"/>' ),
	'wallet'  => $dak_icon( '<rect x="2.5" y="5" width="15" height="11" rx="2"/><path d="M2.5 8.5h15"/><path d="M13.5 12.2h1.5"/>' ),
	'check'   => $dak_icon( '<path d="M5 10.5l3.2 3.2L15 6.8"/>' ),
);

$dak_visible_specialities = 8;
$dak_count                = function ( $n ) {
	return '<span class="dak-dir-option-count">' . esc_html( number_format_i18n( $n ) ) . '</span>';
};

$discount_slabs   = isset( $discount_slabs ) ? $discount_slabs : array( 'any' => 0, '30' => 0, '50' => 0 );
$fee_slabs        = isset( $fee_slabs ) ? $fee_slabs : array( '500' => 0, '1000' => 0, '1500' => 0 );
$experience_slabs = isset( $experience_slabs ) ? $experience_slabs : array( '5' => 0, '10' => 0, '15' => 0, '20' => 0 );

// Quick-filter chips: one choice per row (picking another replaces it,
// picking the same one again turns it off — see doctor-ak-directory.js).
$dak_discount_chips = array(
	'any' => __( 'Any offer', 'doctor-ak-portal' ),
	/* translators: %d: discount percentage. */
	'30'  => sprintf( __( '%d%%+ off', 'doctor-ak-portal' ), 30 ),
	/* translators: %d: discount percentage. */
	'50'  => sprintf( __( '%d%%+ off', 'doctor-ak-portal' ), 50 ),
);

$dak_fee_chips = array();

foreach ( array_keys( $fee_slabs ) as $dak_fee_cap ) {
	/* translators: %s: amount in PKR. */
	$dak_fee_chips[ $dak_fee_cap ] = sprintf( __( 'Up to PKR %s', 'doctor-ak-portal' ), number_format_i18n( (int) $dak_fee_cap ) );
}

$dak_experience_options = array();

foreach ( array_keys( $experience_slabs ) as $dak_years ) {
	/* translators: %d: number of years. */
	$dak_experience_options[ $dak_years ] = sprintf( __( '%d+ years', 'doctor-ak-portal' ), (int) $dak_years );
}

// Text doctor-ak-directory.js writes (counts, chips, pages, filter buttons).
$dak_dir_strings = array(
	/* translators: 1: first shown, 2: last shown, 3: total matching. */
	'showing'     => __( 'Showing %1$s–%2$s of %3$s doctors', 'doctor-ak-portal' ),
	/* translators: %s: number of doctors. */
	'showingAll'  => __( 'Showing all %s doctors', 'doctor-ak-portal' ),
	'showingOne'  => __( 'Showing 1 doctor', 'doctor-ak-portal' ),
	'none'        => __( 'No doctors match these filters', 'doctor-ak-portal' ),
	/* translators: %s: number of matching doctors. */
	'showButton'  => __( 'Show %s doctors', 'doctor-ak-portal' ),
	/* translators: %s: filter value, e.g. "Cardiologist". */
	'remove'      => __( 'Remove filter: %s', 'doctor-ak-portal' ),
	/* translators: %s: search text. */
	'searchChip'  => __( 'Search: “%s”', 'doctor-ak-portal' ),
	/* translators: %s: page number. */
	'page'        => __( 'Page %s', 'doctor-ak-portal' ),
	/* translators: %s: city name. */
	'nearCity'    => __( 'Near me: %s', 'doctor-ak-portal' ),
	'nearMe'      => __( 'Near me', 'doctor-ak-portal' ),
	'locating'    => __( 'Finding your location…', 'doctor-ak-portal' ),
	'denied'      => __( 'Location access was not allowed, so Near me is off. You can still choose a city from the list.', 'doctor-ak-portal' ),
	'unsupported' => __( 'This browser cannot share your location. Choose a city from the list instead.', 'doctor-ak-portal' ),
	'noCity'      => __( 'We could not match your location to a city with listed doctors. Choose a city from the list instead.', 'doctor-ak-portal' ),
	/* translators: %s: number of specialties. */
	'more'        => __( 'Show all %s', 'doctor-ak-portal' ),
	'less'        => __( 'Show fewer', 'doctor-ak-portal' ),
	/* translators: %s: number of options picked in one filter, e.g. "2 selected". */
	'selected'    => __( '%s selected', 'doctor-ak-portal' ),
);

/**
 * One filter in the bar: a button showing its name (and what's picked),
 * and the panel of options it opens. In the small-screen drawer the
 * button is hidden and the panel is shown as a normal section.
 *
 * @param string $key   Group key (also the panel id suffix).
 * @param string $label Filter name.
 * @param string $body  Panel content (escaped HTML).
 * @param string $hint  Optional one-line explanation.
 */
$dak_filter_group = function ( $key, $label, $body, $hint = '' ) use ( $dak_icons ) {
	$panel_id  = 'dak-dir-panel-' . $key;
	$legend_id = 'dak-dir-legend-' . $key;
	?>
	<div class="dak-dir-fgroup" data-dak-dir-fgroup="<?php echo esc_attr( $key ); ?>">
		<button type="button" class="dak-dir-fgroup-toggle" data-dak-dir-fgroup-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $panel_id ); ?>">
			<span class="dak-dir-fgroup-name"><?php echo esc_html( $label ); ?></span>
			<span class="dak-dir-fgroup-value" data-dak-dir-fgroup-value></span>
			<span class="dak-dir-fgroup-chevron"><?php echo $dak_icons['chevron']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
		</button>
		<div class="dak-dir-fgroup-panel" id="<?php echo esc_attr( $panel_id ); ?>" data-dak-dir-fgroup-panel>
			<fieldset class="dak-dir-group" aria-labelledby="<?php echo esc_attr( $legend_id ); ?>">
				<legend class="dak-dir-group-title" id="<?php echo esc_attr( $legend_id ); ?>"><?php echo esc_html( $label ); ?></legend>
				<?php if ( '' !== $hint ) : ?>
					<p class="dak-dir-group-hint"><?php echo esc_html( $hint ); ?></p>
				<?php endif; ?>
				<?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped output below. ?>
			</fieldset>
			<div class="dak-dir-fgroup-foot">
				<button type="button" class="dak-dir-link-button" data-dak-dir-fgroup-clear><?php esc_html_e( 'Clear', 'doctor-ak-portal' ); ?></button>
				<button type="button" class="dak-dir-btn dak-dir-btn-primary dak-dir-btn-sm" data-dak-dir-fgroup-done><?php esc_html_e( 'Done', 'doctor-ak-portal' ); ?></button>
			</div>
		</div>
	</div>
	<?php
};

/**
 * A row of quick-filter chips (checkbox inputs that act as one choice).
 *
 * @param string $name    Input name.
 * @param string $title   Row label.
 * @param string $icon    Icon key.
 * @param array  $choices value => label.
 * @param array  $counts  value => doctors.
 */
$dak_quick_row = function ( $name, $title, $icon, array $choices, array $counts ) use ( $dak_icons ) {
	$title_id = 'dak-dir-quick-' . $name;
	?>
	<div class="dak-dir-quick-row" role="group" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
		<span class="dak-dir-quick-title" id="<?php echo esc_attr( $title_id ); ?>">
			<?php echo $dak_icons[ $icon ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			<?php echo esc_html( $title ); ?>
		</span>
		<?php foreach ( $choices as $value => $label ) : ?>
			<?php $dak_n = isset( $counts[ $value ] ) ? (int) $counts[ $value ] : 0; ?>
			<label class="dak-dir-pill<?php echo 0 === $dak_n ? ' is-empty' : ''; ?>">
				<input type="checkbox" class="dak-dir-pill-input" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" data-exclusive data-label="<?php echo esc_attr( $label ); ?>"<?php disabled( 0 === $dak_n ); ?>>
				<span class="dak-dir-pill-check"><?php echo $dak_icons['check']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<span class="dak-dir-pill-label"><?php echo esc_html( $label ); ?></span>
				<span class="dak-dir-pill-count" aria-hidden="true"><?php echo esc_html( number_format_i18n( $dak_n ) ); ?></span>
				<span class="dak-dir-sr">
					<?php
					/* translators: %s: number of doctors. */
					echo esc_html( sprintf( _n( '%s doctor', '%s doctors', $dak_n, 'doctor-ak-portal' ), number_format_i18n( $dak_n ) ) );
					?>
				</span>
			</label>
		<?php endforeach; ?>
	</div>
	<?php
};
?>
<div class="dak-portal dak-dir dak-dir-doctors" data-dak-dir-doctors data-strings="<?php echo esc_attr( wp_json_encode( $dak_dir_strings ) ); ?>">
	<header class="dak-dir-intro">
		<div class="dak-dir-intro-text">
			<h1 class="dak-dir-title"><?php esc_html_e( 'Find a doctor', 'doctor-ak-portal' ); ?></h1>
			<p class="dak-dir-lead"><?php esc_html_e( 'Book a clinic visit or a video consultation with our doctors.', 'doctor-ak-portal' ); ?></p>
		</div>

		<?php if ( $doctors_count > 0 ) : ?>
			<form class="dak-dir-search" role="search" data-dak-dir-search>
				<label class="dak-dir-sr" for="dak-dir-q"><?php esc_html_e( 'Search doctors by name or specialty', 'doctor-ak-portal' ); ?></label>
				<span class="dak-dir-search-icon"><?php echo $dak_icons['search']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<input type="search" id="dak-dir-q" name="q" placeholder="<?php esc_attr_e( 'Search by doctor name or specialty', 'doctor-ak-portal' ); ?>" autocomplete="off">
				<button type="button" class="dak-dir-search-clear" data-dak-dir-clear-search hidden aria-label="<?php esc_attr_e( 'Clear search', 'doctor-ak-portal' ); ?>"><?php echo $dak_icons['x']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
			</form>
		<?php endif; ?>
	</header>

	<?php if ( $doctors_count > 0 ) : ?>
		<div class="dak-dir-topfilters">
			<?php // Quick filters — always on the page (one scrolling row on phones). ?>
			<div class="dak-dir-quick" aria-label="<?php esc_attr_e( 'Quick filters', 'doctor-ak-portal' ); ?>" role="region">
				<?php $dak_quick_row( 'discount', __( 'Offers', 'doctor-ak-portal' ), 'tag', $dak_discount_chips, $discount_slabs ); ?>
				<?php $dak_quick_row( 'fee', __( 'Fee', 'doctor-ak-portal' ), 'wallet', $dak_fee_chips, $fee_slabs ); ?>
			</div>

			<?php // The filter bar on wide screens; the "Filters" drawer below 1024px. ?>
			<div class="dak-dir-filters" id="dak-dir-filters" data-dak-dir-filters data-label="<?php esc_attr_e( 'Filter doctors', 'doctor-ak-portal' ); ?>">
				<div class="dak-dir-filters-head dak-dir-drawer-only">
					<h2 class="dak-dir-filters-title"><?php esc_html_e( 'Filters', 'doctor-ak-portal' ); ?></h2>
					<button type="button" class="dak-dir-link-button" data-dak-dir-clear-all hidden><?php esc_html_e( 'Clear all', 'doctor-ak-portal' ); ?></button>
					<button type="button" class="dak-dir-icon-button" data-dak-dir-close-filters aria-label="<?php esc_attr_e( 'Close filters', 'doctor-ak-portal' ); ?>"><?php echo $dak_icons['x']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
				</div>

				<div class="dak-dir-filters-body">
					<?php if ( ! empty( $specialities ) ) : ?>
						<?php ob_start(); ?>
						<?php if ( count( $specialities ) > $dak_visible_specialities ) : ?>
							<label class="dak-dir-sr" for="dak-dir-spec-find"><?php esc_html_e( 'Find a specialty', 'doctor-ak-portal' ); ?></label>
							<input type="search" class="dak-dir-mini-search" id="dak-dir-spec-find" placeholder="<?php esc_attr_e( 'Find a specialty', 'doctor-ak-portal' ); ?>" autocomplete="off" data-dak-dir-spec-find>
						<?php endif; ?>

						<div class="dak-dir-options" data-dak-dir-spec-list>
							<label class="dak-dir-option">
								<input type="radio" name="specialty" value="" checked>
								<span class="dak-dir-option-label"><?php esc_html_e( 'All specialties', 'doctor-ak-portal' ); ?></span>
								<?php echo $dak_count( $doctors_count ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in closure. ?>
							</label>
							<?php foreach ( $specialities as $dak_i => $dak_speciality ) : ?>
								<label class="dak-dir-option" data-dak-dir-spec-option data-name="<?php echo esc_attr( $dak_speciality['slug'] ); ?>"<?php echo $dak_i >= $dak_visible_specialities ? ' data-dak-dir-extra hidden' : ''; ?>>
									<input type="radio" name="specialty" value="<?php echo esc_attr( $dak_speciality['slug'] ); ?>" data-label="<?php echo esc_attr( $dak_speciality['label'] ); ?>">
									<span class="dak-dir-option-label"><?php echo esc_html( $dak_speciality['label'] ); ?></span>
									<?php echo $dak_count( $dak_speciality['count'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in closure. ?>
								</label>
							<?php endforeach; ?>
						</div>

						<?php if ( count( $specialities ) > $dak_visible_specialities ) : ?>
							<button type="button" class="dak-dir-link-button" data-dak-dir-spec-more aria-expanded="false" data-count="<?php echo esc_attr( count( $specialities ) ); ?>">
								<?php echo esc_html( sprintf( $dak_dir_strings['more'], number_format_i18n( count( $specialities ) ) ) ); ?>
							</button>
						<?php endif; ?>
						<?php $dak_filter_group( 'specialty', __( 'Specialty', 'doctor-ak-portal' ), ob_get_clean() ); ?>
					<?php endif; ?>

					<?php ob_start(); ?>
					<div class="dak-dir-options">
						<label class="dak-dir-option">
							<input type="checkbox" name="visit" value="clinic" data-label="<?php esc_attr_e( 'Clinic visit', 'doctor-ak-portal' ); ?>">
							<span class="dak-dir-option-label"><?php esc_html_e( 'Clinic visit', 'doctor-ak-portal' ); ?></span>
							<?php echo $dak_count( $facets['clinic'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in closure. ?>
						</label>
						<label class="dak-dir-option">
							<input type="checkbox" name="visit" value="video" data-label="<?php esc_attr_e( 'Video consultation', 'doctor-ak-portal' ); ?>">
							<span class="dak-dir-option-label"><?php esc_html_e( 'Video consultation', 'doctor-ak-portal' ); ?></span>
							<?php echo $dak_count( $facets['video'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in closure. ?>
						</label>
					</div>
					<?php $dak_filter_group( 'visit', __( 'Visit type', 'doctor-ak-portal' ), ob_get_clean() ); ?>

					<?php ob_start(); ?>
					<div class="dak-dir-options">
						<label class="dak-dir-option">
							<input type="radio" name="availability" value="" checked>
							<span class="dak-dir-option-label"><?php esc_html_e( 'Any time', 'doctor-ak-portal' ); ?></span>
						</label>
						<label class="dak-dir-option">
							<input type="radio" name="availability" value="today" data-label="<?php esc_attr_e( 'Open slot today', 'doctor-ak-portal' ); ?>" data-short="<?php esc_attr_e( 'Today', 'doctor-ak-portal' ); ?>">
							<span class="dak-dir-option-label"><?php esc_html_e( 'Open slot today', 'doctor-ak-portal' ); ?></span>
							<?php echo $dak_count( $facets['today'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in closure. ?>
						</label>
						<label class="dak-dir-option">
							<input type="radio" name="availability" value="week" data-label="<?php esc_attr_e( 'Open slot in the next 7 days', 'doctor-ak-portal' ); ?>" data-short="<?php esc_attr_e( 'Next 7 days', 'doctor-ak-portal' ); ?>">
							<span class="dak-dir-option-label"><?php esc_html_e( 'Next 7 days', 'doctor-ak-portal' ); ?></span>
							<?php echo $dak_count( $facets['week'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in closure. ?>
						</label>
					</div>
					<?php $dak_filter_group( 'availability', __( 'Availability', 'doctor-ak-portal' ), ob_get_clean(), __( 'Based on session hours and appointments already booked.', 'doctor-ak-portal' ) ); ?>

					<?php ob_start(); ?>
					<div class="dak-dir-options">
						<label class="dak-dir-option">
							<input type="radio" name="experience" value="" checked>
							<span class="dak-dir-option-label"><?php esc_html_e( 'Any experience', 'doctor-ak-portal' ); ?></span>
						</label>
						<?php foreach ( $dak_experience_options as $dak_years => $dak_years_label ) : ?>
							<?php $dak_n = (int) $experience_slabs[ $dak_years ]; ?>
							<label class="dak-dir-option<?php echo 0 === $dak_n ? ' is-empty' : ''; ?>">
								<?php /* translators: %d: number of years. */ ?>
								<input type="radio" name="experience" value="<?php echo esc_attr( $dak_years ); ?>" data-label="<?php echo esc_attr( sprintf( __( '%d+ years experience', 'doctor-ak-portal' ), (int) $dak_years ) ); ?>" data-short="<?php echo esc_attr( $dak_years_label ); ?>"<?php disabled( 0 === $dak_n ); ?>>
								<span class="dak-dir-option-label"><?php echo esc_html( $dak_years_label ); ?></span>
								<?php echo $dak_count( $dak_n ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in closure. ?>
							</label>
						<?php endforeach; ?>
					</div>
					<?php $dak_filter_group( 'experience', __( 'Experience', 'doctor-ak-portal' ), ob_get_clean() ); ?>

					<?php if ( $facets['male'] + $facets['female'] > 0 ) : ?>
						<?php ob_start(); ?>
						<div class="dak-dir-options">
							<label class="dak-dir-option">
								<input type="checkbox" name="gender" value="female" data-label="<?php esc_attr_e( 'Female doctor', 'doctor-ak-portal' ); ?>" data-short="<?php esc_attr_e( 'Female', 'doctor-ak-portal' ); ?>">
								<span class="dak-dir-option-label"><?php esc_html_e( 'Female', 'doctor-ak-portal' ); ?></span>
								<?php echo $dak_count( $facets['female'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in closure. ?>
							</label>
							<label class="dak-dir-option">
								<input type="checkbox" name="gender" value="male" data-label="<?php esc_attr_e( 'Male doctor', 'doctor-ak-portal' ); ?>" data-short="<?php esc_attr_e( 'Male', 'doctor-ak-portal' ); ?>">
								<span class="dak-dir-option-label"><?php esc_html_e( 'Male', 'doctor-ak-portal' ); ?></span>
								<?php echo $dak_count( $facets['male'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in closure. ?>
							</label>
						</div>
						<?php $dak_filter_group( 'gender', __( 'Gender', 'doctor-ak-portal' ), ob_get_clean() ); ?>
					<?php endif; ?>

					<?php if ( ! empty( $cities ) ) : ?>
						<?php ob_start(); ?>
						<label class="dak-dir-sr" for="dak-dir-city"><?php esc_html_e( 'City', 'doctor-ak-portal' ); ?></label>
						<select id="dak-dir-city" name="city" class="dak-dir-select">
							<option value=""><?php esc_html_e( 'All cities', 'doctor-ak-portal' ); ?></option>
							<?php foreach ( $cities as $dak_city ) : ?>
								<option value="<?php echo esc_attr( $dak_city['slug'] ); ?>" data-label="<?php echo esc_attr( $dak_city['label'] ); ?>">
									<?php echo esc_html( sprintf( '%s (%s)', $dak_city['label'], number_format_i18n( $dak_city['count'] ) ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<button type="button" class="dak-dir-nearme" data-dak-dir-nearme aria-pressed="false">
							<?php echo $dak_icons['pin']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
							<span data-dak-dir-nearme-label><?php esc_html_e( 'Near me', 'doctor-ak-portal' ); ?></span>
						</button>
						<p class="dak-dir-group-hint" data-dak-dir-nearme-status role="status" hidden></p>
						<?php $dak_filter_group( 'location', __( 'Location', 'doctor-ak-portal' ), ob_get_clean() ); ?>
					<?php endif; ?>

					<button type="button" class="dak-dir-link-button dak-dir-bar-clear" data-dak-dir-clear-all hidden><?php esc_html_e( 'Clear all', 'doctor-ak-portal' ); ?></button>
				</div>

				<div class="dak-dir-filters-foot dak-dir-drawer-only">
					<button type="button" class="dak-dir-btn dak-dir-btn-primary dak-dir-btn-block" data-dak-dir-close-filters data-dak-dir-show-results><?php echo esc_html( sprintf( $dak_dir_strings['showButton'], number_format_i18n( $doctors_count ) ) ); ?></button>
				</div>
			</div>
			<div class="dak-dir-scrim" data-dak-dir-close-filters hidden></div>
		</div>

		<section class="dak-dir-results" aria-labelledby="dak-dir-count">
			<div class="dak-dir-toolbar">
				<button type="button" class="dak-dir-btn dak-dir-btn-secondary dak-dir-filters-open" data-dak-dir-open-filters aria-controls="dak-dir-filters" aria-expanded="false">
					<?php echo $dak_icons['sliders']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					<span><?php esc_html_e( 'Filters', 'doctor-ak-portal' ); ?></span>
					<span class="dak-dir-badge" data-dak-dir-filter-count hidden></span>
				</button>

				<p class="dak-dir-count" id="dak-dir-count" data-dak-dir-count aria-live="polite" aria-atomic="true">
					<?php echo esc_html( sprintf( $dak_dir_strings['showing'], 1, number_format_i18n( min( 12, $doctors_count ) ), number_format_i18n( $doctors_count ) ) ); ?>
				</p>

				<div class="dak-dir-sort">
					<label for="dak-dir-sort"><?php esc_html_e( 'Sort by', 'doctor-ak-portal' ); ?></label>
					<select id="dak-dir-sort" name="sort" class="dak-dir-select">
						<option value="experience"><?php esc_html_e( 'Most experienced', 'doctor-ak-portal' ); ?></option>
						<option value="available"><?php esc_html_e( 'Soonest available', 'doctor-ak-portal' ); ?></option>
						<option value="fee-asc"><?php esc_html_e( 'Lowest fee', 'doctor-ak-portal' ); ?></option>
						<option value="discount"><?php esc_html_e( 'Biggest discount', 'doctor-ak-portal' ); ?></option>
						<option value="name-asc"><?php esc_html_e( 'Name (A–Z)', 'doctor-ak-portal' ); ?></option>
						<option value="name-desc"><?php esc_html_e( 'Name (Z–A)', 'doctor-ak-portal' ); ?></option>
					</select>
				</div>
			</div>

			<div class="dak-dir-active" data-dak-dir-active hidden>
				<ul class="dak-dir-chips" data-dak-dir-chips></ul>
				<button type="button" class="dak-dir-link-button" data-dak-dir-clear-all><?php esc_html_e( 'Clear all', 'doctor-ak-portal' ); ?></button>
			</div>

			<ul class="dak-dir-doctor-list" data-dak-dir-list>
				<?php foreach ( $doctors_html as $dak_card_html ) : ?>
					<?php echo $dak_card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- card partial escapes its own output. ?>
				<?php endforeach; ?>
			</ul>

			<div class="dak-dir-empty" data-dak-dir-empty hidden>
				<p class="dak-dir-empty-title"><?php esc_html_e( 'No doctors match these filters', 'doctor-ak-portal' ); ?></p>
				<p class="dak-dir-empty-text"><?php esc_html_e( 'Try removing a filter or searching for a different name or specialty.', 'doctor-ak-portal' ); ?></p>
				<button type="button" class="dak-dir-btn dak-dir-btn-secondary" data-dak-dir-clear-all><?php esc_html_e( 'Clear all filters', 'doctor-ak-portal' ); ?></button>
			</div>

			<nav class="dak-dir-pagination" data-dak-dir-pagination aria-label="<?php esc_attr_e( 'Doctor results pages', 'doctor-ak-portal' ); ?>" hidden>
				<button type="button" class="dak-dir-page-step" data-dak-dir-page="prev">
					<?php echo $dak_icons['arrow_l']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					<span><?php esc_html_e( 'Previous', 'doctor-ak-portal' ); ?></span>
				</button>
				<ol class="dak-dir-pages" data-dak-dir-pages></ol>
				<button type="button" class="dak-dir-page-step" data-dak-dir-page="next">
					<span><?php esc_html_e( 'Next', 'doctor-ak-portal' ); ?></span>
					<?php echo $dak_icons['arrow_r']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				</button>
			</nav>
		</section>
	<?php else : ?>
		<p class="dak-dir-empty-title"><?php esc_html_e( 'No doctors are listed yet. Please check back soon.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</div>
