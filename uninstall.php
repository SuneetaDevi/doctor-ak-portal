<?php
/**
 * Fired when the plugin is uninstalled (deleted) via the WordPress admin.
 *
 * Further cleanup (plugin options, custom user meta) will be added here
 * as each feature that creates it is introduced in later phases.
 *
 * @package DoctorAKPortal
 */

// If uninstall is not called from WordPress, bail.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'DOCTOR_AK_PORTAL_PATH' ) ) {
	define( 'DOCTOR_AK_PORTAL_PATH', plugin_dir_path( __FILE__ ) );
}

require_once DOCTOR_AK_PORTAL_PATH . 'includes/class-autoloader.php';
\DoctorAKPortal\Autoloader::register();

// Removing the roles does not delete the users who held them; WordPress
// simply leaves those accounts without a role, consistent with core
// behaviour whenever a role is removed.
\DoctorAKPortal\Includes\Roles::remove_roles();
