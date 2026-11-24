<?php
/**
 * Template: the [doctors_directory] page's first step — every specialty as
 * a tile, with a search box that narrows the tiles as you type (by name,
 * or by plain words such as "heart" or "skin"). A tile opens the doctor
 * list (doctors-directory.php) filtered to that specialty; "Show all
 * doctors" opens it unfiltered.
 *
 * Without JavaScript the tiles are ordinary links and the search box
 * submits ?q= to the doctor list, which searches names and specialties.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array  $specialities    { slug, label, count, url, icon (static SVG), keywords }, alphabetical.
 * @var int    $doctors_count   Number of listed doctors.
 * @var string $page_url        This page's URL (the search form's target).
 * @var string $all_doctors_url The unfiltered doctor list (?view=all).
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dak_spec_icon = function ( $paths ) {
	return '<svg viewBox="0 0 20 20" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths . '</svg>';
};

$dak_spec_icons = array(
	'search'  => $dak_spec_icon( '<circle cx="8.8" cy="8.8" r="5.3"/><path d="M17 17l-3.8-3.8"/>' ),
	'x'       => $dak_spec_icon( '<path d="M5.5 5.5l9 9M14.5 5.5l-9 9"/>' ),
	'arrow_r' => $dak_spec_icon( '<path d="M7.5 4.5 13 10l-5.5 5.5"/>' ),
	'users'   => $dak_spec_icon( '<circle cx="7.5" cy="7" r="2.8"/><path d="M2.5 16c.7-2.8 2.7-4.2 5-4.2s4.3 1.4 5 4.2"/><path d="M12.8 4.4a2.6 2.6 0 0 1 0 5.2"/><path d="M14.4 12.1c1.5.5 2.6 1.7 3.1 3.9"/>' ),
);

$dak_spec_total = count( $specialities );

// Text doctor-ak-directory.js writes (the live count and the empty state).
$dak_spec_strings = array(
	/* translators: %s: number of specialties. */
	'count'     => __( '%s specialties', 'doctor-ak-portal' ),
	'countOne'  => __( '1 specialty', 'doctor-ak-portal' ),
	/* translators: 1: number of matching specialties, 2: total specialties. */
	'matching'  => __( '%1$s of %2$s specialties match', 'doctor-ak-portal' ),
	/* translators: %s: what the visitor typed. */
	'noMatch'   => __( 'No specialty matches “%s”', 'doctor-ak-portal' ),
	/* translators: %s: what the visitor typed. */
	'searchFor' => __( 'Search doctors for “%s”', 'doctor-ak-portal' ),
);
?>
<div class="dak-portal dak-dir dak-dir-doctors dak-dir-specs" data-dak-spec-picker data-strings="<?php echo esc_attr( wp_json_encode( $dak_spec_strings ) ); ?>">
	<header class="dak-dir-intro">
		<div class="dak-dir-intro-text">
			<h1 class="dak-dir-title"><?php esc_html_e( 'Find a doctor by specialty', 'doctor-ak-portal' ); ?></h1>
			<p class="dak-dir-lead"><?php esc_html_e( 'Choose a specialty to see the doctors who practise it, then book a clinic visit or a video consultation.', 'doctor-ak-portal' ); ?></p>
		</div>

		<form class="dak-dir-search" role="search" method="get" action="<?php echo esc_url( strtok( $page_url, '?' ) ); ?>" data-dak-spec-search>
			<?php
			// Keep a plain-permalink page id (?page_id=12) when the form submits.
			$dak_spec_query = wp_parse_url( $page_url, PHP_URL_QUERY );

			if ( $dak_spec_query ) {
				parse_str( $dak_spec_query, $dak_spec_args );

				foreach ( $dak_spec_args as $dak_arg_name => $dak_arg_value ) {
					if ( is_scalar( $dak_arg_value ) ) {
						echo '<input type="hidden" name="' . esc_attr( $dak_arg_name ) . '" value="' . esc_attr( $dak_arg_value ) . '">';
					}
				}
			}
			?>
			<label class="dak-dir-sr" for="dak-spec-q"><?php esc_html_e( 'Search specialties', 'doctor-ak-portal' ); ?></label>
			<span class="dak-dir-search-icon"><?php echo $dak_spec_icons['search']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
			<input type="search" id="dak-spec-q" name="q" placeholder="<?php esc_attr_e( 'Search a specialty, e.g. Cardiologist or “skin”', 'doctor-ak-portal' ); ?>" autocomplete="off" aria-describedby="dak-spec-count">
			<button type="button" class="dak-dir-search-clear" data-dak-spec-clear hidden aria-label="<?php esc_attr_e( 'Clear search', 'doctor-ak-portal' ); ?>"><?php echo $dak_spec_icons['x']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
		</form>
	</header>

	<div class="dak-spec-toolbar">
		<p class="dak-dir-count" id="dak-spec-count" data-dak-spec-count aria-live="polite" aria-atomic="true">
			<?php echo esc_html( 1 === $dak_spec_total ? $dak_spec_strings['countOne'] : sprintf( $dak_spec_strings['count'], number_format_i18n( $dak_spec_total ) ) ); ?>
		</p>
		<a class="dak-dir-btn dak-dir-btn-secondary dak-spec-all" href="<?php echo esc_url( $all_doctors_url ); ?>">
			<?php echo $dak_spec_icons['users']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			<span>
				<?php
				/* translators: %s: number of doctors. */
				echo esc_html( sprintf( _n( 'Show all %s doctor', 'Show all %s doctors', $doctors_count, 'doctor-ak-portal' ), number_format_i18n( $doctors_count ) ) );
				?>
			</span>
		</a>
	</div>

	<ul class="dak-spec-grid" data-dak-spec-list>
		<?php foreach ( $specialities as $dak_speciality ) : ?>
			<li class="dak-spec-item" data-dak-spec-item data-search="<?php echo esc_attr( trim( mb_strtolower( $dak_speciality['label'] ) . ' ' . $dak_speciality['keywords'] ) ); ?>">
				<a class="dak-spec-tile" href="<?php echo esc_url( $dak_speciality['url'] ); ?>">
					<span class="dak-spec-icon"><?php echo $dak_speciality['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG built in Doctors_Directory::specialty_icon(). ?></span>
					<span class="dak-spec-text">
						<span class="dak-spec-name"><?php echo esc_html( $dak_speciality['label'] ); ?></span>
						<span class="dak-spec-meta">
							<?php
							/* translators: %s: number of doctors with this specialty. */
							echo esc_html( sprintf( _n( '%s doctor', '%s doctors', $dak_speciality['count'], 'doctor-ak-portal' ), number_format_i18n( $dak_speciality['count'] ) ) );
							?>
						</span>
					</span>
					<span class="dak-spec-go"><?php echo $dak_spec_icons['arrow_r']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<div class="dak-dir-empty" data-dak-spec-empty hidden>
		<p class="dak-dir-empty-title" data-dak-spec-empty-title></p>
		<p class="dak-dir-empty-text"><?php esc_html_e( 'Check the spelling, or search doctors by name instead.', 'doctor-ak-portal' ); ?></p>
		<a class="dak-dir-btn dak-dir-btn-secondary" href="<?php echo esc_url( $all_doctors_url ); ?>" data-dak-spec-search-link data-base-url="<?php echo esc_url( $page_url ); ?>"></a>
	</div>
</div>
