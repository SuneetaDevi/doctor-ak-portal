<?php
/**
 * Template: shared "Doctor details" dialog for the admin Doctors directory.
 *
 * One empty shell, filled client-side by doctor-ak-admin-dashboard.js from
 * the clicked row's own <template data-admin-doctor-template> (see
 * admin-doctors-table.php). Printed outside #dak-admin-users-tab-content so
 * the live filter never replaces it. Escape, focus trap and focus return are
 * provided for every .dak-modal by doctor-ak-dashboard-ui.js.
 *
 * @package DoctorAKPortal\Templates
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-portal dak-modal" id="dak-admin-doctor-view-modal" aria-hidden="true">
	<div class="dak-modal-overlay" data-dak-admin-doctor-view-close></div>

	<div class="dak-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="dak-admin-doctor-view-title">
		<button type="button" class="dak-modal-close" data-dak-admin-doctor-view-close aria-label="<?php esc_attr_e( 'Close', 'doctor-ak-portal' ); ?>">&times;</button>

		<div class="dak-modal-header">
			<h2 id="dak-admin-doctor-view-title"><?php esc_html_e( 'Doctor details', 'doctor-ak-portal' ); ?></h2>
		</div>

		<div class="dak-modal-body" id="dak-admin-doctor-view-body"></div>
	</div>
</div>
