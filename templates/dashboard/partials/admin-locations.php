<?php
/**
 * Template: Admin dashboard "Locations" section — maintains the
 * Country -> City -> Area list used by doctor location fields
 * (registration, profile, clinics) and the doctors directory's location
 * filter. Repeatable-row editor at every level (add/remove Country, City,
 * and Area), matching the doctor profile's "Awards & Recognition" editor
 * pattern — submitted rows are serialized client-side into the same
 * `name[]` / `cities[]` shape Locations::sanitize_from_request() already
 * expects, so the AJAX handler needed no changes.
 *
 * @package DoctorAKPortal\Templates
 *
 * @var array $countries     Every country, see Locations::get_all().
 * @var array $country_names Suggested country names for autocomplete, see Locations::all_country_names().
 * @var array $city_names    Suggested city names for autocomplete, see Locations::suggested_city_names().
 * @var array $area_names    Suggested area names for autocomplete, see Locations::suggested_area_names().
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$country_names = isset( $country_names ) ? $country_names : array();
$city_names    = isset( $city_names ) ? $city_names : array();
$area_names    = isset( $area_names ) ? $area_names : array();
?>
<div class="dak-list-page dak-form-page">
<div class="dak-page-head">
	<div>
		<h1><?php esc_html_e( 'Locations', 'doctor-ak-portal' ); ?></h1>
		<p><?php esc_html_e( 'Countries, cities and areas doctors pick from for their location, and patients filter the doctors directory by.', 'doctor-ak-portal' ); ?></p>
	</div>
</div>

<datalist id="dak-country-datalist">
	<?php foreach ( $country_names as $dak_country_name ) : ?>
		<option value="<?php echo esc_attr( $dak_country_name ); ?>"></option>
	<?php endforeach; ?>
</datalist>
<datalist id="dak-city-datalist">
	<?php foreach ( $city_names as $dak_city_name ) : ?>
		<option value="<?php echo esc_attr( $dak_city_name ); ?>"></option>
	<?php endforeach; ?>
</datalist>
<datalist id="dak-area-datalist">
	<?php foreach ( $area_names as $dak_area_name ) : ?>
		<option value="<?php echo esc_attr( $dak_area_name ); ?>"></option>
	<?php endforeach; ?>
</datalist>

<section class="dak-results dak-locations-editor" id="dak-locations-form" aria-labelledby="dak-locations-title">
	<div class="dak-results-tools">
		<h2 class="dak-results-title" id="dak-locations-title"><?php esc_html_e( 'Country → City → Area', 'doctor-ak-portal' ); ?><span class="dak-results-count"><?php echo esc_html( sprintf( /* translators: %d: number of countries. */ _n( '%d country', '%d countries', count( $countries ), 'doctor-ak-portal' ), count( $countries ) ) ); ?></span></h2>
		<div class="dak-results-tools-actions">
			<button type="button" class="dak-button dak-button-secondary dak-button-sm" id="dak-locations-load-defaults"><?php esc_html_e( 'Load default list (Pakistan)', 'doctor-ak-portal' ); ?></button>
			<button type="button" class="dak-button dak-button-secondary dak-button-sm" id="dak-locations-add-country"><?php esc_html_e( '+ Add Country', 'doctor-ak-portal' ); ?></button>
		</div>
	</div>

	<div class="dak-results-body">
		<div class="dak-alert dak-alert-error dak-hidden" id="dak-locations-error" role="alert"></div>
		<div class="dak-alert dak-alert-success dak-hidden" id="dak-locations-success" role="status"></div>
		<p class="dak-field-hint dak-locations-hint"><?php esc_html_e( '"Load default list" fills in a starting list below to review and edit — it doesn’t save anything by itself.', 'doctor-ak-portal' ); ?></p>

		<div class="dak-countries-rows" data-countries-rows>
			<?php if ( empty( $countries ) ) : ?>
				<p class="dak-empty-state" id="dak-locations-empty"><?php esc_html_e( 'No countries added yet.', 'doctor-ak-portal' ); ?></p>
			<?php endif; ?>
			<?php foreach ( $countries as $country ) : ?>
				<div class="dak-country-row" data-country-row>
					<div class="dak-country-row-header">
						<input type="text" class="dak-country-name" list="dak-country-datalist" placeholder="<?php esc_attr_e( 'Country name', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Country name', 'doctor-ak-portal' ); ?>" value="<?php echo esc_attr( $country['name'] ); ?>">
						<button type="button" class="dak-awards-remove" data-remove-country aria-label="<?php echo esc_attr( sprintf( /* translators: %s: country. */ __( 'Remove country %s', 'doctor-ak-portal' ), $country['name'] ) ); ?>" title="<?php esc_attr_e( 'Remove country', 'doctor-ak-portal' ); ?>">&times;</button>
					</div>

					<div class="dak-cities-rows" data-cities-rows>
						<?php foreach ( $country['cities'] as $city ) : ?>
							<div class="dak-city-row" data-city-row>
								<input type="text" class="dak-city-name" list="dak-city-datalist" placeholder="<?php esc_attr_e( 'City name', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'City name', 'doctor-ak-portal' ); ?>" value="<?php echo esc_attr( $city['name'] ); ?>">

								<div class="dak-areas-rows" data-areas-rows>
									<?php foreach ( $city['areas'] as $area ) : ?>
										<span class="dak-area-chip" data-area-row>
											<input type="text" class="dak-area-name" list="dak-area-datalist" placeholder="<?php esc_attr_e( 'Area', 'doctor-ak-portal' ); ?>" aria-label="<?php esc_attr_e( 'Area name', 'doctor-ak-portal' ); ?>" value="<?php echo esc_attr( $area['name'] ); ?>">
											<button type="button" class="dak-awards-remove" data-remove-area aria-label="<?php echo esc_attr( sprintf( /* translators: %s: area. */ __( 'Remove area %s', 'doctor-ak-portal' ), $area['name'] ) ); ?>" title="<?php esc_attr_e( 'Remove area', 'doctor-ak-portal' ); ?>">&times;</button>
										</span>
									<?php endforeach; ?>
								</div>

								<button type="button" class="dak-button dak-button-secondary dak-button-sm" data-add-area>
									<?php esc_html_e( '+ Add Area', 'doctor-ak-portal' ); ?>
								</button>
								<button type="button" class="dak-awards-remove" data-remove-city aria-label="<?php echo esc_attr( sprintf( /* translators: %s: city. */ __( 'Remove city %s', 'doctor-ak-portal' ), $city['name'] ) ); ?>" title="<?php esc_attr_e( 'Remove city', 'doctor-ak-portal' ); ?>">&times;</button>
							</div>
						<?php endforeach; ?>
					</div>

					<button type="button" class="dak-button dak-button-secondary dak-button-sm" data-add-city>
						<?php esc_html_e( '+ Add City', 'doctor-ak-portal' ); ?>
					</button>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="dak-results-footer">
		<button type="button" class="dak-button dak-button-primary" id="dak-locations-save">
			<span class="dak-button-label"><?php esc_html_e( 'Save locations', 'doctor-ak-portal' ); ?></span>
		</button>
	</div>
</section>
</div>