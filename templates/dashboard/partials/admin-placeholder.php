<?php
/**
 * Template: Generic "coming soon" placeholder for admin dashboard sections
 * that appear in the sidebar but aren't backed by a real feature yet
 * (Appointments, Encounters, Receptionist, Clinic, Services, Doctor Sessions).
 *
 * @package DoctorAKPortal\Templates
 *
 * @var string $section_label Human-readable section name, e.g. 'Appointments'.
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="dak-dashboard-greeting">
	<h1><?php echo esc_html( $section_label ); ?></h1>
</div>

<section class="dak-dashboard-card dak-admin-placeholder-card">
	<p class="dak-empty-state">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %s: section name, e.g. "Appointments". */
				__( '%s management is coming soon.', 'doctor-ak-portal' ),
				$section_label
			)
		);
		?>
	</p>
</section>
