<?php
/**
 * Template: Doctors directory for the [doctors_directory] shortcode.
 *
 * Search on top; filters (specialty, visit type, availability, gender,
 * location) in a sidebar that becomes a labelled drawer on small screens;
 * results with a live "Showing 1–12 of 53 doctors" count, sorting and
 * pagination. Every card is rendered here; doctor-ak-directory.js filters
 * and sorts the full set before paginating, and keeps the state in the URL
 * so Back from a profile returns to the same list.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string[] $doctors_html  Pre-rendered directory/doctor-directory-card.php output, most experienced first.
 * @var array    $specialities  { slug (lowercase label), label, count }, alphabetical.
 * @var array    $cities        { slug, label, count }, most doctors first.
 * @var array    $facets        Doctors per option: clinic, video, today, week, male, female.
 * @var int      $doctors_count Number of listed doctors.
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
);

$dak_visible_specialities = 8;
$dak_count                = function ( $n ) {
	return '<span class="dak-dir-option-count">' . esc_html( number_format_i18n( $n ) ) . '</span>';
};

// Text doctor-ak-directory.js writes (counts, chips, pages).
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
);
?>
<div class="dak-portal dak-dir dak-dir-doctors" data-dak-dir-doctors data-strings="<?php echo esc_attr( wp_json_encode( $dak_dir_strings ) ); ?>">
	<header class="dak-dir-intro">
		<div class="dak-dir-intro-text">
			<h1 class="dak-dir-title"><?php esc_html_e( 'Find a doctor', 'doctor-ak-portal' ); ?></h1>
			<p class="dak-dir-lead">
				<?php
				if ( $doctors_count > 0 ) {
					echo esc_html(
						sprintf(
							/* translators: 1: number of doctors, 2: number of specialties. */
							__( '%1$s doctors across %2$s specialties. Book a clinic visit or a video consultation.', 'doctor-ak-portal' ),
							number_format_i18n( $doctors_count ),
							number_format_i18n( count( $specialities ) )
						)
					);
				} else {
					esc_html_e( 'Book a clinic visit or a video consultation with our doctors.', 'doctor-ak-portal' );
				}
				?>
			</p>
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
		<div class="dak-dir-layout">
			<div class="dak-dir-filters" id="dak-dir-filters" data-dak-dir-filters data-label="<?php esc_attr_e( 'Filter doctors', 'doctor-ak-portal' ); ?>">
				<div class="dak-dir-filters-head">
					<h2 class="dak-dir-filters-title"><?php esc_html_e( 'Filters', 'doctor-ak-portal' ); ?></h2>
					<button type="button" class="dak-dir-link-button" data-dak-dir-clear-all hidden><?php esc_html_e( 'Clear all', 'doctor-ak-portal' ); ?></button>
					<button type="button" class="dak-dir-icon-button dak-dir-drawer-only" data-dak-dir-close-filters aria-label="<?php esc_attr_e( 'Close filters', 'doctor-ak-portal' ); ?>"><?php echo $dak_icons['x']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
				</div>

				<div class="dak-dir-filters-body">
					<?php if ( ! empty( $specialities ) ) : ?>
						<fieldset class="dak-dir-group">
							<legend class="dak-dir-group-title"><?php esc_html_e( 'Specialty', 'doctor-ak-portal' ); ?></legend>

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
						</fieldset>
					<?php endif; ?>

					<fieldset class="dak-dir-group">
						<legend class="dak-dir-group-title"><?php esc_html_e( 'Visit type', 'doctor-ak-portal' ); ?></legend>
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
					</fieldset>

					<fieldset class="dak-dir-group">
						<legend class="dak-dir-group-title"><?php esc_html_e( 'Availability', 'doctor-ak-portal' ); ?></legend>
						<p class="dak-dir-group-hint"><?php esc_html_e( 'Based on session hours and appointments already booked.', 'doctor-ak-portal' ); ?></p>
						<div class="dak-dir-options">
							<label class="dak-dir-option">
								<input type="radio" name="availability" value="" checked>
								<span class="dak-dir-option-label"><?php esc_html_e( 'Any time', 'doctor-ak-portal' ); ?></span>
							</label>
							<label class="dak-dir-option">
								<input type="radio" name="availability" value="today" data-label="<?php esc_attr_e( 'Open slot today', 'doctor-ak-portal' ); ?>">
								<span class="dak-dir-option-label"><?php esc_html_e( 'Open slot today', 'doctor-ak-portal' ); ?></span>
								<?php echo $dak_count( $facets['today'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in closure. ?>
							</label>
							<label class="dak-dir-option">
								<input type="radio" name="availability" value="week" data-label="<?php esc_attr_e( 'Open slot in the next 7 days', 'doctor-ak-portal' ); ?>">
								<span class="dak-dir-option-label"><?php esc_html_e( 'Next 7 days', 'doctor-ak-portal' ); ?></span>
								<?php echo $dak_count( $facets['week'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in closure. ?>
							</label>
						</div>
					</fieldset>

					<?php if ( $facets['male'] + $facets['female'] > 0 ) : ?>
						<fieldset class="dak-dir-group">
							<legend class="dak-dir-group-title"><?php esc_html_e( 'Doctor gender', 'doctor-ak-portal' ); ?></legend>
							<div class="dak-dir-options">
								<label class="dak-dir-option">
									<input type="checkbox" name="gender" value="female" data-label="<?php esc_attr_e( 'Female doctor', 'doctor-ak-portal' ); ?>">
									<span class="dak-dir-option-label"><?php esc_html_e( 'Female', 'doctor-ak-portal' ); ?></span>
									<?php echo $dak_count( $facets['female'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in closure. ?>
								</label>
								<label class="dak-dir-option">
									<input type="checkbox" name="gender" value="male" data-label="<?php esc_attr_e( 'Male doctor', 'doctor-ak-portal' ); ?>">
									<span class="dak-dir-option-label"><?php esc_html_e( 'Male', 'doctor-ak-portal' ); ?></span>
									<?php echo $dak_count( $facets['male'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in closure. ?>
								</label>
							</div>
						</fieldset>
					<?php endif; ?>

					<?php if ( ! empty( $cities ) ) : ?>
						<fieldset class="dak-dir-group">
							<legend class="dak-dir-group-title"><?php esc_html_e( 'Location', 'doctor-ak-portal' ); ?></legend>
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
						</fieldset>
					<?php endif; ?>
				</div>

				<div class="dak-dir-filters-foot dak-dir-drawer-only">
					<button type="button" class="dak-dir-btn dak-dir-btn-primary dak-dir-btn-block" data-dak-dir-close-filters data-dak-dir-show-results><?php echo esc_html( sprintf( $dak_dir_strings['showButton'], number_format_i18n( $doctors_count ) ) ); ?></button>
				</div>
			</div>
			<div class="dak-dir-scrim" data-dak-dir-close-filters hidden></div>

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
		</div>
	<?php else : ?>
		<p class="dak-dir-empty-title"><?php esc_html_e( 'No doctors are listed yet. Please check back soon.', 'doctor-ak-portal' ); ?></p>
	<?php endif; ?>
</div>
