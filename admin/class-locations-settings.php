<?php
/**
 * Settings → Locations admin page.
 *
 * @package DoctorAKPortal\Admin
 */

namespace DoctorAKPortal\Admin;

use DoctorAKPortal\Includes\Assets;
use DoctorAKPortal\Includes\Locations;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Locations_Settings
 *
 * Lets an administrator maintain the City -> Areas list used by doctor
 * location fields (registration, profile, clinics) and the doctors
 * directory's location filter. One repeatable row per city: a name field
 * plus a newline-separated textarea of that city's areas.
 */
class Locations_Settings {

	/**
	 * Registers the settings page under Settings.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_options_page(
			__( 'Locations', 'doctor-ak-portal' ),
			__( 'Locations', 'doctor-ak-portal' ),
			'manage_options',
			'doctor-ak-locations-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registers the option with the Settings API.
	 *
	 * @return void
	 */
	public function register_settings() {
		// No 'default' arg here — Locations::get_all() owns the "not saved
		// yet" fallback (the seed cities) via its own false-vs-array()
		// distinction on get_option(), which a registered default here
		// would short-circuit (WP applies it through a `default_option_*`
		// filter regardless of the $default passed to get_option()).
		register_setting(
			'doctor_ak_locations_settings',
			Locations::OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	/**
	 * Converts the posted `dak_locations[name][]` / `dak_locations[areas][]`
	 * parallel arrays into the shape Locations::get_all() expects.
	 *
	 * @param mixed $value Raw posted value.
	 * @return array
	 */
	public function sanitize( $value ) {
		$names = ( is_array( $value ) && isset( $value['name'] ) && is_array( $value['name'] ) ) ? $value['name'] : array();
		$areas = ( is_array( $value ) && isset( $value['areas'] ) && is_array( $value['areas'] ) ) ? $value['areas'] : array();

		return Locations::sanitize_from_request( $names, $areas );
	}

	/**
	 * Enqueues the repeatable-row editor script, only on this settings page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_doctor-ak-locations-settings' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'doctor-ak-portal-locations-settings',
			DOCTOR_AK_PORTAL_URL . 'assets/js/doctor-ak-locations-settings.js',
			array(),
			Assets::version( 'assets/js/doctor-ak-locations-settings.js' ),
			true
		);

		wp_localize_script(
			'doctor-ak-portal-locations-settings',
			'dakLocationsSettings',
			array(
				'defaultCities' => Locations::default_seed_data(),
			)
		);
	}

	/**
	 * Renders the settings form.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$cities = Locations::get_all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Locations', 'doctor-ak-portal' ); ?></h1>
			<p><?php esc_html_e( 'Cities and areas doctors can pick from for their location, and patients can filter the doctors directory by. Enter one area per line for each city.', 'doctor-ak-portal' ); ?></p>

			<p>
				<button type="button" class="button" id="dak-locations-load-defaults"><?php esc_html_e( 'Load Default Cities (Pakistan)', 'doctor-ak-portal' ); ?></button>
				<span class="description"><?php esc_html_e( "Fills in a starting list of major Pakistani cities and their well-known areas below — review and edit before saving. This doesn't save anything by itself.", 'doctor-ak-portal' ); ?></span>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'doctor_ak_locations_settings' ); ?>

				<div id="dak-locations-rows">
					<?php if ( empty( $cities ) ) : ?>
						<p class="description" id="dak-locations-empty"><?php esc_html_e( 'No cities added yet.', 'doctor-ak-portal' ); ?></p>
					<?php endif; ?>
					<?php foreach ( $cities as $city ) : ?>
						<div class="dak-locations-row" style="display:flex;gap:16px;align-items:flex-start;padding:16px 0;border-bottom:1px solid #dcdcde;">
							<div style="flex:0 0 220px;">
								<label style="font-weight:600;display:block;margin-bottom:4px;"><?php esc_html_e( 'City', 'doctor-ak-portal' ); ?></label>
								<input type="text" name="<?php echo esc_attr( Locations::OPTION_KEY ); ?>[name][]" value="<?php echo esc_attr( $city['name'] ); ?>" class="regular-text">
							</div>
							<div style="flex:1 1 auto;">
								<label style="font-weight:600;display:block;margin-bottom:4px;"><?php esc_html_e( 'Areas (one per line)', 'doctor-ak-portal' ); ?></label>
								<textarea name="<?php echo esc_attr( Locations::OPTION_KEY ); ?>[areas][]" rows="4" style="width:100%;"><?php echo esc_textarea( implode( "\n", wp_list_pluck( $city['areas'], 'name' ) ) ); ?></textarea>
							</div>
							<button type="button" class="button" data-locations-remove-row style="margin-top:24px;"><?php esc_html_e( 'Remove', 'doctor-ak-portal' ); ?></button>
						</div>
					<?php endforeach; ?>
				</div>

				<p>
					<button type="button" class="button" id="dak-locations-add-row"><?php esc_html_e( '+ Add City', 'doctor-ak-portal' ); ?></button>
				</p>

				<template id="dak-locations-row-template">
					<div class="dak-locations-row" style="display:flex;gap:16px;align-items:flex-start;padding:16px 0;border-bottom:1px solid #dcdcde;">
						<div style="flex:0 0 220px;">
							<label style="font-weight:600;display:block;margin-bottom:4px;"><?php esc_html_e( 'City', 'doctor-ak-portal' ); ?></label>
							<input type="text" name="<?php echo esc_attr( Locations::OPTION_KEY ); ?>[name][]" value="" class="regular-text">
						</div>
						<div style="flex:1 1 auto;">
							<label style="font-weight:600;display:block;margin-bottom:4px;"><?php esc_html_e( 'Areas (one per line)', 'doctor-ak-portal' ); ?></label>
							<textarea name="<?php echo esc_attr( Locations::OPTION_KEY ); ?>[areas][]" rows="4" style="width:100%;"></textarea>
						</div>
						<button type="button" class="button" data-locations-remove-row style="margin-top:24px;"><?php esc_html_e( 'Remove', 'doctor-ak-portal' ); ?></button>
					</div>
				</template>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
