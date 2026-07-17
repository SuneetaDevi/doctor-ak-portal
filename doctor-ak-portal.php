<?php
/**
 * Plugin Name:       Doctor AK Portal
 * Plugin URI:        https://draklohana.com
 * Description:       Core authentication and user management system for Doctor AK Lohana's website. Provides doctor/patient registration, login, dashboards, profile and availability management via shortcodes. Renders body content only — the active theme supplies header, navigation and footer.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Doctor AK Lohana
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       doctor-ak-portal
 * Domain Path:       /languages
 */

namespace DoctorAKPortal;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'DOCTOR_AK_PORTAL_VERSION', '1.0.0' );
define( 'DOCTOR_AK_PORTAL_FILE', __FILE__ );
define( 'DOCTOR_AK_PORTAL_PATH', plugin_dir_path( __FILE__ ) );
define( 'DOCTOR_AK_PORTAL_URL', plugin_dir_url( __FILE__ ) );
define( 'DOCTOR_AK_PORTAL_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The autoloader cannot be autoloaded, so it is required directly.
 */
require_once DOCTOR_AK_PORTAL_PATH . 'includes/class-autoloader.php';
Autoloader::register();

/**
 * Runs on plugin activation.
 *
 * @return void
 */
function activate_doctor_ak_portal() {
	Includes\Activator::activate();
}

/**
 * Runs on plugin deactivation.
 *
 * @return void
 */
function deactivate_doctor_ak_portal() {
	Includes\Deactivator::deactivate();
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate_doctor_ak_portal' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate_doctor_ak_portal' );

/**
 * Bootstraps and runs the plugin.
 *
 * @return void
 */
function run_doctor_ak_portal() {
	$plugin = new Includes\Plugin();
	$plugin->run();
}
run_doctor_ak_portal();
